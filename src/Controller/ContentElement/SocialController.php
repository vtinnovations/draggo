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
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Icon\IconLibrary;

/**
 * Social icons (Tier 2): rows of "icon-key | url". The icon key is any library
 * key (facebook, instagram, linkedin, youtube, mail, phone …). Renders icon links.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_social')]
class SocialController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $rows = StringUtil::deserialize($model->draggo_social_items, true);
        $links = '';
        foreach ($rows as $row) {
            // Pass the RAW key to render() — it handles FA classes (with spaces)
            // and built-in SVG keys itself. Pre-sanitizing here would strip the
            // space in "fab fa-facebook" → broken icon.
            $key = trim((string) ($row['key'] ?? ''));
            $url = trim((string) ($row['value'] ?? ''));
            $svg = IconLibrary::render($key);
            if ($svg === '' || $url === '') {
                continue;
            }
            $href = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $links .= '<a class="draggo-social-link" href="' . $href . '" aria-label="' . htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $svg . '</a>';
        }
        if ($links === '') {
            return new Response('');
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<div class="' . trim('draggo-social ' . $cls) . '"' . $idAttr . '>' . $links . '</div>');
    }
}
