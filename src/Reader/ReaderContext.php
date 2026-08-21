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

namespace Vtinnovations\Draggo\Reader;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;

/**
 * Resolves the "current" reader item (news or event) from the request alias, so
 * Reader-Field elements can render its data on a news/event reader page. Pure
 * Contao models, all addon classes guarded — absent bundle → null, never fatal.
 *
 * @phpstan-type Item array{0:string, 1:object}  // [type, model]
 */
final class ReaderContext
{
    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    /**
     * @param string $source 'auto' | 'news' | 'event'
     * @return array{0:string,1:object}|null  [type, model] or null
     */
    public function current(string $source): ?array
    {
        $this->framework->initialize();
        /** @var Input $input */
        $input = $this->framework->getAdapter(Input::class);
        $alias = (string) ($input->get('auto_item') ?: $input->get('items') ?: $input->get('events') ?: '');
        if ($alias === '') {
            return null;
        }

        if ($source !== 'event' && class_exists(\Contao\NewsModel::class)) {
            $news = $this->framework->getAdapter(\Contao\NewsModel::class)->findByIdOrAlias($alias);
            if ($news !== null) {
                return ['news', $news];
            }
        }
        if ($source !== 'news' && class_exists(\Contao\CalendarEventsModel::class)) {
            $event = $this->framework->getAdapter(\Contao\CalendarEventsModel::class)->findByIdOrAlias($alias);
            if ($event !== null) {
                return ['event', $event];
            }
        }

        return null;
    }

    /** Resolve a backend user's display name (news/event author), or ''. */
    public function authorName(mixed $userId): string
    {
        $id = (int) $userId;
        if ($id <= 0) {
            return '';
        }
        $user = $this->framework->getAdapter(\Contao\UserModel::class)->findByPk($id);

        return $user !== null ? (string) $user->name : '';
    }
}
