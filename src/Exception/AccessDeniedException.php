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
 * Thrown by RequestGuard when CSRF, authentication, authorisation or record
 * ownership checks fail. The API controllers translate it to HTTP 403.
 */
final class AccessDeniedException extends \RuntimeException
{
}
