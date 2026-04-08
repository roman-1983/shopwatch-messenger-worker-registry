<?php

namespace ShopWatch\MessengerWorkerRegistry\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('messenger_worker_registry');

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('ttl')
                    ->defaultValue(120)
                    ->min(10)
                    ->info('TTL in seconds before a worker is considered dead (default: 120)')
                ->end()
                ->scalarNode('cache')
                    ->defaultValue('cache.app')
                    ->info('Cache pool service ID to use for worker registry storage')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
