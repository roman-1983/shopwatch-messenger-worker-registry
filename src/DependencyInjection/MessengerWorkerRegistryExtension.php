<?php

namespace ShopWatch\MessengerWorkerRegistry\DependencyInjection;

use ShopWatch\MessengerWorkerRegistry\WorkerRegistry;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class MessengerWorkerRegistryExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $definition = $container->getDefinition(WorkerRegistry::class);
        $definition->replaceArgument('$ttlSeconds', $config['ttl']);
        $definition->replaceArgument('$cache', new Reference($config['cache']));
    }
}
