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

namespace Vtinnovations\Draggo\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Vtinnovations\Draggo\Agent\RegistryClient;
use Vtinnovations\Draggo\Settings\ActivationStore;

/**
 * Retires the previous activation file (var/draggo/license.json).
 *
 * That file recorded a bare key plus a cached verdict. Nothing in it was
 * signed, so there is nothing to authenticate and it CANNOT simply be adopted
 * as state — trusting it would mean trusting a file any operator could edit,
 * which is exactly the weakness this rewrite removes.
 *
 * What it does carry that is worth keeping is the key the customer already paid
 * for. So the key is taken as a seed for one real, fully verified activation
 * against the registry:
 *
 *   - registry confirms it → a properly signed record lands in the new store
 *     and the installation stays licensed across the upgrade with no
 *     re-typing;
 *   - registry declines, or is unreachable → nothing is granted. The operator
 *     re-activates from Settings → Draggo Licence management.
 *
 * Either way the legacy file is deleted, so there is exactly one store and no
 * possibility of the old and new halves disagreeing. Deleting it is safe: it
 * held no content, only a cached verdict.
 */
final class RetireLegacyActivationMigration extends AbstractMigration
{
    public function __construct(
        private readonly RegistryClient $registry,
        private readonly ActivationStore $store,
        private readonly string $projectDir,
    ) {
    }

    public function getName(): string
    {
        return 'Draggo: migrate the previous licence file to the signed activation store';
    }

    public function shouldRun(): bool
    {
        return is_file($this->legacyFile());
    }

    public function run(): MigrationResult
    {
        $file = $this->legacyFile();
        $key = $this->legacyKey($file);

        // Never overwrite an already-migrated signed record.
        $alreadyActivated = null !== $this->store->read();
        $migrated = false;

        if ('' !== $key && !$alreadyActivated) {
            $migrated = RegistryClient::OK === $this->registry->activate($key)['status'];
        }

        @unlink($file);

        if ($alreadyActivated) {
            return $this->createResult(true, 'Legacy licence file removed; the signed activation was already in place.');
        }

        return $this->createResult(
            true,
            $migrated
                ? 'Licence re-activated against v-t.one and stored in signed form; legacy file removed.'
                : 'Legacy licence file removed. Please activate under Settings → Draggo Licence management.',
        );
    }

    /** The key is the only field worth carrying forward. */
    private function legacyKey(string $file): string
    {
        $data = json_decode((string) @file_get_contents($file), true);

        return \is_array($data) ? trim((string) ($data['license_key'] ?? '')) : '';
    }

    private function legacyFile(): string
    {
        return rtrim($this->projectDir, '/\\') . '/var/draggo/license.json';
    }
}
