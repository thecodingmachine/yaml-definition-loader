<?php

namespace TheCodingMachine\Definition;

use Assembly\Reference;
use TheCodingMachine\Definition\Exception\FileNotFoundException;

class YamlDefinitionLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/Fixtures/');
    }

    public function testLoadFile()
    {
        try {
            $loader = new YamlDefinitionLoader(self::$fixturesPath.'/foo.yml');
            $loader->getDefinitions();
            $this->fail('->load() throws a FileNotFoundException if the loaded file does not exist');
        } catch (FileNotFoundException $e) {
            // Ok
        }

        try {
            $loader = new YamlDefinitionLoader(self::$fixturesPath.'/parameters.ini');
            $loader->getDefinitions();
            $this->fail('->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
        } catch (\Exception $e) {
            $this->assertInstanceOf('TheCodingMachine\Definition\Exception\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
        }

        foreach (array('nonvalid1', 'nonvalid2') as $fixture) {
            try {
                $loader = new YamlDefinitionLoader(self::$fixturesPath.'/'.$fixture.'.yml');
                $loader->getDefinitions();
                $this->fail('->load() throws an InvalidArgumentException if the loaded file does not validate');
            } catch (\Exception $e) {
                $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file does not validate');
            }
        }
    }

    public function testEmptyFile()
    {
        $loader = new YamlDefinitionLoader(self::$fixturesPath.'/services1.yml');

        $definitions = $loader->getDefinitions();
        $this->assertEquals([], $definitions);
    }

    /**
     * @dataProvider provideInvalidFiles
     * @expectedException \TheCodingMachine\Definition\Exception\InvalidArgumentException
     */
    public function testLoadInvalidFile($file)
    {
        $loader = new YamlDefinitionLoader(self::$fixturesPath.'/'.$file.'.yml');

        $loader->getDefinitions();
    }

    /**
     * @expectedException \TheCodingMachine\Definition\Exception\InvalidArgumentException
     */
    public function testLoadRemoteFile()
    {
        $loader = new YamlDefinitionLoader('http://example.com/file.yml');

        $loader->getDefinitions();
    }

    public function provideInvalidFiles()
    {
        return array(
            array('bad_parameters'),
            array('bad_imports'),
            array('bad_import'),
            array('bad_services'),
            array('bad_service'),
            array('bad_calls'),
            array('bad_format'),
            array('services4_bad_import'),
            array('unsupported1'),
            array('unsupported2'),
            array('unsupported3'),
            array('unsupported4'),
            array('unsupported5'),
            array('unsupported6'),
            array('unsupported7'),
            array('unsupported8'),
            array('unsupported9'),
            array('unsupported10'),
            array('unsupported11'),
            array('unsupported12'),
            array('unsupported13'),
            array('unsupported14'),
            array('unsupported15'),
            array('unsupported16'),
            array('unsupported17'),
        );
    }

    public function testLoadParameters()
    {
        $loader = new YamlDefinitionLoader(self::$fixturesPath.'/services2.yml');
        $definitions = $loader->getDefinitions();

        $expectedResults = array('foo' => 'bar', 'MixedCase' => array('MixedCaseKey' => 'value'), 'values' => array(true, false, 0, 1000.3), 'bar' => 'foo', 'foo_bar' => '@foo_bar');

        foreach ($definitions as $definition) {
            $this->assertInstanceOf('Interop\\Container\\Definition\\ParameterDefinitionInterface', $definition);
            $this->assertEquals($expectedResults[$definition->getIdentifier()], $definition->getValue());
        }
    }

    public function testLoadImports()
    {
        $loader = new YamlDefinitionLoader(self::$fixturesPath.'/services4.yml');
        $definitions = $loader->getDefinitions();

        $expectedResults = array('foo' => 'foo', 'values' => array(true, false), 'bar' => 'foo', 'MixedCase' => array('MixedCaseKey' => 'value'), 'foo_bar' => '@foo_bar');

        foreach ($definitions as $definition) {
            $this->assertInstanceOf('Interop\\Container\\Definition\\ParameterDefinitionInterface', $definition);
            $this->assertEquals($expectedResults[$definition->getIdentifier()], $definition->getValue());
        }
    }

    public function testLoadServices()
    {
        $loader = new YamlDefinitionLoader(self::$fixturesPath.'/services6.yml');
        $services = $loader->getDefinitions();
        $this->assertTrue(isset($services['foo']), '->load() parses service elements');
        $this->assertInstanceOf('Interop\Container\Definition\DefinitionInterface', $services['foo'], '->load() converts service element to Definition instances');
        $this->assertEquals('FooClass', $services['foo']->getClassName(), '->load() parses the class attribute');
        $this->assertEquals(array('foo', new Reference('foo'), array(true, false), '@foo'), $services['arguments']->getConstructorArguments(), '->load() parses the argument tags');
        $this->assertEquals('setBar', $services['method_call2']->getMethodCalls()[0]->getMethodName(), '->load() parses the method_call tag');
        $this->assertEquals(array('foo', new Reference('foo'), array(true, false)), $services['method_call2']->getMethodCalls()[0]->getArguments(), '->load() parses the method_call tag');

        //$this->assertEquals('factory', $services['new_factory1']->getFactory(), '->load() parses the factory tag');
        $this->assertEquals(new Reference('foo'), $services['new_factory2']->getReference(), '->load() parses the factory tag');
        $this->assertEquals('method', $services['new_factory2']->getMethodName(), '->load() parses the factory tag');
        $this->assertEquals(new Reference('baz'), $services['new_factory3']->getReference(), '->load() parses the factory tag');
        //$this->assertEquals('Class', $services['new_factory3']->getReference(), '->load() parses the factory tag');
        //$this->assertEquals('getClass', $services['new_factory3']->getMethodName(), '->load() parses the factory tag');

        $this->assertEquals('foo', $services['alias_for_foo']->getTarget(), '->load() parses aliases');
        $this->assertEquals('foo', $services['another_alias_for_foo']->getTarget(), '->load() parses aliases');

        $this->assertEquals('foo', $services['instance_with_properties']->getPropertyAssignments()[0]->getPropertyName(), '->load() parses the properties tag');
        $this->assertEquals('bar', $services['instance_with_properties']->getPropertyAssignments()[0]->getValue(), '->load() parses the properties tag');
        $this->assertEquals('bar', $services['instance_with_properties']->getPropertyAssignments()[1]->getPropertyName(), '->load() parses the properties tag');
        $this->assertEquals('baz', $services['instance_with_properties']->getPropertyAssignments()[1]->getValue()->getTarget(), '->load() parses the properties tag');
    }

    public function testLoadYamlOnlyWithKeys()
    {
        $loader = new YamlDefinitionLoader(self::$fixturesPath.'/services21.yml');

        $definitions = $loader->getDefinitions();
        $definition = $definitions['manager'];
        $this->assertEquals('setLogger', $definition->getMethodCalls()[0]->getMethodName());
        $this->assertEquals(array(new Reference('logger')), $definition->getMethodCalls()[0]->getArguments());
        //$this->assertEquals(array(true), $definition->getArguments());
        //$this->assertEquals(array('manager' => array(array('alias' => 'user'))), $definition->getTags());
    }
}
