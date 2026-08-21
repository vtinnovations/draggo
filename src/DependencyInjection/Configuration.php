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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Config tree for the bundle. All values are ENV-overridable in services.yaml.
 *
 * The AI block is consumed from Phase 3 onward; it is declared here in Phase 1
 * so the schema is stable and the settings UI can bind to it early.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('draggo');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('ai')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('provider')
                            ->values(['claude', 'openai', 'gemini'])
                            ->defaultValue('claude')
                        ->end()
                        ->scalarNode('model')->defaultValue('claude-opus-4-8')->end()
                        ->scalarNode('api_key')->defaultValue('')->end()
                        ->integerNode('max_tokens')->defaultValue(4096)->min(256)->end()
                        ->integerNode('max_rounds')->defaultValue(8)->min(1)->max(30)->end()
                        ->integerNode('rate_limit_per_hour')->defaultValue(30)->min(1)->end()
                        ->integerNode('cost_cap_deci_cents')->defaultValue(5000)->min(0)->end()
                        // Optional opt-in: anonymised block-type telemetry to a
                        // central endpoint (structure only — never user content).
                        ->booleanNode('telemetry')->defaultFalse()->end()
                        ->scalarNode('telemetry_url')->defaultValue('')->end()
                    ->end()
                ->end()
                ->arrayNode('editor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_columns')->defaultValue(6)->min(1)->max(12)->end()
                    ->end()
                ->end()
                ->arrayNode('bootstrap')
                    ->addDefaultsIfNotSet()
                    ->children()
                        // full = load external Bootstrap (css_url/js_url);
                        // grid = ship the bundle's local grid CSS only;
                        // off  = theme already provides Bootstrap.
                        // grid = ship the bundle's local grid CSS (no CDN, no JS);
                        // full = load external Bootstrap (css_url/js_url);
                        // off  = theme already provides Bootstrap.
                        ->enumNode('mode')
                            ->values(['full', 'grid', 'off'])
                            ->defaultValue('grid')
                        ->end()
                        ->scalarNode('css_url')
                            ->defaultValue('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')
                        ->end()
                        ->scalarNode('js_url')
                            ->defaultValue('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
