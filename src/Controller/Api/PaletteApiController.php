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

namespace Vtinnovations\Draggo\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Editor\ElementRegistry;
use Vtinnovations\Draggo\Exception\AccessDeniedException;
use Vtinnovations\Draggo\Grid\GridPresets;
use Vtinnovations\Draggo\Security\RequestGuard;
use Vtinnovations\Draggo\Settings\EditionProfile;

/**
 * Serves the editor's element palette: the REAL registered CTE types grouped
 * for the UI. Block types (Phase 3) are appended here once they exist; until
 * then the list reflects only Contao core/3rd-party elements.
 */
final class PaletteApiController
{
    public function __construct(
        private readonly ElementRegistry $registry,
        private readonly RequestGuard $guard,
        private readonly ContaoFramework $framework,
        private readonly \Vtinnovations\Draggo\Block\BlockTypeStore $blockTypes,
        private readonly \Vtinnovations\Draggo\Settings\EditionResolver $edition,
    ) {
    }

    /**
     * preset key => true when this installation may NOT use it, so the editor
     * can render the input locked. 'custom' (free-form column widths) is
     * included so that input can be locked too.
     *
     * @return array<string, bool>
     */
    private function lockedStructures(): array
    {
        $profile = $this->edition->profile();
        $out = [];

        foreach (array_keys(GridPresets::labels()) as $key) {
            $key = (string) $key;
            $out[$key] = !$profile->allowsStructure($key, 'custom' === $key);
        }

        return $out;
    }

    /**
     * Flag every palette entry the current licence tier may not place, so the
     * editor can show it locked instead of letting the user build with an
     * element that would be rejected on save (or silently not render).
     *
     * @param array<string, mixed> $groups
     * @return array<string, mixed>
     */
    private function markLocked(array $groups): array
    {
        $profile = $this->edition->profile();

        foreach ($groups as $group => $items) {
            if (!\is_array($items)) {
                continue;
            }
            foreach ($items as $i => $item) {
                if (\is_array($item) && isset($item['type'])) {
                    $groups[$group][$i]['locked'] = !$profile->allowsElement((string) $item['type']);
                }
            }
        }

        return $groups;
    }

    public function list(): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
        } catch (AccessDeniedException $e) {
            return new JsonResponse(
                ['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]],
                Response::HTTP_FORBIDDEN,
            );
        }

        return new JsonResponse([
            // Drives what the editor UI offers (locked elements, hidden
            // globals/library panels). Presentation only — every one of these
            // is independently enforced server-side, and nothing here reveals
            // the key, the package or the licensed domains.
            'license' => [
                'state' => $this->edition->profile()->active ? 'licensed' : 'unlicensed',
                'globals' => $this->edition->profile()->allows(EditionProfile::CAP_GLOBALS),
                'library' => $this->edition->profile()->allows(EditionProfile::CAP_LIBRARY),
                'ai' => $this->edition->profile()->allows(EditionProfile::CAP_AI),
            ],
            'groups'       => $this->markLocked($this->registry->palette()),
            'structures'   => GridPresets::labels(),  // preset key => label
            // Column layouts the tier may place (preset key => bool) + whether
            // prebuilt sections are available at all.
            'structuresLocked' => $this->lockedStructures(),
            'presetWidths' => GridPresets::widths(),   // preset key => [col widths]
            'templates'    => \Vtinnovations\Draggo\Template\SectionTemplates::catalog(), // prebuilt sections
            'wrapperTriples' => \Vtinnovations\Draggo\Grid\ForeignGrids::editorTriples(), // grid systems the editor recognises

            'blockTypes'   => $this->blockTypes->all(), // AI-generated element types
            'googleFonts'  => \Vtinnovations\Draggo\Font\GoogleFonts::FAMILIES,
            'dynamicTags'  => \Vtinnovations\Draggo\Dynamic\DynamicTags::catalog(),
            'icons'        => \Vtinnovations\Draggo\Icon\IconLibrary::pickerList(),
            'faIcons'      => \Vtinnovations\Draggo\Icon\FaIcons::pickerList(),
        ]);
    }
}
