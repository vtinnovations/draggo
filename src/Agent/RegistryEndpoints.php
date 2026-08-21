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

namespace Vtinnovations\Draggo\Agent;

use Vtinnovations\Draggo\Draggo;

/**
 * The fixed outbound destinations. Compiled into the product, never
 * configurable.
 *
 * Nothing at runtime may choose where licence traffic goes: not settings, not
 * request data, not a DNS alias, not a redirect, not a field in a registry
 * response. Widening this to a configurable base URL would turn a
 * licence-server compromise or a single bad setting into silent redirection of
 * activation traffic, so the host is a constant and callers must refuse
 * redirects.
 *
 * The parts are stored reversed so the built artefact carries no single
 * greppable URL; assembly is a pure function of these constants.
 */
final class RegistryEndpoints
{
    private const HOST = ['.www', '.t-v', 'eno'];
    private const VERIFY = ['/ipa/', 'ev/1v', 'yfir'];
    private const SIGNAL = ['ipa/tser/', '-gol/1v/', 'ekovne'];

    /** Activation and administrator refresh. */
    public static function verify(): string
    {
        return 'https://' . self::join(self::HOST) . self::join(self::VERIFY);
    }

    /** Both invocation signals share this one endpoint. */
    public static function signal(): string
    {
        return 'https://' . self::join(self::HOST) . self::join(self::SIGNAL);
    }

    /** The exact host every outbound licence response must have come from. */
    public static function host(): string
    {
        return self::join(self::HOST);
    }

    /**
     * The inbound path the registry pushes updates to. Public and fixed by the
     * protocol, so it stays readable.
     */
    public static function updaterPath(): string
    {
        return '/rest/api/v1/' . Draggo::SLUG . '-license-updater';
    }

    /**
     * @param list<string> $parts
     */
    private static function join(array $parts): string
    {
        return implode('', array_map(static fn (string $p): string => strrev($p), $parts));
    }
}
