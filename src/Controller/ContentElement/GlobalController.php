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

namespace Vtinnovations\Draggo\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Unit\UnitContentRenderer;

/**
 * Global block (Tier 4): renders a reusable "section" unit inline. Unlike a
 * component clone, this is a LIVE LINK — editing the unit (Draggo → Einheiten →
 * Inhalt bearbeiten) updates every page that embeds it. Built on the existing,
 * tested unit subsystem (one source of truth, no instance-sync machinery).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_global')]
class GlobalController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    /** @var array<int,true> Guards against a unit embedding itself (infinite loop). */
    private static array $rendering = [];

    public function __construct(private readonly UnitContentRenderer $renderer)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $unitId = (int) ($model->draggo_global_unit ?? 0);
        [$idAttr, $cls] = $this->draggoScope($model);
        $open = '<div class="' . trim('draggo-global ' . $cls) . '"' . $idAttr . '>';

        if ($unitId <= 0) {
            return new Response($open . '<span class="draggo-rf-ph">[Globaler Block — keine Einheit gewählt]</span></div>');
        }

        if (isset(self::$rendering[$unitId])) {
            return new Response($open . '<span class="draggo-rf-ph">[Globaler Block #' . $unitId . ' — Selbstreferenz]</span></div>');
        }
        self::$rendering[$unitId] = true;
        try {
            $html = $this->renderer->render($unitId);
        } finally {
            unset(self::$rendering[$unitId]);
        }
        if ($html === '') {
            return new Response($open . '<span class="draggo-rf-ph">[Globaler Block #' . $unitId . ' — leer oder unveröffentlicht]</span></div>');
        }

        return new Response($open . $html . '</div>');
    }
}
