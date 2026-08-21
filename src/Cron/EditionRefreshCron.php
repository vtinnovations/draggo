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

namespace Vtinnovations\Draggo\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Vtinnovations\Draggo\Agent\RegistryClient;
use Vtinnovations\Draggo\Settings\ActivationStore;
use Vtinnovations\Draggo\Settings\EditionResolver;

/**
 * Daily re-verification, so a renewal or a revocation takes effect without the
 * operator touching the settings screen.
 *
 * Only runs when something is actually stored: an unlicensed installation has
 * no key to send and must never invent one. A failed refresh is a no-op by
 * construction — {@see RegistryClient} preserves the previous record on any
 * transport failure, so a bad night at the licence server cannot lock a paying
 * customer out of their own editor.
 */
#[AsCronJob('daily')]
final class EditionRefreshCron
{
    public function __construct(
        private readonly RegistryClient $registry,
        private readonly EditionResolver $resolver,
        private readonly ActivationStore $store,
    ) {
    }

    public function __invoke(): void
    {
        if (null === $this->store->read()) {
            return;
        }

        // Refresh only against an authentic record; a tampered or foreign one
        // has no key worth sending.
        if ('' === $this->resolver->profile()->key()) {
            return;
        }

        $this->registry->refresh();
    }
}
