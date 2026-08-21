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

namespace Vtinnovations\Draggo\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Vtinnovations\Draggo\Font\GoogleFonts;
use Vtinnovations\Draggo\Token\DefaultsStore;

/**
 * Loads only the Google Fonts actually used on the page (page elements, header/
 * footer units, global defaults, font tokens) — one stylesheet link in the head.
 */
#[AsHook('generatePage')]
final class GoogleFontsListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DefaultsStore $defaults,
    ) {
    }

    public function __invoke(PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular): void
    {
        $families = $this->collect((int) $pageModel->id);
        $link = GoogleFonts::link($families);
        if ($link !== null) {
            $GLOBALS['TL_HEAD'][] = $link;
        }
    }

    /** @return list<string> */
    public function collect(int $pageId): array
    {
        $blob = '';
        $values = [];

        try {
            $articleIds = array_map('intval', $this->connection->fetchFirstColumn(
                'SELECT id FROM tl_article WHERE pid = :pid',
                ['pid' => $pageId],
            ));
            $blob .= implode("\n", $this->connection->fetchFirstColumn(
                'SELECT draggo_layout FROM tl_article WHERE pid = :pid',
                ['pid' => $pageId],
            ));
            if ($articleIds !== []) {
                $blob .= implode("\n", $this->connection->fetchFirstColumn(
                    "SELECT draggo_layout FROM tl_content WHERE ptable = 'tl_article' AND pid IN (:ids)",
                    ['ids' => $articleIds],
                    ['ids' => ArrayParameterType::INTEGER],
                ));
            }
            $blob .= implode("\n", $this->connection->fetchFirstColumn(
                "SELECT draggo_layout FROM tl_draggo_unit WHERE published = '1'",
            ));
            $blob .= implode("\n", $this->connection->fetchFirstColumn(
                "SELECT draggo_layout FROM tl_content WHERE ptable = 'tl_draggo_unit'",
            ));
            $values = array_merge($values, $this->connection->fetchFirstColumn(
                "SELECT value FROM tl_draggo_token WHERE type = 'font'",
            ));
        } catch (\Throwable) {
            // tables/columns not migrated yet
        }

        $values = array_merge($values, $this->defaultFontValues());

        return array_values(array_unique(array_merge(GoogleFonts::fromBlob($blob), GoogleFonts::extract($values))));
    }

    /** Google families used inside a single unit (for the editor preview). */
    public function collectForUnit(int $unitId): array
    {
        $blob = '';
        $values = [];
        try {
            $blob .= (string) $this->connection->fetchOne('SELECT draggo_layout FROM tl_draggo_unit WHERE id = :id', ['id' => $unitId]);
            $blob .= implode("\n", $this->connection->fetchFirstColumn(
                "SELECT draggo_layout FROM tl_content WHERE ptable = 'tl_draggo_unit' AND pid = :id",
                ['id' => $unitId],
            ));
            $values = $this->connection->fetchFirstColumn("SELECT value FROM tl_draggo_token WHERE type = 'font'");
        } catch (\Throwable) {
        }
        $values = array_merge($values, $this->defaultFontValues());

        return array_values(array_unique(array_merge(GoogleFonts::fromBlob($blob), GoogleFonts::extract($values))));
    }

    /** @return list<string> */
    private function defaultFontValues(): array
    {
        $d = $this->defaults->get();
        $out = [];
        foreach (['body', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $k) {
            if (\is_array($d[$k] ?? null) && isset($d[$k]['fontFamily'])) {
                $out[] = (string) $d[$k]['fontFamily'];
            }
        }

        return $out;
    }
}
