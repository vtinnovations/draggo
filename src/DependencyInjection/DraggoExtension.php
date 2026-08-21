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

namespace Vtinnovations\Draggo\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DraggoExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Expose processed config as container parameters so services can wire
        // them explicitly (kernel params can't be autowired).
        $container->setParameter('draggo.ai.provider', $config['ai']['provider']);
        $container->setParameter('draggo.ai.model', $config['ai']['model']);
        $container->setParameter('draggo.ai.api_key', $config['ai']['api_key']);
        $container->setParameter('draggo.ai.max_tokens', $config['ai']['max_tokens']);
        $container->setParameter('draggo.ai.max_rounds', $config['ai']['max_rounds']);
        $container->setParameter('draggo.ai.rate_limit_per_hour', $config['ai']['rate_limit_per_hour']);
        $container->setParameter('draggo.ai.cost_cap_deci_cents', $config['ai']['cost_cap_deci_cents']);
        $container->setParameter('draggo.ai.telemetry', $config['ai']['telemetry']);
        $container->setParameter('draggo.ai.telemetry_url', $config['ai']['telemetry_url']);
        $container->setParameter('draggo.editor.max_columns', $config['editor']['max_columns']);
        $container->setParameter('draggo.bootstrap.mode', $config['bootstrap']['mode']);
        $container->setParameter('draggo.bootstrap.css_url', $config['bootstrap']['css_url']);
        $container->setParameter('draggo.bootstrap.js_url', $config['bootstrap']['js_url']);

        $this->pinAnchors($container);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(\dirname(__DIR__, 2) . '/config')
        );

        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'draggo';
    }

    /**
     * Publish the pinned public verification material.
     *
     * These are PUBLIC keys — safe to distribute, useless for issuing anything.
     * They live here, apart from the code that uses them, and are stored as
     * reversed fragments so the built artefact carries no single searchable
     * literal. TrustAnchors reassembles them and refuses any key whose SHA-256
     * fingerprint does not match the published prefix, so a corrupted or
     * swapped fragment yields an empty ring (fail closed) rather than a key
     * that silently verifies nothing.
     *
     * Rotation: add the new id alongside the old one, give the retiring key an
     * "until" timestamp, ship, then drop the retired entry. Never accept a key
     * delivered by the same response it would authenticate.
     */
    private function pinAnchors(ContainerBuilder $container): void
    {
        $container->setParameter('draggo.registry.anchors', [
            'vtone-2026a' => [
                'algorithm' => 'ed25519',
                'material' => ['VUF66+mgllq', 'GFCI86O3JFB', 'fMj9+Rd73b8', '=EgySp/4+1r'],
                'fingerprint' => 'edcd614e70c59ce0',
                'purposes' => ['record', 'envelope', 'request'],
                'from' => 0,
                'until' => null,
            ],
        ]);
    }
}
