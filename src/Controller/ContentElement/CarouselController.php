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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FilesModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Image carousel (Tier 2 — the shared carousel core). Slides come from a
 * multi-image selection; the runtime (draggo-frontend.js) handles sliding,
 * arrows, dots and autoplay.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_carousel')]
class CarouselController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $this->framework->initialize();
        /** @var FilesModel $files */
        $files = $this->framework->getAdapter(FilesModel::class);

        $slides = '';
        foreach (StringUtil::deserialize($model->draggo_car_src, true) as $uuid) {
            if (empty($uuid)) {
                continue;
            }
            $f = $files->findByUuid($uuid);
            if ($f === null) {
                continue;
            }
            $src = htmlspecialchars((string) $f->path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $slides .= '<div class="draggo-car-slide"><img src="/' . $src . '" alt=""></div>';
        }
        if ($slides === '') {
            return new Response('');
        }

        $per = (int) ($model->draggo_car_per ?? 1);
        $per = ($per >= 1 && $per <= 6) ? $per : 1;
        $auto = (($model->draggo_car_auto ?? '') === '1') ? (int) (($model->draggo_car_speed ?? 4) ?: 4) * 1000 : 0;
        $arrows = ($model->draggo_car_arrows ?? '') === '1';
        $dots = ($model->draggo_car_dots ?? '') === '1';
        $fade = ($model->draggo_car_fade ?? '') === '1' ? '1' : '0';
        $pause = ($model->draggo_car_pause ?? '') === '1' ? '1' : '0';

        [$idAttr, $cls] = $this->draggoScope($model);
        $carCls = trim('draggo-car ' . ($fade === '1' ? 'draggo-car--fade ' : '') . $cls);
        $html = '<div class="' . $carCls . '"' . $idAttr . ' data-per="' . $per . '" data-auto="' . $auto . '" data-fade="' . $fade . '" data-pause="' . $pause . '">'
            . '<div class="draggo-car-viewport"><div class="draggo-car-track">' . $slides . '</div></div>'
            . ($arrows ? '<button type="button" class="draggo-car-prev" aria-label="Zurück">‹</button><button type="button" class="draggo-car-next" aria-label="Weiter">›</button>' : '')
            . ($dots ? '<div class="draggo-car-dots"></div>' : '')
            . '</div>';

        return new Response($html);
    }
}
