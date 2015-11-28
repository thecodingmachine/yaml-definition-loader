<?php

namespace TheCodingMachine\Definition;


use Interop\Container\Definition\DefinitionProviderInterface;
use Interop\Container\Definition\Factory\DefinitionProviderFactoryInterface;
use Puli\Discovery\Api\Discovery;

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
 // TODO: change method signature to   DefinitionProviderInterface[]
        // Then foreach discovered yaml file, go!
    }
}
