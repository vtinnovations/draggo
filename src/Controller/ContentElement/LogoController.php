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
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FilesModel;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Site Logo (Tier 4): an image linked to the homepage (current root) or a custom
 * URL. The header building block; size via the universal Style tab.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_logo')]
class LogoController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly InsertTagParser $insertTags,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $this->framework->initialize();
        /** @var FilesModel $files */
        $files = $this->framework->getAdapter(FilesModel::class);
        $file = !empty($model->draggo_logo_src) ? $files->findByUuid($model->draggo_logo_src) : null;
        if ($file === null) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alt = $e(trim((string) ($model->draggo_logo_alt ?? '')));

        $href = $this->resolveHref($model);
        $img = '<img src="/' . $e((string) $file->path) . '" alt="' . $alt . '" class="draggo-logo-img">';

        [$idAttr, $cls] = $this->draggoScope($model);
        $inner = $href !== '' ? '<a href="' . $e($href) . '">' . $img . '</a>' : $img;

        return new Response('<span class="' . trim('draggo-logo ' . $cls) . '"' . $idAttr . '>' . $inner . '</span>');
    }

    private function resolveHref(ContentModel $model): string
    {
        $mode = (string) ($model->draggo_logo_link ?? 'home');
        if ($mode === 'none') {
            return '';
        }
        if ($mode === 'custom') {
            $url = trim((string) ($model->draggo_logo_url ?? ''));

            return $url !== '' ? $this->insertTags->replaceInline($url) : '';
        }

        // Home = the current root's first published page.
        global $objPage;
        if ($objPage === null) {
            return '/';
        }
        /** @var PageModel $pageAdapter */
        $pageAdapter = $this->framework->getAdapter(PageModel::class);
        $home = $pageAdapter->findFirstPublishedByPid((int) $objPage->rootId);
        if ($home === null) {
            return '/';
        }
        try {
            return $home->getFrontendUrl();
        } catch (\Throwable) {
            return '/';
        }
    }
}
