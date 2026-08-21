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
 * Renders the "Edit content with Draggo" row operation in the unit list,
 * linking to the unit-scoped editor.
 */
final class UnitOperationListener
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator, private readonly EditionResolver $edition)
    {
    }

    #[AsCallback(table: 'tl_draggo_unit', target: 'list.operations.draggo.button')]
    public function __invoke(
        array $row,
        ?string $href,
        string $label,
        string $title,
        ?string $icon,
        string $attributes,
    ): string {
        // Unlicensed: no editor entry point on units either.
        if (!$this->edition->profile()->allows(EditionProfile::CAP_EDITOR)) {
            return '';
        }

        try {
            $url = $this->urlGenerator->generate('draggo_editor_unit', ['unit' => (int) $row['id']]);
        } catch (\Throwable) {
            return '';
        }

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $attributes,
            Image::getHtml($icon ?: 'bundles/draggo/icon.svg', $label),
        );
    }
}
