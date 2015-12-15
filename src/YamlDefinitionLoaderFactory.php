<?php

namespace TheCodingMachine\Definition;


use Assembly\ArrayDefinitionProvider;
use Interop\Container\Definition\DefinitionProviderInterface;
use Interop\Container\Definition\Factory\DefinitionProviderFactoryInterface;
use Puli\Discovery\Api\Discovery;
use Puli\Repository\Resource\FileResource;

/**
 * A class in charge of creating the YamlDefinitionLoader.
 */
class YamlDefinitionLoaderFactory implements DefinitionProviderFactoryInterface
{

    /**
     * Creates a definition provider.
     *
     * @param Discovery $discovery
     *
     * @return DefinitionProviderInterface
     */
    public static function buildDefinitionProvider(Discovery $discovery)
    {
        $bindings = $discovery->findBindings('definition-interop/yaml-definition-files');

        $definitionProviders = [];

        foreach ($bindings as $binding) {
            foreach ($binding->getResources() as $resource) {
                /* @var $resource FileResource */
                $definitionProviders[] = new YamlDefinitionLoader($resource->getFilesystemPath());
            }
        }

        return self::mergeDefinitionProviders($definitionProviders);
    }

    /**
     * @param DefinitionProviderInterface[] $definitionProviders
     */
    private static function mergeDefinitionProviders(array $definitionProviders) {
        $definitions = [];
        foreach ($definitionProviders as $definitionProvider) {
            $definitions += $definitionProvider->getDefinitions();
        }
        return new ArrayDefinitionProvider($definitions);
    }
}
