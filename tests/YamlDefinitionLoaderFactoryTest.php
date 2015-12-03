<?php

namespace TheCodingMachine\Definition;

use Assembly\Reference;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Discovery\InMemoryDiscovery;
use Puli\Repository\FilesystemRepository;
use Puli\Repository\InMemoryRepository;
use TheCodingMachine\Definition\Exception\FileNotFoundException;

class YamlDefinitionLoaderFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testFactory() {
        $discovery = new InMemoryDiscovery();
        $discovery->addBindingType(new BindingType('thecodingmachine/yaml_definitions'));
        $resourceBinding = new ResourceBinding(__DIR__.'/Fixtures/services6.yml', 'thecodingmachine/yaml_definitions');
        $repository = new FilesystemRepository();

        $resourceBinding->setRepository($repository);
        $discovery->addBinding($resourceBinding);

        $definitionProvider = YamlDefinitionLoaderFactory::buildDefinitionProvider($discovery);
        $definitions = $definitionProvider->getDefinitions();

        $this->assertArrayHasKey('foo', $definitions);
    }
}
