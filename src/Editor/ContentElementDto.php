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

namespace Vtinnovations\Draggo\Editor;

/**
 * Editor-facing view of one tl_content row. Carries only what the frontend
 * editor needs — never the full DB record. The ContentSynchronizer maps
 * between this DTO and tl_content.
 */
final class ContentElementDto implements \JsonSerializable
{
    /**
     * @param array<string,mixed> $layout decoded draggo_layout JSON (Phase 2)
     */
    public function __construct(
        public readonly int $id,
        public readonly int $pid,
        public readonly string $type,
        public readonly int $sorting,
        public readonly string $headline,
        public readonly bool $managed,
        public readonly array $layout = [],
        public readonly string $gridPreset = '',
        public readonly string $gridCustom = '',
        public readonly string $gridTablet = '',
        public readonly string $gridMobile = '',
        public readonly string $container = '',
        public readonly string $blocktype = '',
        public readonly ?string $html = null,
        public readonly ?string $styleCss = null,
        public readonly ?string $linkColor = null,
        public readonly ?string $scopedCss = null,
        public readonly ?string $respCss = null,
        public readonly bool $isWrapper = false,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id'         => $this->id,
            'pid'        => $this->pid,
            'type'       => $this->type,
            'sorting'    => $this->sorting,
            'headline'   => $this->headline,
            'managed'    => $this->managed,
            'layout'     => $this->layout,
            'gridPreset' => $this->gridPreset,
            'gridCustom' => $this->gridCustom,
            'gridTablet' => $this->gridTablet,
            'gridMobile' => $this->gridMobile,
            'container'  => $this->container,
            'blocktype'  => $this->blocktype,
            'html'       => $this->html,
            'styleCss'   => $this->styleCss,
            'linkColor'  => $this->linkColor,
            'scopedCss'  => $this->scopedCss,
            'respCss'    => $this->respCss,
            'isWrapper'  => $this->isWrapper,
        ];
    }
}
