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

namespace Vtinnovations\Draggo\Loop;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Doctrine\DBAL\Connection;

/**
 * Query layer for the Loop grid (Roadmap Tier 3). Wraps Contao models/DBAL so
 * the element stays a thin renderer. Currently: child pages of a start page.
 * Add news/events/own sources here as further methods.
 */
final class LoopQuery
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Published, non-hidden child pages of $parentId as renderable items.
     *
     * @return list<array{title:string,teaser:string,href:string,image:string}>
     */
    public function childPages(int $parentId, int $limit = 0, string $order = 'default'): array
    {
        if ($parentId <= 0) {
            return [];
        }
        $this->framework->initialize();

        try {
            $orderBy = $order === 'alpha' ? 'title ASC' : ($order === 'oldest' ? 'sorting DESC' : 'sorting');
            $sql = "SELECT id FROM tl_page WHERE pid = :pid AND published = '1' AND (hide = '' OR hide IS NULL)
                    AND type NOT IN ('root','error_401','error_403','error_404') ORDER BY {$orderBy}";
            if ($limit > 0) {
                $sql .= ' LIMIT ' . $limit;
            }
            $ids = array_map('intval', $this->connection->fetchFirstColumn($sql, ['pid' => $parentId]));
        } catch (\Throwable) {
            return [];
        }

        /** @var PageModel $pageAdapter */
        $pageAdapter = $this->framework->getAdapter(PageModel::class);

        $out = [];
        foreach ($ids as $id) {
            $page = $pageAdapter->findById($id);
            if ($page === null) {
                continue;
            }
            try {
                $href = $page->getFrontendUrl();
            } catch (\Throwable) {
                $href = '#';
            }
            $out[] = [
                'title'  => (string) ($page->title ?: $page->alias),
                'teaser' => (string) ($page->description ?? ''),
                'href'   => $href,
                'image'  => '',
            ];
        }

        return $out;
    }

    /**
     * Published news of an archive (only if the contao/news-bundle is present).
     *
     * @return list<array{title:string,teaser:string,href:string,image:string}>
     */
    public function news(int $archiveId, int $limit = 0, bool $featuredOnly = false, string $order = 'default', int $categoryId = 0): array
    {
        if ($archiveId <= 0 || !class_exists(\Contao\NewsModel::class) || !class_exists(\Contao\News::class)) {
            return [];
        }
        $this->framework->initialize();

        try {
            $now = time();
            $orderBy = $order === 'alpha' ? 'headline ASC' : ($order === 'oldest' ? 'date ASC' : 'date DESC');
            $params = ['a' => $archiveId, 'n' => $now];
            $where = "pid = :a AND published = '1' AND (start = '' OR start <= :n) AND (stop = '' OR stop > :n)";
            if ($featuredOnly) {
                $where .= " AND featured = '1'";
            }
            // Optional taxonomy filter (codefog/contao-news_categories). Guarded
            // by a subquery against its relation table; absent table → caught.
            if ($categoryId > 0) {
                $where .= ' AND id IN (SELECT news_id FROM tl_news_categories WHERE category_id = :cat)';
                $params['cat'] = $categoryId;
            }
            $sql = "SELECT id FROM tl_news WHERE {$where} ORDER BY {$orderBy}";
            if ($limit > 0) {
                $sql .= ' LIMIT ' . $limit;
            }
            $ids = array_map('intval', $this->connection->fetchFirstColumn($sql, $params));
        } catch (\Throwable) {
            return [];
        }

        $newsAdapter = $this->framework->getAdapter(\Contao\NewsModel::class);
        $newsCtrl = $this->framework->getAdapter(\Contao\News::class);
        $files = $this->framework->getAdapter(\Contao\FilesModel::class);

        $out = [];
        foreach ($ids as $id) {
            $n = $newsAdapter->findByPk($id);
            if ($n === null) {
                continue;
            }
            try {
                $href = $newsCtrl->generateNewsUrl($n);
            } catch (\Throwable) {
                $href = '#';
            }
            $img = '';
            if (!empty($n->addImage) && !empty($n->singleSRC)) {
                $f = $files->findByUuid($n->singleSRC);
                if ($f !== null) {
                    $img = (string) $f->path;
                }
            }
            $out[] = [
                'title'  => (string) $n->headline,
                'teaser' => trim(strip_tags((string) $n->teaser)),
                'href'   => $href,
                'image'  => $img,
            ];
        }

        return $out;
    }

    /**
     * Published events of a calendar (only if the contao/calendar-bundle is
     * present). Upcoming first by start time.
     *
     * @return list<array{title:string,teaser:string,href:string,image:string}>
     */
    public function events(int $calendarId, int $limit = 0, string $order = 'default'): array
    {
        if ($calendarId <= 0 || !class_exists(\Contao\CalendarEventsModel::class) || !class_exists(\Contao\Events::class)) {
            return [];
        }
        $this->framework->initialize();

        try {
            $now = time();
            // Events read chronologically by default; 'oldest' is a no-op vs.
            // default for a time line, so only alpha diverges.
            $orderBy = $order === 'alpha' ? 'title ASC' : 'startTime ASC';
            $sql = "SELECT id FROM tl_calendar_events WHERE pid = :c AND published = '1'
                    AND (start = '' OR start <= :n) AND (stop = '' OR stop > :n) ORDER BY {$orderBy}";
            if ($limit > 0) {
                $sql .= ' LIMIT ' . $limit;
            }
            $ids = array_map('intval', $this->connection->fetchFirstColumn($sql, ['c' => $calendarId, 'n' => $now]));
        } catch (\Throwable) {
            return [];
        }

        $evAdapter = $this->framework->getAdapter(\Contao\CalendarEventsModel::class);
        $evCtrl = $this->framework->getAdapter(\Contao\Events::class);
        $files = $this->framework->getAdapter(\Contao\FilesModel::class);

        $out = [];
        foreach ($ids as $id) {
            $ev = $evAdapter->findByPk($id);
            if ($ev === null) {
                continue;
            }
            try {
                $href = $evCtrl->generateEventUrl($ev);
            } catch (\Throwable) {
                $href = '#';
            }
            $img = '';
            if (!empty($ev->addImage) && !empty($ev->singleSRC)) {
                $f = $files->findByUuid($ev->singleSRC);
                if ($f !== null) {
                    $img = (string) $f->path;
                }
            }
            $out[] = [
                'title'  => (string) $ev->title,
                'teaser' => trim(strip_tags((string) $ev->teaser)),
                'href'   => $href,
                'image'  => $img,
            ];
        }

        return $out;
    }
}
