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

namespace Vtinnovations\Draggo\Exception;

final class ElementNotFoundException extends \RuntimeException
{
    public function __construct(int $elementId)
    {
        parent::__construct(sprintf('Content element "%d" not found.', $elementId));
    }
}
