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
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Breadcrumb (Tier 4): the page trail from the website root to the current page.
 * Built by walking pid upward via PageModel; the current page is unlinked.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_breadcrumb')]
class BreadcrumbController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        global $objPage;
        [$idAttr, $cls] = $this->draggoScope($model);
        $open = '<nav class="' . trim('draggo-breadcrumb ' . $cls) . '"' . $idAttr . ' aria-label="Breadcrumb"><ol>';

        if ($objPage === null) {
            return new Response($open . '<li>[Breadcrumb]</li></ol></nav>');
        }
        $this->framework->initialize();
        /** @var PageModel $pageAdapter */
        $pageAdapter = $this->framework->getAdapter(PageModel::class);

        $chain = [];
        $pid = (int) $objPage->id;
        $guard = 0;
        while ($pid > 0 && $guard++ < 50) {
            $pg = $pageAdapter->findByPk($pid);
            if ($pg === null || $pg->type === 'root') {
                break;
            }
            $chain[] = $pg;
            $pid = (int) $pg->pid;
        }
        $chain = array_reverse($chain);
        if ($chain === []) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sep = (string) ($model->draggo_bc_sep ?? '');
        $sep = $sep !== '' ? $sep : '›';
        $sepLi = '<li class="draggo-bc-sep" aria-hidden="true">' . $e($sep) . '</li>';

        $items = '';
        $last = \count($chain) - 1;
        foreach ($chain as $i => $pg) {
            if ($i > 0) {
                $items .= $sepLi;
            }
            $label = $e((string) ($pg->title ?: $pg->alias));
            if ($i === $last) {
                $items .= '<li class="draggo-bc-item draggo-bc-active"><span>' . $label . '</span></li>';
                continue;
            }
            $href = '#';
            if (\in_array($pg->type, ['regular', 'forward', 'redirect'], true)) {
                try {
                    $href = $e($pg->getFrontendUrl());
                } catch (\Throwable) {
                    $href = '#';
                }
            }
            $items .= '<li class="draggo-bc-item"><a href="' . $href . '">' . $label . '</a></li>';
        }

        return new Response($open . $items . '</ol></nav>');
    }
}
