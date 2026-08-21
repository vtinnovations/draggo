<?php

declare(strict_types=1);

/*
 * Draggo
 *
 * Package: vtinnovations/draggo
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://v-t.one
 */

namespace Vtinnovations\Draggo\Editor;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Vtinnovations\Draggo\Element\OpenHours;
use Vtinnovations\Draggo\Exception\ElementNotFoundException;
use Vtinnovations\Draggo\Exception\InvalidInputException;
use Vtinnovations\Draggo\Grid\GridPresets;
use Vtinnovations\Draggo\Editor\FieldRegistry;
use Vtinnovations\Draggo\Layout\LayoutStyleCompiler;

/**
 * Maps between tl_content rows and ContentElementDto, and performs the
 * editor's mutations against tl_content. Contao stays the source of truth:
 * we use the same conventions the core backend uses (sorting in 128 steps,
 * tstamp bumps, tl_undo on delete).
 *
 * All writes are parameterised (no string interpolation into SQL).
 */
final class ContentSynchronizer
{
    private const SORT_STEP = 128;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly LayoutStyleCompiler $compiler,
        private readonly DcaFieldReader $dcaFields,
        private readonly \Vtinnovations\Draggo\Block\BlockTypeStore $blockTypes,
    ) {
    }

    /**
     * Ordered editable-field descriptors for a type. draggo_* keep their fully
     * hand-designed sets (FieldRegistry); core/3rd-party elements are derived
     * from the live DCA so every backend option is editable here too.
     *
     * @return list<array{name:string,widget:string,label:string,options:list<array{0:string,1:string}>,mandatory:bool}>
     */
    private function fieldSpecs(string $type): array
    {
        // Curated draggo_* elements keep their hand-designed field sets. draggo_*
        // types WITHOUT a FieldRegistry entry (e.g. every landing-page element
        // draggo_lp_*) fall through to the DCA reader below, so their full
        // backend palette becomes editable in the Draggo editor automatically.
        if (str_starts_with($type, 'draggo_') && FieldRegistry::fieldsFor($type) !== []) {
            $specs = [];
            foreach (FieldRegistry::fieldsFor($type) as $name) {
                $specs[] = [
                    'name'      => $name,
                    'widget'    => FieldRegistry::WIDGET[$name] ?? 'text',
                    'label'     => FieldRegistry::LABEL[$name] ?? $name,
                    'options'   => FieldRegistry::OPTIONS[$name] ?? [],
                    'mandatory' => \in_array($name, FieldRegistry::REQUIRED[$type] ?? [], true),
                ];
            }

            return $specs;
        }

        return $this->dcaFields->specs($type);
    }

    /**
     * Articles of a page, ordered as in the site structure, each with its
     * content-element DTOs. This is the page-scoped view the editor renders:
     * one section per article (a page may have several across columns).
     *
     * @return list<array{id:int,title:string,inColumn:string,elements:list<ContentElementDto>}>
     */
    public function listForPage(int $pageId): array
    {
        try {
            $articles = $this->connection->fetchAllAssociative(
                'SELECT id, title, inColumn, draggo_layout FROM tl_article WHERE pid = :pid ORDER BY sorting',
                ['pid' => $pageId],
            );
        } catch (\Throwable) {
            // draggo_layout column not migrated yet → degrade gracefully.
            $articles = $this->connection->fetchAllAssociative(
                'SELECT id, title, inColumn FROM tl_article WHERE pid = :pid ORDER BY sorting',
                ['pid' => $pageId],
            );
        }

        $out = [];
        foreach ($articles as $article) {
            $layout = [];
            if (!empty($article['draggo_layout'])) {
                $decoded = json_decode((string) $article['draggo_layout'], true);
                if (\is_array($decoded)) {
                    $layout = $decoded;
                }
            }
            $out[] = [
                'id'       => (int) $article['id'],
                'title'    => (string) ($article['title'] ?? ''),
                'inColumn' => (string) ($article['inColumn'] ?? 'main'),
                'layout'   => $layout,
                'elements' => $this->listForArticle((int) $article['id']),
            ];
        }

        return $out;
    }

    /**
     * All pages in the same site root as $pageId, in tree (pre-order) order with
     * a depth level for indentation — powers the editor's page switcher. Each:
     * {id,title,type,level,published}. Empty if the root can't be resolved.
     *
     * @return list<array{id:int,title:string,type:string,level:int,published:bool}>
     */
    public function pagesInRootOf(int $pageId): array
    {
        $rootId = $this->rootIdOf($pageId);
        if ($rootId === 0) {
            return [];
        }

        $out = [];
        $this->collectPages($rootId, 0, $out, 0);

        return $out;
    }

    /** Walk up the tree from $pageId to its site root (type=root or pid=0). */
    private function rootIdOf(int $pageId): int
    {
        $current = $pageId;
        for ($i = 0; $i < 100 && $current > 0; ++$i) {
            $row = $this->connection->fetchAssociative(
                'SELECT id, pid, type FROM tl_page WHERE id = :id',
                ['id' => $current],
            );
            if ($row === false) {
                return 0;
            }
            if ((string) $row['type'] === 'root' || (int) $row['pid'] === 0) {
                return (int) $row['id'];
            }
            $current = (int) $row['pid'];
        }

        return 0;
    }

    /**
     * Depth-first append of all pages below $pid, ordered by sorting.
     *
     * @param list<array{id:int,title:string,type:string,level:int,published:bool}> $out
     */
    private function collectPages(int $pid, int $level, array &$out, int $depth): void
    {
        if ($depth > 50) {
            return; // guard against pathological/cyclic trees
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title, type, published FROM tl_page WHERE pid = :pid ORDER BY sorting',
            ['pid' => $pid],
        );
        foreach ($rows as $row) {
            $out[] = [
                'id'        => (int) $row['id'],
                'title'     => (string) ($row['title'] ?? ''),
                'type'      => (string) ($row['type'] ?? 'regular'),
                'level'     => $level,
                'published' => !empty($row['published']),
            ];
            $this->collectPages((int) $row['id'], $level + 1, $out, $depth + 1);
        }
    }

    /**
     * Decoded container layout of a unit (header/footer), or [].
     *
     * @return array<string,mixed>
     */
    public function unitLayout(int $unitId): array
    {
        try {
            $raw = $this->connection->fetchOne('SELECT draggo_layout FROM tl_draggo_unit WHERE id = :id', ['id' => $unitId]);
        } catch (\Throwable) {
            return [];
        }
        if (!\is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Persist a unit's container layout.
     *
     * @param array<string,mixed> $layout
     */
    public function updateUnitLayout(int $unitId, array $layout, int $tstamp): void
    {
        $this->connection->update(
            'tl_draggo_unit',
            ['draggo_layout' => $layout === [] ? null : json_encode($layout), 'tstamp' => $tstamp],
            ['id' => $unitId],
        );
    }

    public function pageExists(int $pageId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT id FROM tl_page WHERE id = :id',
            ['id' => $pageId],
        );
    }

    public function unitExists(int $unitId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT id FROM tl_draggo_unit WHERE id = :id',
            ['id' => $unitId],
        );
    }

    /**
     * Create a new container = a tl_article in the page's "main" column.
     * (Draggo's design: one container == one article.) Returns its id.
     */
    /**
     * Create a container (article) at a position:
     *   $after === null → append at the end
     *   $after === 0    → insert at the very top
     *   $after === id   → insert directly after that article
     */
    public function createArticle(int $pageId, string $title, int $tstamp, ?int $after = null): int
    {
        $sorting = $this->computeArticleSorting($pageId, $after);

        // tl_article requires a unique alias.
        $alias = 'draggo-' . $pageId . '-' . $tstamp . '-' . random_int(1000, 9999);

        $this->connection->insert('tl_article', [
            'pid'       => $pageId,
            'sorting'   => $sorting,
            'tstamp'    => $tstamp,
            'title'     => $title !== '' ? $title : 'Container',
            'alias'     => $alias,
            'author'    => 0,
            'inColumn'  => 'main',
            'published' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function computeArticleSorting(int $pageId, ?int $after): int
    {
        if ($after === null) {
            $max = (int) $this->connection->fetchOne(
                'SELECT MAX(sorting) FROM tl_article WHERE pid = :pid',
                ['pid' => $pageId],
            );

            return $max + self::SORT_STEP;
        }

        if ($after === 0) {
            $min = $this->connection->fetchOne(
                'SELECT MIN(sorting) FROM tl_article WHERE pid = :pid',
                ['pid' => $pageId],
            );

            return $min === false || $min === null ? self::SORT_STEP : max(1, (int) (((int) $min) / 2));
        }

        $current = (int) $this->connection->fetchOne(
            'SELECT sorting FROM tl_article WHERE id = :id',
            ['id' => $after],
        );

        $next = $this->connection->fetchOne(
            'SELECT MIN(sorting) FROM tl_article WHERE pid = :pid AND sorting > :sorting',
            ['pid' => $pageId, 'sorting' => $current],
        );

        if ($next !== false && $next !== null) {
            $mid = (int) (($current + (int) $next) / 2);

            return $mid > $current ? $mid : $current + 1;
        }

        return $current + self::SORT_STEP;
    }

    /**
     * Delete a container (article) and all its content elements.
     */
    public function deleteArticle(int $articleId): void
    {
        $this->connection->transactional(function (Connection $conn) use ($articleId): void {
            $conn->delete('tl_content', ['pid' => $articleId, 'ptable' => 'tl_article']);
            $conn->delete('tl_article', ['id' => $articleId]);
        });
    }

    /**
     * Duplicate a whole container (article) WITH all its content — grid
     * wrappers, nesting, props and per-element layout included — placed right
     * after the original. A raw row-by-row copy preserves the structure exactly
     * (the grid is just sorting-ordered content rows). Returns the new article id.
     */
    public function duplicateArticle(int $articleId, int $tstamp): int
    {
        // In-place: same page, right after the source.
        $pid = (int) $this->connection->fetchOne('SELECT pid FROM tl_article WHERE id = :id', ['id' => $articleId]);

        return $this->copyArticle($articleId, $pid, $articleId, $tstamp);
    }

    /**
     * Copy a container (article) WITH all its content into a target page (the
     * cross-page "paste" path; pass the source's own page + id for an in-place
     * duplicate). Placed after {$after} or at the page end when null. Returns the
     * new article id.
     */
    public function copyArticle(int $sourceArticleId, int $targetPageId, ?int $after, int $tstamp): int
    {
        return $this->connection->transactional(function (Connection $conn) use ($sourceArticleId, $targetPageId, $after, $tstamp): int {
            $src = $conn->fetchAssociative('SELECT * FROM tl_article WHERE id = :id', ['id' => $sourceArticleId]);
            if ($src === false) {
                throw new ElementNotFoundException($sourceArticleId);
            }

            // Clone the article record into the target page (unique alias).
            unset($src['id']);
            $src['pid'] = $targetPageId;
            $src['sorting'] = $this->computeArticleSorting($targetPageId, $after);
            $src['tstamp'] = $tstamp;
            $src['alias'] = 'draggo-' . $targetPageId . '-' . $tstamp . '-' . random_int(1000, 9999);
            $src['title'] = ((string) ($src['title'] ?? 'Container')) . ' (Kopie)';
            $conn->insert('tl_article', $src);
            $newId = (int) $conn->lastInsertId();

            // Deep-copy every content row verbatim into the new article.
            $rows = $conn->fetchAllAssociative(
                "SELECT * FROM tl_content WHERE pid = :pid AND ptable = 'tl_article' ORDER BY sorting",
                ['pid' => $sourceArticleId],
            );
            foreach ($rows as $row) {
                unset($row['id']);
                $row['pid'] = $newId;
                $row['tstamp'] = $tstamp;
                $conn->insert('tl_content', $row);
            }

            return $newId;
        });
    }

    /**
     * Persist a new order of containers (articles) within a page.
     *
     * @param list<int> $orderedIds
     */
    public function reorderArticles(int $pageId, array $orderedIds, int $tstamp): void
    {
        $this->connection->transactional(function (Connection $conn) use ($pageId, $orderedIds, $tstamp): void {
            $sorting = self::SORT_STEP;
            foreach ($orderedIds as $id) {
                $conn->update('tl_article', ['sorting' => $sorting, 'tstamp' => $tstamp], ['id' => $id, 'pid' => $pageId]);
                $sorting += self::SORT_STEP;
            }
        });
    }

    public function articleInPage(int $articleId, int $pageId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT id FROM tl_article WHERE id = :id AND pid = :pid',
            ['id' => $articleId, 'pid' => $pageId],
        );
    }

    /**
     * Ordered DTO list for a container (article or unit).
     *
     * @return list<ContentElementDto>
     */
    public function listForContainer(int $pid, string $ptable = 'tl_article'): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, pid, type, sorting, headline, draggo_managed, draggo_layout,
                    draggo_grid_preset, draggo_grid_custom, draggo_grid_tablet, draggo_grid_mobile, draggo_container, draggo_blocktype
             FROM tl_content
             WHERE pid = :pid AND ptable = :ptable
             ORDER BY sorting',
            ['pid' => $pid, 'ptable' => $ptable],
        );

        return array_map([$this, 'rowToDto'], $rows);
    }

    /**
     * Back-compat alias for the page editor.
     *
     * @return list<ContentElementDto>
     */
    public function listForArticle(int $articleId): array
    {
        return $this->listForContainer($articleId, 'tl_article');
    }

    /**
     * Create a new element after the given sibling (or appended if null).
     * Returns the new id. Does NOT trust the type — callers must validate
     * via InputSanitizer first.
     */
    /**
     * Clone an existing element (with ALL its draggo_* settings + layout) into
     * a target container, placed after $afterId. The cssID scope hook is rebuilt
     * for the new id (no style bleed from the source) and a user-set fixed cssId
     * is dropped (no duplicate DOM id). Grid wrappers can't be cloned alone.
     */
    public function cloneElement(int $sourceId, int $targetPid, string $ptable, ?int $afterId, int $tstamp): int
    {
        return $this->insertElementData($this->exportElement($sourceId), $targetPid, $ptable, $afterId, $tstamp);
    }

    /**
     * Change an existing grid row's column structure (preset). Sets the new
     * preset on the row-start and adds/removes column separators to match the
     * target column count — content is preserved (removing a separator merges
     * its column into the previous one; adding appends empty columns).
     */
    public function restructureRow(int $rowStartId, string $preset, ?string $custom, int $tstamp, ?string $tablet = null, ?string $mobile = null): void
    {
        $row = $this->findRow($rowStartId);
        if ($row === null) {
            throw new ElementNotFoundException($rowStartId);
        }
        if ((string) $row['type'] !== 'draggo_row_start') {
            throw new InvalidInputException('Kein Reihen-Anfang.');
        }
        $pid = (int) $row['pid'];
        $ptable = (string) $row['ptable'];
        $container = (string) ($row['draggo_container'] ?: 'container');
        $targetCols = max(1, GridPresets::definition($preset, $container, $custom)->columnCount());

        $all = $this->connection->fetchAllAssociative(
            'SELECT id, type, sorting FROM tl_content WHERE pid = :pid AND ptable = :pt ORDER BY sorting',
            ['pid' => $pid, 'pt' => $ptable],
        );

        // Walk this row's range (depth-balanced); collect THIS row's top-level
        // column separators + the last element before the matching row-stop.
        $depth = 0; $started = false; $colIds = []; $stopId = 0; $lastInside = $rowStartId;
        foreach ($all as $r) {
            $id = (int) $r['id'];
            if ($id === $rowStartId) {
                $started = true;
            }
            if (!$started) {
                continue;
            }
            $t = (string) $r['type'];
            if ($t === 'draggo_row_start') {
                ++$depth;
            } elseif ($t === 'draggo_row_stop') {
                --$depth;
                if ($depth === 0) {
                    $stopId = $id;
                    break;
                }
                $lastInside = $id;
            } else {
                if ($t === 'draggo_col' && $depth === 1) {
                    $colIds[] = $id;
                }
                $lastInside = $id;
            }
        }
        if ($stopId === 0) {
            throw new InvalidInputException('Reihe ist beschädigt (kein Ende).');
        }

        $current = \count($colIds) + 1; // row-start opens column 0
        if ($targetCols > $current) {
            $after = $lastInside; // insert empty columns just before row-stop
            for ($i = $current; $i < $targetCols; ++$i) {
                $this->connection->insert('tl_content', [
                    'pid' => $pid, 'ptable' => $ptable, 'type' => 'draggo_col',
                    'sorting' => $this->computeSortingAfter($pid, $after, $ptable),
                    'tstamp' => $tstamp, 'draggo_managed' => '1',
                ]);
                $after = (int) $this->connection->lastInsertId();
            }
        } elseif ($targetCols < $current) {
            // Remove the last separators → their content merges left.
            foreach (\array_slice($colIds, $targetCols - $current) as $colId) {
                $this->connection->delete('tl_content', ['id' => $colId]);
            }
        }

        $update = [
            'draggo_grid_preset' => $preset,
            'draggo_grid_custom' => (string) ($custom ?? ''),
            'tstamp' => $tstamp,
        ];
        // Responsive column layout (per-breakpoint). null = leave as-is; ''
        // resets to inherit (tablet→desktop, mobile→stacked).
        if ($tablet !== null) {
            $update['draggo_grid_tablet'] = $tablet;
        }
        if ($mobile !== null) {
            $update['draggo_grid_mobile'] = $mobile;
        }
        $this->connection->update('tl_content', $update, ['id' => $rowStartId]);
    }

    /**
     * Convert a recognised THIRD-PARTY grid row (SubColumns / bootstrap-grid /
     * RockSolid) into Draggo's own wrappers, in place. Rewrites the start /
     * separators / stop types + sets a Draggo preset by column count. Content
     * stays; the frontend then renders via Draggo's grid. Opt-in (destructive
     * to the theme grid markup) — only the addressed (depth-1) row is touched.
     */
    public function convertForeignRow(int $startId, int $tstamp): void
    {
        $row = $this->findRow($startId);
        if ($row === null) {
            throw new ElementNotFoundException($startId);
        }
        $triple = \Vtinnovations\Draggo\Grid\ForeignGrids::tripleForStart((string) $row['type']);
        if ($triple === null) {
            throw new InvalidInputException('Keine erkannte Theme-Grid-Reihe.');
        }
        $pid = (int) $row['pid'];
        $ptable = (string) $row['ptable'];

        $all = $this->connection->fetchAllAssociative(
            'SELECT id, type FROM tl_content WHERE pid = :pid AND ptable = :pt ORDER BY sorting',
            ['pid' => $pid, 'pt' => $ptable],
        );

        $depth = 0; $started = false; $sepIds = []; $stopId = 0;
        foreach ($all as $r) {
            $id = (int) $r['id'];
            if ($id === $startId) {
                $started = true;
            }
            if (!$started) {
                continue;
            }
            $t = (string) $r['type'];
            if ($t === $triple['start']) {
                ++$depth;
            } elseif ($t === $triple['stop']) {
                --$depth;
                if ($depth === 0) {
                    $stopId = $id;
                    break;
                }
            } elseif ($t === $triple['separator'] && $depth === 1) {
                $sepIds[] = $id;
            }
        }
        if ($stopId === 0) {
            throw new InvalidInputException('Theme-Reihe unvollständig (kein Ende).');
        }

        $count = \count($sepIds) + 1;
        $presetByCount = [1 => '1', 2 => '6-6', 3 => '4-4-4', 4 => '3-3-3-3'];
        $preset = $presetByCount[$count] ?? 'custom';
        $custom = $preset === 'custom' ? implode(',', array_fill(0, $count, max(1, intdiv(12, $count)))) : '';

        $this->connection->update('tl_content', [
            'type' => 'draggo_row_start', 'draggo_grid_preset' => $preset,
            'draggo_grid_custom' => $custom, 'draggo_container' => 'container',
            'draggo_managed' => '1', 'tstamp' => $tstamp,
        ], ['id' => $startId]);
        foreach ($sepIds as $sid) {
            $this->connection->update('tl_content', ['type' => 'draggo_col', 'draggo_managed' => '1', 'tstamp' => $tstamp], ['id' => $sid]);
        }
        $this->connection->update('tl_content', ['type' => 'draggo_row_stop', 'draggo_managed' => '1', 'tstamp' => $tstamp], ['id' => $stopId]);
    }

    /** Rename a container (tl_article.title) — for backend overview. */
    public function renameArticle(int $articleId, string $title, int $tstamp): void
    {
        $title = trim($title);
        if ($title === '') {
            return;
        }
        $this->connection->update('tl_article', ['title' => mb_substr($title, 0, 255), 'tstamp' => $tstamp], ['id' => $articleId]);
    }

    /** Duplicate an element in place (same container, right after itself). */
    public function duplicateElement(int $sourceId, int $tstamp): int
    {
        $row = $this->findRow($sourceId);
        if ($row === null) {
            throw new ElementNotFoundException($sourceId);
        }

        return $this->cloneElement($sourceId, (int) $row['pid'], (string) $row['ptable'], $sourceId, $tstamp);
    }

    /**
     * Portable field set of an element (for clipboard / components): all columns
     * except the container/meta ones, with the layout's fixed cssId stripped.
     * Grid wrappers can't be exported alone.
     *
     * @return array<string,mixed>
     */
    public function exportElement(int $sourceId): array
    {
        $row = $this->findRow($sourceId);
        if ($row === null) {
            throw new ElementNotFoundException($sourceId);
        }
        if (\in_array((string) $row['type'], ['draggo_row_start', 'draggo_col', 'draggo_row_stop'], true)) {
            throw new InvalidInputException('Grid-Struktur-Elemente können nicht einzeln verwendet werden.');
        }

        return $this->stripRow($row);
    }

    /**
     * Portable, ordered field sets of a WHOLE grid row (row-start … matching
     * row-stop, incl. columns + their content + nested rows). For section
     * components. Depth-balanced so grid-in-grid is captured correctly.
     *
     * @return list<array<string,mixed>>
     */
    public function exportRowTree(int $rowStartId): array
    {
        $row = $this->findRow($rowStartId);
        if ($row === null) {
            throw new ElementNotFoundException($rowStartId);
        }
        if ((string) $row['type'] !== 'draggo_row_start') {
            throw new InvalidInputException('Kein Reihen-Anfang.');
        }

        $all = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_content WHERE pid = :pid AND ptable = :pt ORDER BY sorting',
            ['pid' => (int) $row['pid'], 'pt' => (string) $row['ptable']],
        );

        $items = [];
        $depth = 0;
        $started = false;
        foreach ($all as $r) {
            if ((int) $r['id'] === $rowStartId) {
                $started = true;
            }
            if (!$started) {
                continue;
            }
            $type = (string) $r['type'];
            if ($type === 'draggo_row_start') {
                ++$depth;
            }
            $items[] = $this->stripRow($r);
            if ($type === 'draggo_row_stop') {
                --$depth;
                if ($depth <= 0) {
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * Portable snapshot of a whole container's content (ordered field sets),
     * for restore points. Grid wrappers + their namespaced layout are kept.
     *
     * @return list<array<string,mixed>>
     */
    public function exportContainer(int $pid, string $ptable): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_content WHERE pid = :pid AND ptable = :pt ORDER BY sorting',
            ['pid' => $pid, 'pt' => $ptable],
        );

        return array_map(fn (array $r): array => $this->stripRow($r), $rows);
    }

    /**
     * Replace ALL content of a container with a snapshot's field sets (restore).
     * Wipe + re-insert in order inside a transaction so a failed restore can't
     * leave the container half-empty.
     *
     * @param list<array<string,mixed>> $items
     */
    public function replaceContainer(int $pid, string $ptable, array $items, int $tstamp): void
    {
        $this->connection->transactional(function (Connection $conn) use ($pid, $ptable, $items, $tstamp): void {
            $conn->executeStatement(
                'DELETE FROM tl_content WHERE pid = :pid AND ptable = :pt',
                ['pid' => $pid, 'pt' => $ptable],
            );
            $after = null;
            foreach ($items as $data) {
                if (!\is_array($data) || ($data['type'] ?? '') === '') {
                    continue;
                }
                $after = $this->insertElementData($data, $pid, $ptable, $after, $tstamp);
            }
        });
    }

    /**
     * Insert an ordered list of portable field sets (a section component),
     * chaining sorting so the structure stays contiguous + in order. Returns
     * the first new element id.
     *
     * @param list<array<string,mixed>> $items
     */
    public function insertTree(array $items, int $pid, string $ptable, ?int $afterId, int $tstamp): int
    {
        $after = $afterId;
        $first = 0;
        foreach ($items as $data) {
            if (!\is_array($data) || ($data['type'] ?? '') === '') {
                continue;
            }
            $newId = $this->insertElementData($data, $pid, $ptable, $after, $tstamp);
            if ($first === 0) {
                $first = $newId;
            }
            $after = $newId;
        }
        if ($first === 0) {
            throw new InvalidInputException('Leere Komponente.');
        }

        return $first;
    }

    /**
     * Strip container/meta columns + a fixed cssId from a tl_content row, leaving
     * a portable field set. Grid wrappers keep their namespaced row/col layout.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function stripRow(array $row): array
    {
        foreach (['id', 'pid', 'ptable', 'sorting', 'tstamp', 'cssID', 'draggo_managed'] as $meta) {
            unset($row[$meta]);
        }
        if (!empty($row['draggo_layout'])) {
            $decoded = json_decode((string) $row['draggo_layout'], true);
            if (\is_array($decoded) && !isset($decoded['row']) && !isset($decoded['col'])) {
                unset($decoded['cssId']);
                $row['draggo_layout'] = $decoded === [] ? null : json_encode($decoded);
            }
        }

        return $row;
    }

    /**
     * Insert a new element from a portable field set (clone / component / paste).
     * Rebuilds the cssID scope hook for the new id (no style bleed).
     *
     * @param array<string,mixed> $data
     */
    public function insertElementData(array $data, int $targetPid, string $ptable, ?int $afterId, int $tstamp): int
    {
        // Never trust caller-supplied container/meta keys.
        foreach (['id', 'pid', 'ptable', 'sorting', 'tstamp', 'cssID', 'draggo_managed'] as $meta) {
            unset($data[$meta]);
        }
        if (($data['type'] ?? '') === '') {
            throw new InvalidInputException('Element-Typ fehlt.');
        }

        $layoutForHook = [];
        if (!empty($data['draggo_layout'])) {
            $decoded = json_decode((string) $data['draggo_layout'], true);
            if (\is_array($decoded) && !isset($decoded['row']) && !isset($decoded['col'])) {
                unset($decoded['cssId']);
                $layoutForHook = $decoded;
            }
        }

        $data['pid'] = $targetPid;
        $data['ptable'] = $ptable;
        $data['sorting'] = $this->computeSortingAfter($targetPid, $afterId, $ptable);
        $data['tstamp'] = $tstamp;
        $data['draggo_managed'] = '1';
        $data['cssID'] = '';

        $this->connection->insert('tl_content', $this->onlyExistingColumns('tl_content', $data));
        $newId = (int) $this->connection->lastInsertId();

        $this->ensureContentCssClass($newId, $layoutForHook);

        return $newId;
    }

    public function createElement(int $pid, string $type, ?int $afterId, int $tstamp, string $ptable = 'tl_article', ?string $blocktype = null): int
    {
        $sorting = $this->computeSortingAfter($pid, $afterId, $ptable);

        $insert = [
            'pid'             => $pid,
            'ptable'          => $ptable,
            'type'            => $type,
            'sorting'         => $sorting,
            'tstamp'          => $tstamp,
            'draggo_managed' => '1',
        ];
        // AI block instance: bind it to its block-type definition.
        if ($type === 'draggo_block' && $blocktype !== null && $blocktype !== '') {
            $insert['draggo_blocktype'] = $blocktype;
        }
        $this->connection->insert('tl_content', $insert);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Insert a full grid structure (row-start + N-1 column separators +
     * row-stop) in one go and return the created element ids in order.
     *
     * @return list<int>
     */
    public function insertStructure(
        int $pid,
        string $preset,
        ?string $custom,
        ?int $afterId,
        int $tstamp,
        string $ptable = 'tl_article',
    ): array {
        $definition = GridPresets::definition($preset, 'container', $custom);
        $columns = max(1, $definition->columnCount());
        $ids = [];

        // Renumber the WHOLE container afterwards so the new wrappers land in
        // the exact intended position even inside a tight gap (grid-in-grid).
        // Gap-based sorting would otherwise overflow a nested column.
        $this->connection->transactional(function (Connection $conn) use (
            $pid, $ptable, $preset, $custom, $columns, $tstamp, $afterId, &$ids
        ): void {
            // Existing order before insertion.
            $existing = array_map('intval', $conn->fetchFirstColumn(
                'SELECT id FROM tl_content WHERE pid = :pid AND ptable = :ptable ORDER BY sorting',
                ['pid' => $pid, 'ptable' => $ptable],
            ));

            // Append the new rows (temporary sorting; renumbered below).
            $tmp = 1_000_000;
            $insert = function (array $data) use ($conn, &$ids, &$tmp): void {
                $data['sorting'] = $tmp;
                $data['tstamp'] = 0; // real tstamp set during the renumber below
                $conn->insert('tl_content', $data);
                $ids[] = (int) $conn->lastInsertId();
                $tmp += 128;
            };

            $insert([
                'pid' => $pid, 'ptable' => $ptable, 'type' => 'draggo_row_start', 'draggo_managed' => '1',
                'draggo_grid_preset' => $preset, 'draggo_container' => 'container', 'draggo_grid_custom' => (string) $custom,
            ]);
            for ($i = 1; $i < $columns; $i++) {
                $insert(['pid' => $pid, 'ptable' => $ptable, 'type' => 'draggo_col', 'draggo_managed' => '1']);
            }
            $insert(['pid' => $pid, 'ptable' => $ptable, 'type' => 'draggo_row_stop', 'draggo_managed' => '1']);

            // Build the desired order: splice new ids right after $afterId.
            $pos = $afterId !== null ? array_search($afterId, $existing, true) : false;
            if ($pos === false) {
                $order = array_merge($existing, $ids); // append at end
            } else {
                $order = array_merge(
                    \array_slice($existing, 0, $pos + 1),
                    $ids,
                    \array_slice($existing, $pos + 1),
                );
            }

            // Renumber everything in 128-steps.
            $sorting = self::SORT_STEP;
            foreach ($order as $id) {
                $conn->update('tl_content', ['sorting' => $sorting, 'tstamp' => $tstamp], ['id' => $id]);
                $sorting += self::SORT_STEP;
            }
        });

        return $ids;
    }

    /**
     * Persist a new explicit order within one container. $orderedIds must
     * already be validated to all belong to $pid/$ptable (ownership check).
     *
     * @param list<int> $orderedIds
     */
    public function reorder(int $pid, array $orderedIds, int $tstamp, string $ptable = 'tl_article'): void
    {
        $this->connection->transactional(function (Connection $conn) use ($pid, $orderedIds, $tstamp, $ptable): void {
            $sorting = self::SORT_STEP;
            foreach ($orderedIds as $id) {
                $conn->update(
                    'tl_content',
                    ['sorting' => $sorting, 'tstamp' => $tstamp, 'draggo_managed' => '1'],
                    ['id' => $id, 'pid' => $pid, 'ptable' => $ptable],
                );
                $sorting += self::SORT_STEP;
            }
        });
    }

    /**
     * Update a whitelisted set of scalar columns on one element.
     *
     * @param array<string,scalar|null> $values already sanitised
     */
    public function updateElement(int $elementId, array $values, int $tstamp): void
    {
        if ($values === []) {
            return;
        }

        $values['tstamp'] = $tstamp;
        $values['draggo_managed'] = '1';

        $affected = $this->connection->update('tl_content', $values, ['id' => $elementId]);

        if ($affected === 0 && !$this->exists($elementId)) {
            throw new ElementNotFoundException($elementId);
        }
    }

    /**
     * Store the layout JSON for one element (already sanitised).
     * $scope: 'flat' (store as-is, e.g. a plain column) or 'row'/'col'
     * (merge into a namespaced object on the row-start element).
     *
     * @param array<string,string> $layout
     */
    public function updateLayout(int $elementId, array $layout, string $scope, int $tstamp): void
    {
        if ($scope === 'row' || $scope === 'col') {
            $existing = $this->decodeJsonColumn($elementId, 'draggo_layout');
            // Drop a legacy flat object (migrate it into 'col').
            if ($existing !== [] && !isset($existing['row'], $existing['col']) && !isset($existing['row']) && !isset($existing['col'])) {
                $existing = ['col' => $existing];
            }
            if ($layout === []) {
                unset($existing[$scope]);
            } else {
                $existing[$scope] = $layout;
            }
            $value = $existing === [] ? null : json_encode($existing);
        } else {
            $value = $layout === [] ? null : json_encode($layout);
            // Flat scope = element style → ensure a CSS hook class + apply the
            // user's own id/class (Phase C) onto the real element via cssID.
            $this->ensureContentCssClass($elementId, $layout);
        }

        $this->connection->update(
            'tl_content',
            ['draggo_layout' => $value, 'tstamp' => $tstamp, 'draggo_managed' => '1'],
            ['id' => $elementId],
        );
    }

    /**
     * Ensure draggo-el-{id} is on a content element's cssID class (for scoped
     * CSS) and apply the user's own id/class from the layout (Phase C) so they
     * become real attributes on the rendered element.
     *
     * @param array<string,mixed> $layout
     */
    private function ensureContentCssClass(int $elementId, array $layout = []): void
    {
        $hook = 'draggo-el-' . $elementId;
        $userId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($layout['cssId'] ?? '')) ?? '';

        $cssId = $this->connection->fetchOne('SELECT cssID FROM tl_content WHERE id = :id', ['id' => $elementId]);
        $parts = $cssId ? (@unserialize((string) $cssId, ['allowed_classes' => false]) ?: ['', '']) : ['', ''];
        $id = $userId !== '' ? $userId : (string) ($parts[0] ?? '');

        $userClasses = preg_split('/\s+/', trim(preg_replace('/[^A-Za-z0-9 _-]/', '', (string) ($layout['cssClass'] ?? '')) ?? '')) ?: [];
        $classes = array_values(array_filter($userClasses, static fn ($c) => $c !== '' && $c !== $hook));
        $classes[] = $hook;

        $this->connection->update('tl_content', ['cssID' => serialize([$id, implode(' ', $classes)])], ['id' => $elementId]);
    }

    /**
     * Store the container (article) layout + ensure a stable CSS hook class
     * (draggo-art-{id}) on the article wrapper via tl_article.cssID.
     *
     * @param array<string,string> $layout
     */
    public function updateArticleLayout(int $articleId, array $layout, int $tstamp): void
    {
        // ID + extra class from the layout (user-set), plus our hook class.
        $userId = (string) ($layout['cssId'] ?? '');
        $userClass = trim((string) ($layout['cssClass'] ?? ''));
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', $userId) ?? '';
        $classes = $userClass !== '' ? (preg_split('/\s+/', preg_replace('/[^A-Za-z0-9 _-]/', '', $userClass) ?? '') ?: []) : [];
        $classes = array_values(array_filter($classes, static fn ($c) => $c !== '' && $c !== 'draggo-art-' . $articleId));
        $classes[] = 'draggo-art-' . $articleId;

        $this->connection->update(
            'tl_article',
            [
                'draggo_layout' => $layout === [] ? null : json_encode($layout),
                'cssID'          => serialize([$id, implode(' ', $classes)]),
                'tstamp'         => $tstamp,
            ],
            ['id' => $articleId],
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonColumn(int $elementId, string $column): array
    {
        $raw = $this->connection->fetchOne("SELECT {$column} FROM tl_content WHERE id = :id", ['id' => $elementId]);
        if (!\is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Delete an element. The Contao backend writes a tl_undo entry before
     * hard-deleting; the API controller delegates that to DataContainer\Undo
     * logic where available. Here we hard-delete after the guard confirmed
     * ownership; the undo snapshot is taken by the caller.
     */
    public function deleteElement(int $elementId): void
    {
        $this->connection->delete('tl_content', ['id' => $elementId]);
    }

    /**
     * Inline-editable fields of an element (type-whitelisted via FieldRegistry).
     *
     * @return array{type:string, fields:list<array{name:string,widget:string,label:string,value:string}>}
     */
    /** BlockSpec field type → editor widget. */
    private function blockWidget(string $t): string
    {
        return match ($t) {
            'textarea', 'richtext' => 'rte',
            'image'     => 'file',
            'images'    => 'files',
            'list'      => 'lines',
            'pairs'     => 'pairs',      // title + text repeater
            'accordion' => 'accitems',  // title + rich-text repeater
            'iconlinks' => 'iconpairs', // icon + url repeater
            'bool'      => 'bool',
            'select'    => 'select',
            'icon'      => 'icon',
            default     => 'text', // text, url, number, range, color
        };
    }

    /**
     * Editor field set for an AI block instance: descriptors from the BlockSpec,
     * values from draggo_data. Image values are plain files/ paths (not UUIDs).
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function blockFields(array $row): array
    {
        $spec = $this->blockTypes->find((string) ($row['draggo_blocktype'] ?? ''));
        $data = json_decode((string) ($row['draggo_data'] ?? ''), true);
        $data = \is_array($data) ? $data : [];

        $fields = [];
        if ($spec !== null) {
            foreach ($spec->fields as $f) {
                $name = $f['name'];
                $widget = $this->blockWidget($f['type']);
                $field = ['name' => $name, 'widget' => $widget, 'label' => $f['label'], 'value' => ''];
                $val = $data[$name] ?? '';
                if ($widget === 'bool') {
                    $field['value'] = !empty($val) ? '1' : '';
                } elseif ($widget === 'file') {
                    $field['value'] = (string) $val; // stored as files/ path
                    $field['accept'] = 'image';
                } elseif ($widget === 'files') {
                    $field['value'] = implode("\n", \is_array($val) ? array_map('strval', $val) : []);
                    $field['accept'] = 'image';
                } elseif ($widget === 'lines') {
                    $field['value'] = implode("\n", \is_array($val) ? array_map('strval', $val) : []);
                } elseif ($widget === 'pairs' || $widget === 'accitems' || $widget === 'iconpairs') {
                    // Repeatable {key,value} items — pass through as-is.
                    $field['value'] = \is_array($val) ? array_values(array_filter($val, 'is_array')) : [];
                } elseif ($widget === 'select') {
                    $field['value'] = (string) $val;
                    $field['options'] = $f['options'] ?? [];
                } else {
                    $field['value'] = (string) $val;
                }
                $fields[] = $field;
            }
            $fields[] = ['name' => 'protected', 'widget' => 'bool', 'label' => 'Nur für eingeloggte Mitglieder', 'value' => !empty($row['protected']) ? '1' : ''];
            $fields[] = ['name' => 'groups', 'widget' => 'mselect', 'label' => 'Mitglieder-Gruppen', 'value' => array_map('strval', StringUtil::deserialize($row['groups'] ?? '', true)), 'options' => $this->dbOptions('tl_member_group', 'name'), 'condition' => ['field' => 'protected', 'value' => '1']];
        }

        $style = [];
        if (!empty($row['draggo_layout'])) {
            $decoded = json_decode((string) $row['draggo_layout'], true);
            if (\is_array($decoded) && !isset($decoded['row']) && !isset($decoded['col'])) {
                $style = $decoded;
            }
        }

        return [
            'type'          => 'draggo_block',
            'blocktype'     => (string) ($row['draggo_blocktype'] ?? ''),
            'fields'        => $fields,
            'style'         => $style,
            'styleControls' => \Vtinnovations\Draggo\Control\StyleSchema::for('draggo_block'),
            // AI block elements never force a field to be filled to save: optional
            // options (toggles, extra text, slides) must stay truly optional, and
            // the sandbox template renders empty fields harmlessly. Forcing every
            // field mandatory made e.g. a "loop" toggle block saving.
            'required'      => [],
        ];
    }

    public function getElementFields(int $elementId): array
    {
        $row = $this->findRow($elementId);
        if ($row === null) {
            throw new ElementNotFoundException($elementId);
        }

        $type = (string) $row['type'];

        // AI block instance: fields come from the BlockSpec, values from the
        // draggo_data JSON (not real columns). Separate path.
        if ($type === 'draggo_block') {
            return $this->blockFields($row);
        }

        $fields = [];
        foreach ($this->fieldSpecs($type) as $spec) {
            $name = $spec['name'];
            $widget = $spec['widget'];
            $field = [
                'name'   => $name,
                'widget' => $widget,
                'label'  => $spec['label'],
                'value'  => '',
            ];

            if ($widget === 'headline') {
                $field['value'] = $this->extractHeadline($row['headline'] ?? '');
                $field['unit'] = $this->headlineUnit($row['headline'] ?? '');
                $field['units'] = FieldRegistry::HEADLINE_UNITS;
            } elseif ($widget === 'file') {
                $field['value'] = $this->uuidToPath($row[$name] ?? null);
                $field['accept'] = $this->fileAccept($type, $name);
            } elseif ($widget === 'files') {
                $field['value'] = implode("\n", $this->uuidsToPaths($row[$name] ?? null));
                $field['accept'] = $this->fileAccept($type, $name);
            } elseif ($widget === 'imgsize') {
                $field['value'] = $this->decodeSize($row[$name] ?? null);
            } elseif ($widget === 'select' || $widget === 'mselect') {
                $field['value'] = $widget === 'mselect'
                    ? array_map('strval', StringUtil::deserialize($row[$name] ?? '', true))
                    : (string) ($row[$name] ?? '');
                // DCA-derived DB source (e.g. a pageTree → tl_page) takes first;
                // then DB-backed selects resolved by field name; else DCA/curated.
                $field['options'] = !empty($spec['optionsSource'])
                    ? $this->dbOptions((string) $spec['optionsSource'], 'title')
                    : match ($name) {
                        'module'                  => $this->dbOptions('tl_module', 'name'),
                        'form', 'draggo_form_id'  => $this->dbOptions('tl_form', 'title'),
                        'articleAlias', 'article' => $this->dbOptions('tl_article', 'title'),
                        'draggo_nav_root', 'draggo_loop_parent', 'draggo_sm_root', 'draggo_se_page' => $this->dbOptions('tl_page', 'title'),
                        'draggo_loop_archive'    => $this->dbOptions('tl_news_archive', 'title'),
                        'draggo_loop_calendar'   => $this->dbOptions('tl_calendar', 'title'),
                        'draggo_loop_category'   => $this->dbOptions('tl_news_category', 'title'),
                        'draggo_global_unit'     => $this->dbOptions('tl_draggo_unit', 'title'),
                        default                   => $spec['options'],
                    };
            } elseif ($widget === 'bool') {
                $field['value'] = !empty($row[$name]) ? '1' : '';
            } elseif ($widget === 'lines') {
                $field['value'] = implode("\n", StringUtil::deserialize($row[$name] ?? '', true));
            } elseif ($widget === 'table') {
                $field['value'] = $this->decodeTable($row[$name] ?? '');
            } elseif ($widget === 'pairs' || $widget === 'iconpairs' || $widget === 'accitems') {
                $field['value'] = $this->decodePairs($row[$name] ?? '');
            } elseif ($widget === 'cards') {
                $field['value'] = $this->decodeCards($row[$name] ?? '');
            } elseif ($widget === 'hours') {
                $field['value'] = $this->decodeOpenHours($row[$name] ?? '');
            } elseif ($widget === 'datetime') {
                $ts = (int) ($row[$name] ?? 0);
                $field['value'] = $ts > 0 ? date('Y-m-d\TH:i', $ts) : '';
            } else {
                $field['value'] = (string) ($row[$name] ?? '');
            }

            // Subpalette show/hide condition (mirrors the backend).
            if (!empty($spec['condition'])) {
                $field['condition'] = $spec['condition'];
            }
            // Callback-backed select with no resolvable options → keep the saved
            // value selectable instead of an empty dropdown.
            if ($widget === 'select' && empty($field['options']) && (string) $field['value'] !== '') {
                $field['options'] = [[(string) $field['value'], (string) $field['value']]];
            }

            $fields[] = $field;
        }

        // Universal access control (Contao-native protected/groups). Only for
        // real editable elements — never the grid-wrapper rows (row/col/stop).
        if ($fields !== []) {
            $fields[] = [
                'name'   => 'protected',
                'widget' => 'bool',
                'label'  => 'Nur für eingeloggte Mitglieder',
                'value'  => !empty($row['protected']) ? '1' : '',
            ];
            $fields[] = [
                'name'    => 'groups',
                'widget'  => 'mselect',
                'label'   => 'Mitglieder-Gruppen',
                'value'   => array_map('strval', StringUtil::deserialize($row['groups'] ?? '', true)),
                'options' => $this->dbOptions('tl_member_group', 'name'),
                'condition' => ['field' => 'protected', 'value' => '1'],
            ];
        }

        // Current element style (flat draggo_layout, content elements only).
        $style = [];
        if (!empty($row['draggo_layout'])) {
            $decoded = json_decode((string) $row['draggo_layout'], true);
            if (\is_array($decoded) && !isset($decoded['row']) && !isset($decoded['col'])) {
                $style = $decoded;
            }
        }

        $required = [];
        foreach ($this->fieldSpecs($type) as $spec) {
            // Only HARD-require always-visible fields. A field inside a
            // subpalette (carries a condition, e.g. singleSRC under "addImage")
            // is mandatory only when its toggle is on — Contao itself enforces
            // it then, so never block the editor when the toggle is off.
            if (!empty($spec['mandatory']) && empty($spec['condition'])) {
                $required[] = $spec['name'];
            }
        }

        return [
            'type'          => $type,
            'fields'        => $fields,
            'style'         => $style,
            'styleControls' => \Vtinnovations\Draggo\Control\StyleSchema::for($type),
            'required'      => $required,
        ];
    }

    /**
     * Save inline-edited fields (only whitelisted ones for the element's type).
     *
     * @param array<string,mixed> $values
     */
    public function saveElementFields(int $elementId, array $values, int $tstamp): void
    {
        $row = $this->findRow($elementId);
        if ($row === null) {
            throw new ElementNotFoundException($elementId);
        }

        // AI block instance → write values into draggo_data JSON (BlockSpec
        // field names only), never real columns.
        if ((string) $row['type'] === 'draggo_block') {
            $this->saveBlockFields($elementId, $row, $values, $tstamp);

            return;
        }

        // Resolve the type's editable fields once (DCA-derived for core types,
        // curated for draggo_*). Drives the whitelist + per-field widget/options.
        $specs = $this->fieldSpecs((string) $row['type']);
        $widgetMap = [];
        $optionsMap = [];
        foreach ($specs as $spec) {
            $widgetMap[$spec['name']] = $spec['widget'];
            $optionsMap[$spec['name']] = $spec['options'];
        }
        $allowed = array_keys($widgetMap);
        $set = [];

        foreach ($values as $key => $value) {
            // Universal access fields bypass the per-type whitelist.
            if ($key === 'protected') {
                // protected is int-typed on some installs → never store '' (strict SQL).
                $set['protected'] = !empty($value) ? '1' : '0';
                continue;
            }
            if ($key === 'groups') {
                $ids = \is_array($value) ? $value : [];
                $set['groups'] = serialize(array_values(array_map('intval', $ids)));
                continue;
            }

            if (!\in_array($key, $allowed, true)) {
                continue;
            }
            $widget = $widgetMap[$key] ?? 'text';

            if ($widget === 'headline') {
                $unit = \is_array($value) ? (string) ($value['unit'] ?? 'h2') : $this->headlineUnit($row['headline'] ?? '');
                $unit = \in_array($unit, FieldRegistry::HEADLINE_UNITS, true) ? $unit : 'h2';
                $text = \is_array($value) ? (string) ($value['value'] ?? '') : (string) $value;
                $set['headline'] = serialize(['unit' => $unit, 'value' => $text]);
            } elseif ($widget === 'file') {
                // File UUIDs are binary. A real column stores binary; a JSON
                // (props-backed) field must store the STRING UUID instead —
                // binary would break json_encode (invalid UTF-8). findByUuid()
                // accepts both, so read paths are unaffected.
                $uuid = $this->pathToUuid((string) $value);
                $set[$key] = ($uuid !== null && $this->isPropField($key)) ? StringUtil::binToUuid($uuid) : $uuid;
            } elseif ($widget === 'files') {
                $uuids = $this->pathsToUuids((string) $value);
                if ($this->isPropField($key)) {
                    $uuids = array_map(static fn ($u): string => StringUtil::binToUuid($u), $uuids);
                }
                $set[$key] = serialize($uuids);
            } elseif ($widget === 'imgsize') {
                $set[$key] = $this->encodeSize(\is_array($value) ? $value : []);
            } elseif ($widget === 'select') {
                $val = (string) $value;
                // Integer-backed selects (ids / counts) must store an int, never ''.
                if (\in_array($key, ['module', 'form', 'draggo_form_id', 'articleAlias', 'article', 'draggo_nav_root', 'draggo_nav_levels', 'draggo_loop_parent', 'draggo_loop_archive', 'draggo_loop_calendar', 'draggo_loop_category', 'draggo_sm_root', 'draggo_global_unit', 'draggo_se_page'], true)) {
                    $set[$key] = (int) $val;
                } else {
                    $allowedVals = array_map(static fn (array $o): string => $o[0], $optionsMap[$key] ?? []);
                    $set[$key] = ($allowedVals === [] || \in_array($val, $allowedVals, true)) ? $val : '';
                }
            } elseif ($widget === 'mselect') {
                // Multi-select / multi-checkbox → serialized list of allowed values.
                $vals = \is_array($value) ? array_map('strval', $value) : [];
                $allowedVals = array_map(static fn (array $o): string => $o[0], $optionsMap[$key] ?? []);
                if ($allowedVals !== []) {
                    $vals = array_values(array_intersect($vals, $allowedVals));
                }
                $set[$key] = serialize($vals);
            } elseif ($widget === 'icon') {
                // Built-in SVG key (no spaces) OR a Font Awesome class string
                // ("fa-solid fa-star" — spaces matter). Keep letters/digits/space/-.
                $v = strtolower(trim((string) $value));
                $v = preg_replace('/[^a-z0-9 -]/', '', $v) ?? '';
                $set[$key] = trim(preg_replace('/\s+/', ' ', $v) ?? '');
            } elseif ($widget === 'bool') {
                $set[$key] = !empty($value) ? '1' : '';
            } elseif ($widget === 'color') {
                // Hex (#rgb/#rrggbb/#rrggbbaa) or a design token var(--bld-color-…).
                $v = trim((string) $value);
                $set[$key] = preg_match('/^(?:#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|var\(--bld-color-[a-z0-9-]+\))$/', $v) ? $v : '';
            } elseif ($widget === 'lines') {
                $items = array_values(array_filter(array_map('trim', explode("\n", (string) $value)), static fn (string $s): bool => $s !== ''));
                $set[$key] = serialize($items);
            } elseif ($widget === 'table') {
                $set[$key] = serialize($this->encodeTable($value));
            } elseif ($widget === 'pairs' || $widget === 'iconpairs' || $widget === 'accitems') {
                $set[$key] = serialize($this->encodePairs($value));
            } elseif ($widget === 'cards') {
                $set[$key] = serialize($this->encodeCards($value));
            } elseif ($widget === 'hours') {
                $enc = $this->encodeOpenHours($value);
                $set[$key] = $enc === [] ? '' : serialize($enc);
            } elseif ($widget === 'datetime') {
                $v = trim((string) $value);
                $set[$key] = $v !== '' ? (int) strtotime($v) : 0;
            } elseif (\in_array($key, ['draggo_space', 'draggo_div_thickness', 'draggo_div_width', 'draggo_car_per', 'draggo_car_speed', 'draggo_loop_cols', 'draggo_loop_limit', 'draggo_counter_start', 'draggo_counter_end', 'draggo_counter_duration', 'draggo_progress_value', 'draggo_flip_height', 'draggo_sm_levels', 'draggo_map_zoom', 'draggo_map_height', 'draggo_ga_cols', 'draggo_ga_gap'], true)) {
                // Integer-backed columns must never receive '' (strict SQL).
                $set[$key] = (int) $value;
            } else {
                $set[$key] = (string) $value;
            }
        }

        if ($set === []) {
            return;
        }

        // Route element CONTENT fields into the consolidated draggo_props JSON
        // (instead of individual columns). Structural/core columns stay columns.
        $props = [];
        foreach ($set as $key => $value) {
            if ($this->isPropField($key)) {
                $props[$key] = $value;
                unset($set[$key]);
            }
        }
        if ($props !== []) {
            $existing = $this->decodeJsonColumn($elementId, 'draggo_props');
            foreach ($props as $k => $v) {
                // Empty value clears the key (keeps the JSON lean).
                if ($v === null || $v === '' || $v === '0') {
                    unset($existing[$k]);
                } else {
                    $existing[$k] = $v;
                }
            }
            $set['draggo_props'] = $existing === [] ? null : json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $set['tstamp'] = $tstamp;
        $set['draggo_managed'] = '1';
        // Defensive: never write to a column the DB doesn't have (e.g. a core
        // field like serverPagination on a not-fully-migrated install) — that
        // would abort the whole save. Drop unknown columns silently.
        $set = $this->onlyExistingColumns('tl_content', $set);
        $this->connection->update('tl_content', $set, ['id' => $elementId]);
    }

    /** @var array<string,array<string,bool>> column name(lc) => isInteger */
    private array $columnCache = [];

    /**
     * Sanitise a column→value map for a write to $table:
     *   - drop columns the DB doesn't have (under-migrated install →
     *     "Unknown column … in SET"),
     *   - coerce '' to 0 for INTEGER columns (DCA checkboxes like useHomeDir are
     *     int-backed → strict SQL rejects an empty string).
     *
     * @param array<string,mixed> $set
     * @return array<string,mixed>
     */
    private function onlyExistingColumns(string $table, array $set): array
    {
        if (!isset($this->columnCache[$table])) {
            $cols = [];
            try {
                foreach ($this->connection->createSchemaManager()->listTableColumns($table) as $col) {
                    $type = $col->getType();
                    $isInt = $type instanceof \Doctrine\DBAL\Types\IntegerType
                        || $type instanceof \Doctrine\DBAL\Types\SmallIntType
                        || $type instanceof \Doctrine\DBAL\Types\BigIntType
                        || $type instanceof \Doctrine\DBAL\Types\BooleanType;
                    $cols[strtolower($col->getName())] = $isInt;
                }
            } catch (\Throwable) {
                $cols = []; // schema unavailable → don't filter (fail open)
            }
            $this->columnCache[$table] = $cols;
        }
        $known = $this->columnCache[$table];
        if ($known === []) {
            return $set;
        }
        $out = [];
        foreach ($set as $k => $v) {
            $lc = strtolower($k);
            if (!\array_key_exists($lc, $known)) {
                continue; // column not in this DB → skip
            }
            // Empty string into an integer column → 0 (strict SQL).
            if ($known[$lc] && ($v === '' || $v === null)) {
                $v = 0;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * Persist AI block instance values into draggo_data (whitelisted to the
     * BlockSpec's field names). protected/groups stay on real columns.
     *
     * @param array<string,mixed> $row
     * @param array<string,mixed> $values
     */
    private function saveBlockFields(int $elementId, array $row, array $values, int $tstamp): void
    {
        $spec = $this->blockTypes->find((string) ($row['draggo_blocktype'] ?? ''));
        $set = ['tstamp' => $tstamp, 'draggo_managed' => '1'];

        if (\array_key_exists('protected', $values)) {
            $set['protected'] = !empty($values['protected']) ? '1' : '0';
        }
        if (\array_key_exists('groups', $values)) {
            $ids = \is_array($values['groups']) ? $values['groups'] : [];
            $set['groups'] = serialize(array_values(array_map('intval', $ids)));
        }

        if ($spec !== null) {
            $data = [];
            foreach ($spec->fields as $f) {
                $name = $f['name'];
                if (!\array_key_exists($name, $values)) {
                    continue;
                }
                $v = $values[$name];
                $data[$name] = match ($f['type']) {
                    'bool'   => !empty($v) ? '1' : '',
                    'image'  => $this->safeFilePath((string) $v),
                    'images' => array_values(array_filter(array_map(fn ($p): string => $this->safeFilePath(trim((string) $p)), preg_split('/\r?\n/', (string) $v) ?: []))),
                    'list'   => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $v) ?: []), static fn (string $s): bool => $s !== '')),
                    // Repeatable {key,value} items (title+text / title+html / icon+url).
                    'pairs', 'accordion', 'iconlinks' => $this->cleanPairs($v),
                    'select' => $this->validSelect((string) $v, $f['options'] ?? []),
                    'number', 'range' => is_numeric($v) ? $v + 0 : 0,
                    // richtext keeps HTML (rendered safely as Markup on the FE).
                    default  => (string) $v,
                };
            }
            $set['draggo_data'] = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->connection->update('tl_content', $set, ['id' => $elementId]);
    }

    /**
     * Normalise a repeatable {key,value} field (pairs/accordion/iconlinks):
     * drop empty rows, keep only string key+value. (value may hold sanitized
     * HTML for accordion — that's authored by trusted backend users.)
     *
     * @return list<array{key:string,value:string}>
     */
    private function cleanPairs(mixed $v): array
    {
        $out = [];
        foreach (\is_array($v) ? $v : [] as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $k = (string) ($row['key'] ?? '');
            $val = (string) ($row['value'] ?? '');
            if ($k === '' && $val === '') {
                continue;
            }
            $out[] = ['key' => $k, 'value' => $val];
        }

        return $out;
    }

    /** Accept only a relative files/ path or http(s) URL (block image value). */
    private function safeFilePath(string $v): string
    {
        $v = trim($v);
        if ($v === '' || preg_match('#[\'"()\s]#', $v)) {
            return '';
        }

        return preg_match('#^(?:files/|https?://)#i', $v) === 1 ? $v : '';
    }

    /** @param list<array{0:string,1:string}> $options */
    private function validSelect(string $v, array $options): string
    {
        $allowed = array_map(static fn (array $o): string => (string) $o[0], $options);

        return ($allowed === [] || \in_array($v, $allowed, true)) ? $v : (string) ($allowed[0] ?? '');
    }

    /**
     * Which file kinds the picker should offer for a given element/field.
     * Returns a comma list of kinds (image|video|audio|document) or 'any'.
     */
    private function fileAccept(string $type, string $name): string
    {
        if ($type === 'image' || $type === 'gallery' || $name === 'draggo_ib_src' || $name === 'draggo_car_src' || $name === 'draggo_logo_src' || $name === 'draggo_hs_img' || $name === 'draggo_ga_images') {
            return 'image';
        }
        if ($type === 'player' && $name === 'playerSRC') {
            return 'video,audio';
        }

        return 'any';
    }

    /**
     * Generic [id, label] select options from a table (module/form/article).
     * $table is a fixed internal literal, never user input.
     *
     * @return list<array{0:string,1:string}>
     */
    private function dbOptions(string $table, string $labelCol): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative("SELECT id, {$labelCol} AS lbl FROM {$table} ORDER BY {$labelCol}");
        } catch (\Throwable) {
            return [['', '—']];
        }
        $opts = [['', '—']];
        foreach ($rows as $r) {
            $opts[] = [(string) $r['id'], (string) ($r['lbl'] ?: ('#' . $r['id']))];
        }

        return $opts;
    }

    /**
     * Published unit ids of a type (for the editor header/footer preview).
     *
     * @return list<int>
     */
    public function publishedUnitIds(string $type): array
    {
        try {
            return array_map('intval', $this->connection->fetchFirstColumn(
                "SELECT id FROM tl_draggo_unit WHERE type = :t AND published = '1' ORDER BY id",
                ['t' => $type],
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Whether any published unit of a type is set sticky (editor preview). */
    public function hasStickyUnit(string $type): bool
    {
        try {
            return (bool) $this->connection->fetchOne(
                "SELECT id FROM tl_draggo_unit WHERE type = :t AND published = '1' AND sticky = '1' LIMIT 1",
                ['t' => $type],
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /** Binary file UUID → files/ path (for the editor). */
    private function uuidToPath(mixed $uuid): string
    {
        if (empty($uuid)) {
            return '';
        }
        $this->framework->initialize();
        /** @var FilesModel $adapter */
        $adapter = $this->framework->getAdapter(FilesModel::class);
        $file = $adapter->findByUuid($uuid);

        return $file !== null ? (string) $file->path : '';
    }

    /** files/ path → binary file UUID (for storage), or null. */
    private function pathToUuid(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        $this->framework->initialize();
        /** @var FilesModel $adapter */
        $adapter = $this->framework->getAdapter(FilesModel::class);
        $file = $adapter->findByPath($path);

        // The editor's file picker can surface files that exist on disk but are
        // not yet registered in the DBAFS (tl_files) — those have no UUID, so a
        // native UUID field (singleSRC/multiSRC) would silently stay empty.
        // Auto-register the resource so any pickable file resolves.
        if ($file === null) {
            try {
                $dbafs = $this->framework->getAdapter(\Contao\Dbafs::class);
                $file = $dbafs->addResource($path);
            } catch (\Throwable) {
                $file = null;
            }
        }

        return $file !== null ? $file->uuid : null;
    }

    /**
     * Serialized binary-UUID array → list of files/ paths (for the editor).
     *
     * @return list<string>
     */
    private function uuidsToPaths(mixed $raw): array
    {
        $uuids = StringUtil::deserialize($raw, true);
        if ($uuids === []) {
            return [];
        }
        $this->framework->initialize();
        /** @var FilesModel $adapter */
        $adapter = $this->framework->getAdapter(FilesModel::class);

        $paths = [];
        foreach ($uuids as $uuid) {
            if (empty($uuid)) {
                continue;
            }
            $file = $adapter->findByUuid($uuid);
            if ($file !== null) {
                $paths[] = (string) $file->path;
            }
        }

        return $paths;
    }

    /**
     * Newline-separated files/ paths → list of binary UUIDs (for storage).
     *
     * @return list<string>
     */
    private function pathsToUuids(string $value): array
    {
        $uuids = [];
        foreach (explode("\n", $value) as $line) {
            $uuid = $this->pathToUuid(trim($line));
            if ($uuid !== null) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    /**
     * Serialized table rows → 2-D string grid (for the editor).
     *
     * @return list<list<string>>
     */
    private function decodeTable(mixed $raw): array
    {
        $rows = StringUtil::deserialize($raw, true);
        $out = [];
        foreach ($rows as $row) {
            $cells = \is_array($row) ? $row : [$row];
            $out[] = array_map(static fn ($c): string => (string) $c, array_values($cells));
        }

        return $out;
    }

    /**
     * Editor 2-D grid → clean list of equal-length string rows.
     *
     * @return list<list<string>>
     */
    private function encodeTable(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }
        $rows = [];
        foreach ($value as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $rows[] = array_map(static fn ($c): string => (string) $c, array_values($row));
        }

        return $rows;
    }

    /**
     * Serialized description list → list of {key,value} (for the editor).
     *
     * @return list<array{key:string,value:string}>
     */
    private function decodePairs(mixed $raw): array
    {
        $rows = StringUtil::deserialize($raw, true);
        $out = [];
        foreach ($rows as $k => $row) {
            if (\is_array($row)) {
                $out[] = ['key' => (string) ($row['key'] ?? ''), 'value' => (string) ($row['value'] ?? '')];
            } else {
                // Fallback: assoc {term: description}.
                $out[] = ['key' => (string) $k, 'value' => (string) $row];
            }
        }

        return $out;
    }

    /**
     * Card rows {key:title, value:text, img:files-path} for the tilt element.
     *
     * @return list<array{key:string,value:string,img:string}>
     */
    /**
     * Stored weekly schedule → 7 day-rows of {o,c} ranges for the editor widget
     * (index 0 = Monday; a closed day = empty list). Validated via OpenHours.
     *
     * @return list<list<array{o:string,c:string}>>
     */
    private function decodeOpenHours(mixed $raw): array
    {
        return $this->mapWeek(OpenHours::normalize(StringUtil::deserialize($raw, true)));
    }

    /**
     * Editor widget value → validated 7-day {o,c} schedule, or [] when nothing
     * valid is set (so the prop clears). Reuses the same OpenHours validation as
     * the frontend so editor + render agree.
     *
     * @return list<list<array{o:string,c:string}>>
     */
    private function encodeOpenHours(mixed $value): array
    {
        $week = OpenHours::normalize($value);

        return OpenHours::hasAny($week) ? $this->mapWeek($week) : [];
    }

    /** Normalised OpenHours week → list of 7 lists of {o,c}. */
    private function mapWeek(array $week): array
    {
        return array_map(
            static fn (array $day): array => array_map(static fn (array $r): array => ['o' => $r[2], 'c' => $r[3]], $day),
            $week,
        );
    }

    private function decodeCards(mixed $raw): array
    {
        $out = [];
        foreach (StringUtil::deserialize($raw, true) as $row) {
            if (\is_array($row)) {
                $out[] = [
                    'key'   => (string) ($row['key'] ?? ''),
                    'value' => (string) ($row['value'] ?? ''),
                    'img'   => (string) ($row['img'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * Editor card rows → list of {key,value,img}, empty rows dropped. img is
     * sanitised to a relative files/ path or http(s) URL (no breakout).
     *
     * @return list<array{key:string,value:string,img:string}>
     */
    private function encodeCards(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            $val = trim((string) ($row['value'] ?? ''));
            $img = $this->safeFilePath(trim((string) ($row['img'] ?? '')));
            if ($key !== '' || $val !== '' || $img !== '') {
                $out[] = ['key' => $key, 'value' => $val, 'img' => $img];
            }
        }

        return $out;
    }

    /**
     * Editor pair rows → list of {key,value}, empty rows dropped.
     *
     * @return list<array{key:string,value:string}>
     */
    private function encodePairs(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            $val = trim((string) ($row['value'] ?? ''));
            if ($key !== '' || $val !== '') {
                $out[] = ['key' => $key, 'value' => $val];
            }
        }

        return $out;
    }

    /**
     * Serialized Contao image size [width, height, mode] → {width,height,mode}.
     *
     * @return array{width:string,height:string,mode:string}
     */
    private function decodeSize(mixed $raw): array
    {
        $arr = \is_string($raw) && $raw !== '' ? StringUtil::deserialize($raw, true) : [];

        return [
            'width'  => (string) ($arr[0] ?? ''),
            'height' => (string) ($arr[1] ?? ''),
            'mode'   => (string) ($arr[2] ?? ''),
        ];
    }

    /**
     * {width,height,mode} → serialized [width, height, mode].
     *
     * @param array<string,mixed> $size
     */
    private function encodeSize(array $size): string
    {
        $w = (string) ($size['width'] ?? '');
        $h = (string) ($size['height'] ?? '');
        $mode = (string) ($size['mode'] ?? '');

        // Contao only resizes with a resize mode. If the user gave a dimension
        // but no mode, pick a sensible default so the image actually changes.
        if ($mode === '' && ($w !== '' || $h !== '')) {
            $mode = ($w !== '' && $h !== '') ? 'crop' : 'proportional';
        }

        return serialize([$w, $h, $mode]);
    }

    private function headlineUnit(mixed $raw): string
    {
        if (\is_string($raw) && $raw !== '') {
            $u = @unserialize($raw, ['allowed_classes' => false]);
            if (\is_array($u) && !empty($u['unit'])) {
                return (string) $u['unit'];
            }
        }

        return 'h2';
    }

    public function findRow(int $elementId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM tl_content WHERE id = :id',
            ['id' => $elementId],
        );

        return $row ? $this->mergeProps($row) : null;
    }

    /**
     * Structural draggo_* columns that stay real columns. Everything else
     * draggo_* is element content, consolidated into the draggo_props JSON.
     */
    private const PROP_KEEP = [
        'draggo_managed', 'draggo_layout', 'draggo_data', 'draggo_blocktype', 'draggo_container',
        'draggo_grid_preset', 'draggo_grid_custom', 'draggo_grid_tablet', 'draggo_grid_mobile', 'draggo_props',
    ];

    /**
     * Merge the consolidated draggo_props JSON back into a tl_content row so
     * downstream code can read `$row['draggo_xxx']` exactly as when each field
     * had its own column. A real (kept) column with a value is never overwritten.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mergeProps(array $row): array
    {
        $raw = $row['draggo_props'] ?? null;
        if (empty($raw)) {
            return $row;
        }
        $props = json_decode((string) $raw, true);
        if (!\is_array($props)) {
            return $row;
        }
        // props is the source of truth for content fields → it overrides any
        // (legacy, pre-drop) column value so reads stay consistent with writes.
        foreach ($props as $key => $value) {
            if (\is_string($key) && $key !== '') {
                $row[$key] = $value;
            }
        }

        return $row;
    }

    /** True when a column name is a Draggo element content field (→ JSON). */
    private function isPropField(string $key): bool
    {
        return str_starts_with($key, 'draggo_') && !\in_array($key, self::PROP_KEEP, true);
    }

    private function exists(int $elementId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT id FROM tl_content WHERE id = :id',
            ['id' => $elementId],
        );
    }

    private function computeSortingAfter(int $pid, ?int $afterId, string $ptable = 'tl_article'): int
    {
        if ($afterId === null) {
            $max = (int) $this->connection->fetchOne(
                'SELECT MAX(sorting) FROM tl_content WHERE pid = :pid AND ptable = :ptable',
                ['pid' => $pid, 'ptable' => $ptable],
            );

            return $max + self::SORT_STEP;
        }

        $current = (int) $this->connection->fetchOne(
            'SELECT sorting FROM tl_content WHERE id = :id',
            ['id' => $afterId],
        );

        $next = $this->connection->fetchOne(
            'SELECT MIN(sorting) FROM tl_content
             WHERE pid = :pid AND ptable = :ptable AND sorting > :sorting',
            ['pid' => $pid, 'ptable' => $ptable, 'sorting' => $current],
        );

        // Gap exists → place midway; otherwise append a full step.
        if ($next !== false && $next !== null) {
            return (int) (($current + (int) $next) / 2) ?: $current + 1;
        }

        return $current + self::SORT_STEP;
    }

    private function rowToDto(array $row): ContentElementDto
    {
        $layout = [];
        if (!empty($row['draggo_layout'])) {
            $decoded = json_decode((string) $row['draggo_layout'], true);
            if (\is_array($decoded)) {
                $layout = $decoded;
            }
        }

        $html = $this->renderHtml((int) $row['id'], (string) $row['type']);

        // Compile the element's own styles so the canvas preview matches the
        // frontend (typography, colour, spacing, border, …). Only for leaf
        // content elements (flat layout); grid wrappers have html === null.
        $styleCss = null;
        $linkColor = null;
        $scopedCss = null;
        $respCss = null;
        if ($html !== null && !isset($layout['row']) && !isset($layout['col'])) {
            $css = $this->compiler->previewCss($layout);
            $styleCss = $css !== '' ? $css : null;
            $scoped = $this->compiler->scoped('.draggo-el-' . (int) $row['id'], $layout);
            $scopedCss = $scoped !== '' ? $scoped : null;
            // Editor-canvas responsive preview (data-vw-keyed) so the chip shows
            // e.g. a smaller font on tablet/mobile — 1:1 with the frontend.
            $resp = $this->compiler->previewResponsive('.draggo-el-' . (int) $row['id'], $layout);
            $respCss = $resp !== '' ? $resp : null;
            if (!empty($layout['linkColor']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $layout['linkColor'])) {
                $linkColor = (string) $layout['linkColor'];
            }
        }

        return new ContentElementDto(
            id: (int) $row['id'],
            pid: (int) $row['pid'],
            type: (string) $row['type'],
            sorting: (int) $row['sorting'],
            headline: $this->extractHeadline($row['headline'] ?? ''),
            managed: ($row['draggo_managed'] ?? '') === '1',
            layout: $layout,
            gridPreset: (string) ($row['draggo_grid_preset'] ?? ''),
            gridCustom: (string) ($row['draggo_grid_custom'] ?? ''),
            gridTablet: (string) ($row['draggo_grid_tablet'] ?? ''),
            gridMobile: (string) ($row['draggo_grid_mobile'] ?? ''),
            container: (string) ($row['draggo_container'] ?? ''),
            blocktype: (string) ($row['draggo_blocktype'] ?? ''),
            html: $html,
            styleCss: $styleCss,
            linkColor: $linkColor,
            scopedCss: $scopedCss,
            respCss: $respCss,
            isWrapper: $this->isStructuralWrapper((string) $row['type']),
        );
    }

    /**
     * Render the REAL frontend HTML of a content element so the editor canvas
     * can show a live preview (like Elementor). Grid wrappers are structure,
     * not content, so they return null and are drawn by the editor itself.
     */
    private function renderHtml(int $id, string $type): ?string
    {
        // Wrappers are STRUCTURE, not content — Draggo's own grid wrappers and any
        // foreign wrapper (start/separator/stop incl. PCT autogrid) must not emit a
        // partial <div> fragment as a chip; the editor draws the structure instead.
        if (\in_array($type, ['draggo_row_start', 'draggo_col', 'draggo_row_stop'], true)
            || $this->isStructuralWrapper($type)
        ) {
            return null;
        }

        // Many frontend content-element controllers (Contao core + 3rd-party like
        // PCT) read the global active page ($GLOBALS['objPage']->id / ->language /
        // ->layout). In the backend editor route there is none → "read property id
        // on null" → the element renders empty. Provide the element's real page as
        // a temporary global context so they render like on the frontend; restore
        // it afterwards so nothing leaks into the rest of the request.
        $previousPage = $GLOBALS['objPage'] ?? null;
        // Rendering a frontend CE outside a real FE request throws non-fatal
        // warnings/notices (undefined model props, null $objPage, …) that the web
        // error handler escalates to fatals → the element would render blank in the
        // canvas. Swallow only warnings/notices/deprecations for the duration of
        // the preview render (restored right after); real fatals still bubble.
        set_error_handler(
            static fn (): bool => true,
            E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED,
        );
        $stack = null;
        $pushed = false;
        try {
            $this->framework->initialize();
            $this->withPageContext($id);

            // Render in FRONTEND scope. Backend-aware elements (Contao wrappers,
            // and especially pct_customelements / rsce) emit a "### CUSTOM CONTENT
            // ELEMENT ###" backend WILDCARD when Controller::isBackend() is true —
            // which it is on the editor route. Pushing a frontend-scope request so
            // the scope matcher reports FE makes them render their real output.
            $stack = \Contao\System::getContainer()->get('request_stack');
            $current = $stack->getCurrentRequest();
            $feRequest = $current ? $current->duplicate() : \Symfony\Component\HttpFoundation\Request::create('/');
            $feRequest->attributes->set('_scope', 'frontend');
            $stack->push($feRequest);
            $pushed = true;

            /** @var \Contao\Controller $controller */
            $controller = $this->framework->getAdapter(\Contao\Controller::class);
            $html = trim((string) $controller->getContentElement($id));

            return $html !== '' ? $html : null;
        } catch (\Throwable) {
            return null;
        } finally {
            if ($pushed && $stack !== null) {
                $stack->pop();
            }
            restore_error_handler();
            if ($previousPage === null) {
                unset($GLOBALS['objPage']);
            } else {
                $GLOBALS['objPage'] = $previousPage;
            }
        }
    }

    /** True for any registered wrapper type (start/separator/stop) — structure, not content. */
    private function isStructuralWrapper(string $type): bool
    {
        foreach (['start', 'separator', 'stop'] as $kind) {
            if (\in_array($type, (array) ($GLOBALS['TL_WRAPPERS'][$kind] ?? []), true)) {
                return true;
            }
        }

        return false;
    }

    /** @var array<int,\Contao\PageModel|null> pageId → details-loaded PageModel */
    private array $pageCtxCache = [];

    /**
     * Set $GLOBALS['objPage'] to the page the element lives on (article → page),
     * or the first published regular page as a fallback (units, orphans), so
     * frontend CE controllers have the page context they expect in the editor.
     */
    private function withPageContext(int $id): void
    {
        $pageId = (int) ($this->connection->fetchOne(
            "SELECT p.id FROM tl_content c
             JOIN tl_article a ON c.pid = a.id AND c.ptable = 'tl_article'
             JOIN tl_page p ON a.pid = p.id
             WHERE c.id = :id",
            ['id' => $id],
        ) ?: 0);

        if ($pageId === 0) {
            $pageId = (int) ($this->connection->fetchOne(
                "SELECT id FROM tl_page WHERE type = 'regular' AND published = '1' ORDER BY sorting",
            ) ?: 0);
        }
        if ($pageId === 0) {
            return;
        }

        if (!\array_key_exists($pageId, $this->pageCtxCache)) {
            $pageAdapter = $this->framework->getAdapter(\Contao\PageModel::class);
            $this->pageCtxCache[$pageId] = $pageAdapter->findWithDetails($pageId);
        }
        if ($this->pageCtxCache[$pageId] !== null) {
            $GLOBALS['objPage'] = $this->pageCtxCache[$pageId];
        }
    }

    /**
     * tl_content.headline is stored as a serialized {value, unit} array.
     * Return just the display value.
     */
    private function extractHeadline(mixed $raw): string
    {
        if (\is_string($raw) && $raw !== '') {
            $unserialized = @unserialize($raw, ['allowed_classes' => false]);
            if (\is_array($unserialized)) {
                return (string) ($unserialized['value'] ?? '');
            }

            return $raw;
        }

        return '';
    }
}
