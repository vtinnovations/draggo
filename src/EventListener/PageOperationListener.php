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
use Contao\Image;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;

/**
 * Renders the "Edit with Draggo" row operation in the page tree (tl_page).
 * Draggo is page-scoped, so the entry lives under "Seiten", not "Artikel".
 *
 * The operation itself is declared in contao/dca/tl_page.php; this callback
 * builds the link to the page-scoped editor for the given row.
 */
final class PageOperationListener
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EditionResolver $edition,
    ) {
    }

    #[AsCallback(table: 'tl_page', target: 'list.operations.draggo.button')]
    public function __invoke(
        array $row,
        ?string $href,
        string $label,
        string $title,
        ?string $icon,
        string $attributes,
    ): string {
        // Unlicensed: no "edit with Draggo" entry point in the page tree.
        if (!$this->edition->profile()->allows(EditionProfile::CAP_EDITOR)) {
            return '';
        }

        $url = $this->urlGenerator->generate('draggo_editor', ['page' => (int) $row['id']]);

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $attributes,
            Image::getHtml($icon ?: 'bundles/draggo/icon.svg', $label),
        );
    }
}
