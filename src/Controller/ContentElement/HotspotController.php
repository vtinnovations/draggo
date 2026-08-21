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
 * Image hotspots (Tier 5): an image with positioned markers that reveal a
 * tooltip on hover/focus. Each marker = "x,y" (percent) → tooltip text.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_hotspot')]
class HotspotController extends AbstractDraggoElementController
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
        $file = !empty($model->draggo_hs_img) ? $files->findByUuid($model->draggo_hs_img) : null;
        if ($file === null) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $points = '';
        $i = 0;
        foreach (StringUtil::deserialize($model->draggo_hs_points, true) as $row) {
            $coord = (string) ($row['key'] ?? '');
            $tip = trim((string) ($row['value'] ?? ''));
            if (!preg_match('/^\s*(\d{1,3}(?:\.\d+)?)\s*,\s*(\d{1,3}(?:\.\d+)?)\s*$/', $coord, $m)) {
                continue;
            }
            $x = min(100, max(0, (float) $m[1]));
            $y = min(100, max(0, (float) $m[2]));
            ++$i;
            $points .= '<button type="button" class="draggo-hs-point" style="left:' . $x . '%;top:' . $y . '%" aria-label="' . $e($tip) . '">'
                . '<span class="draggo-hs-dot">' . $i . '</span>'
                . ($tip !== '' ? '<span class="draggo-hs-tip">' . $e($tip) . '</span>' : '')
                . '</button>';
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-hotspot ' . $cls) . '"' . $idAttr . '>'
            . '<img class="draggo-hs-img" src="/' . $e((string) $file->path) . '" alt="">'
            . $points
            . '</div>',
        );
    }
}
