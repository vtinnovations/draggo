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

namespace Vtinnovations\Draggo\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\DataContainer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;

/**
 * Draggo's three backend modules (Units, AI element types, Settings) stay in
 * the navigation unconditionally — see contao/config/config.php — the same
 * "visible but locked" presentation Migrator and Guardian use for their own
 * dashboards, rather than hiding the whole product until it is activated.
 *
 * This supplies the "locked" half: opening any of the three while unlicensed
 * replaces the normal list/edit view with the same standalone notice page
 * {@see \Vtinnovations\Draggo\Controller\EditorController::licenseNotice()}
 * already shows for the visual editor, so the product speaks with one gate
 * screen everywhere rather than two different ones.
 *
 * Presentation only: these three tables hold no sensitive capability by
 * themselves (unit / blocktype metadata, AI settings) — the actual editor,
 * its JSON API and every content element are gated at their own boundary in
 * RequestGuard and EditorController, independently of this listener.
 */
final class EditionLockListener
{
    public function __construct(
        private readonly EditionResolver $edition,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    #[AsCallback(table: 'tl_draggo_unit', target: 'config.onload')]
    #[AsCallback(table: 'tl_draggo_blocktype', target: 'config.onload')]
    #[AsCallback(table: 'tl_draggo_settings', target: 'config.onload')]
    public function __invoke(?DataContainer $dc = null): void
    {
        if ($this->edition->profile()->allows(EditionProfile::CAP_EDITOR)) {
            return;
        }

        throw new ResponseException($this->gate());
    }

    private function gate(): Response
    {
        $settingsUrl = $this->router->generate('contao_backend', ['do' => 'settings']);
        $backUrl = $this->router->generate('contao_backend');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Draggo — Lizenz erforderlich</title>
<style>body{font:16px/1.6 system-ui,sans-serif;background:#0f1115;color:#e6e9ef;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.b{max-width:32rem;padding:2rem;background:#171a21;border:1px solid #2a2f3a;border-radius:12px}
h1{margin:0 0 .5rem;font-size:1.25rem}a{color:#7c3aed}
.back{display:inline-block;margin-top:1.25rem;font-size:.9rem;opacity:.75}</style>
</head><body><div class="b">
<h1>Draggo ist nicht lizenziert</h1>
<p>Dieser Bereich ist gesperrt, bis eine gültige Lizenz hinterlegt ist. Trage einen Lizenzschlüssel unter <strong>Einstellungen → Draggo Licence management</strong> ein.</p>
<p><a href="{$this->esc($settingsUrl)}">Zu den Einstellungen →</a></p>
<a class="back" href="{$this->esc($backUrl)}">← Zurück zur Übersicht</a>
</div></body></html>
HTML;

        return new Response($html, Response::HTTP_FORBIDDEN);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
