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
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before/After (interactive): two images with a draggable divider to compare
 * (e.g. retouch before/after). NOT scroll-driven — a self-contained inline
 * script wires a range slider to a clip on the "before" layer (pointer + keyboard
 * accessible). Styles live in draggo-scroll.css (.draggo-ba).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_beforeafter')]
class BeforeAfterController extends AbstractDraggoElementController
{
    use ScopedElementTrait;
    use FilePathTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $before = $this->fileUrl($this->framework, $model->draggo_ba_before ?? null);
        $after = $this->fileUrl($this->framework, $model->draggo_ba_after ?? null);
        if ($before === '' || $after === '') {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lblB = $e(trim((string) ($model->draggo_ba_lblbefore ?? 'Vorher')));
        $lblA = $e(trim((string) ($model->draggo_ba_lblafter ?? 'Nachher')));

        [$idAttr, $cls] = $this->draggoScope($model);
        $scope = '.draggo-el-' . (int) $model->id;

        // Self-contained init: range input drives --ba-pos on the wrapper. Pure
        // CSS does the clipping; this just reflects the slider value as a custom
        // property (pointer + keyboard accessible via the native range input).
        $script = '<script>(function(){var el=document.querySelector(' . json_encode($scope, JSON_UNESCAPED_SLASHES) . ');'
            . 'if(!el||el.__baInit)return;el.__baInit=1;var r=el.querySelector(".draggo-ba-range");'
            . 'function u(){el.style.setProperty("--ba-pos",r.value+"%");}r.addEventListener("input",u);u();})();</script>';

        return new Response(
            '<div class="' . trim('draggo-ba ' . $cls) . '"' . $idAttr . ' style="--ba-pos:50%">'
            . '<div class="draggo-ba-img draggo-ba-after"><img src="' . $e($after) . '" alt="' . $lblA . '" loading="lazy"><span class="draggo-ba-lbl draggo-ba-lbl-a">' . $lblA . '</span></div>'
            . '<div class="draggo-ba-img draggo-ba-before"><img src="' . $e($before) . '" alt="' . $lblB . '" loading="lazy"><span class="draggo-ba-lbl draggo-ba-lbl-b">' . $lblB . '</span></div>'
            . '<div class="draggo-ba-divider" aria-hidden="true"><span class="draggo-ba-handle"></span></div>'
            . '<input type="range" min="0" max="100" value="50" class="draggo-ba-range" aria-label="Vorher/Nachher Vergleich">'
            . '</div>' . $script,
        );
    }
}
