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

/**
 * Thrown when request input fails whitelist validation (unknown CTE type,
 * non-whitelisted field, malformed JSON). API controllers map it to HTTP 422.
 */
final class InvalidInputException extends \RuntimeException
{
}
