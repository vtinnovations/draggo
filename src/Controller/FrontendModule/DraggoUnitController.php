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

namespace Vtinnovations\Draggo\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\ModuleModel;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;
use Vtinnovations\Draggo\Unit\UnitContentRenderer;

/**
 * Frontend module that renders a global Draggo unit. Assign it to a layout
 * section (e.g. header / footer in tl_layout) to make a unit appear globally
 * across all pages using that layout.
 */
#[AsFrontendModule(category: 'draggo', type: 'draggo_unit', template: 'mod_draggo_unit')]
class DraggoUnitController extends AbstractFrontendModuleController
{
    public function __construct(
        private readonly UnitContentRenderer $renderer,
        private readonly EditionResolver $edition,
    ) {
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        // Units are a paid feature; an expired licence keeps rendering them.
        if (!$this->edition->profile()->allows(EditionProfile::CAP_LIBRARY)) {
            return new Response('');
        }

        // Return the rendered unit directly — avoids any module-template
        // resolution issue and keeps our own .draggo-unit wrapper.
        return new Response($this->renderer->render((int) $model->draggo_unit));
    }
}
