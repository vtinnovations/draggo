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

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Makes the settings module behave like a single settings PAGE: ensures exactly
 * one tl_draggo_settings row exists and, when the module opens its list view,
 * jumps straight into editing that row — so the admin sees the form directly.
 */
final class SettingsSingletonListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    #[AsCallback(table: 'tl_draggo_settings', target: 'config.onload')]
    public function __invoke(?DataContainer $dc = null): void
    {
        $id = $this->connection->fetchOne('SELECT id FROM tl_draggo_settings ORDER BY id LIMIT 1');
        if ($id === false) {
            $this->connection->insert('tl_draggo_settings', ['tstamp' => time()]);
            $id = (int) $this->connection->lastInsertId();
        }

        // Already editing (or running a row action) → don't interfere.
        if (\in_array(Input::get('act'), ['edit', 'save'], true)) {
            return;
        }

        // List/overview view → go straight to the single record's edit form.
        // Build a clean URL via the router (addToUrl would HTML-escape the &).
        throw new RedirectResponseException($this->router->generate('contao_backend', [
            'do'  => 'draggo_settings',
            'act' => 'edit',
            'id'  => (int) $id,
        ]));
    }
}
