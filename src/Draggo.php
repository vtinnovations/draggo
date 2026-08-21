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

namespace Vtinnovations\Draggo;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Draggo extends Bundle
{
    /** Product identity as registered with the V&T registry. Not secret. */
    public const PROJECT = 'Draggo';
    public const SLUG = 'draggo';
    public const PRODUCT_ID = 'vt-draggo';

    /**
     * The only package value this product accepts. Draggo is sold as a single
     * paid edition: there is no free, starter, demo or trial package, and a
     * record carrying one of those is not a licence for this bundle.
     */
    public const PACKAGES = ['pro'];

    /** Record schema this client understands. */
    public const SCHEMA = 2;

    /**
     * Return the bundle ROOT (not src/) so Contao can locate the contao/
     * folder for DCA, languages and config.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
