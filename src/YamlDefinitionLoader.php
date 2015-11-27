<?php
namespace TheCodingMachine\Definition;

use Assembly\AliasDefinition;
use Assembly\FactoryDefinition;
use Assembly\InstanceDefinition;
use Assembly\MethodCall;
use Assembly\ParameterDefinition;
use Assembly\PropertyAssignment;
use Assembly\Reference;
use Interop\Container\Definition\DefinitionInterface;
use Interop\Container\Definition\DefinitionProviderInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use TheCodingMachine\Definition\Exception\FileNotFoundException;
use TheCodingMachine\Definition\Exception\InvalidArgumentException;
use TheCodingMachine\Definition\Exception\RuntimeException;

class YamlDefinitionLoader implements DefinitionProviderInterface
{
    /**
     * The name of the YAML file to be loaded.
     *
     * @var string
     */
    private $fileName;

    public function __construct($fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * Returns the definition to register in the container.
     *
     * @return DefinitionInterface[]
     */
    public function getDefinitions()
    {
        $content = $this->loadFile($this->fileName);

        // empty file
        if (null === $content) {
            return [];
        }

        // imports
        $definitions = $this->parseImports($content);

        // parameters
        if (isset($content['parameters'])) {
            if (!is_array($content['parameters'])) {
                throw new InvalidArgumentException(sprintf('The "parameters" key should contain an array in %s. Check your YAML syntax.', $this->fileName));
            }

            foreach ($content['parameters'] as $key => $value) {
                $definitions[$key] = new ParameterDefinition($key, $value);
                //$this->container->setParameter($key, $this->resolveServices($value));
            }
        }

        // services
        $serviceDefinitions = $this->parseDefinitions($content);
        $definitions = $definitions + $serviceDefinitions;

        return $definitions;
    }

    /**
     * Parses all imports.
     *
     * @param array  $content
     * @param $content
     * @return DefinitionInterface[]
     */
    private function parseImports($content)
    {
        if (!isset($content['imports'])) {
            return [];
        }

        if (!is_array($content['imports'])) {
            throw new InvalidArgumentException(sprintf('The "imports" key should contain an array in %s. Check your YAML syntax.', $this->fileName));
        }

        $additionalDefinitions = [];

        foreach ($content['imports'] as $import) {
            if (!is_array($import)) {
                throw new InvalidArgumentException(sprintf('The values in the "imports" key should be arrays in %s. Check your YAML syntax.', $this->fileName));
            }

            if (isset($import['ignore_errors'])) {
                throw new InvalidArgumentException(sprintf('The "ignore_errors" key is not supported in YamlDefinitionLoader. This is a Symfony specific syntax. Check your YAML syntax.', $this->fileName));
            }

            $importFileName = $import['resource'];

            if (strpos($importFileName, '/') !== 0) {
                $importFileName = dirname($this->fileName).'/'.$importFileName;
            }

            $yamlDefinitionLoader = new self($importFileName);
            $newDefinitions = $yamlDefinitionLoader->getDefinitions();

            $additionalDefinitions = $newDefinitions + $additionalDefinitions;
        }
        return $additionalDefinitions;
    }

    /**
     * Parses definitions.
     *
     * @param array  $content
     * @return DefinitionInterface[]
     */
    private function parseDefinitions($content)
    {
        if (!isset($content['services'])) {
            return [];
        }

        if (!is_array($content['services'])) {
            throw new InvalidArgumentException(sprintf('The "services" key should contain an array in %s. Check your YAML syntax.', $this->fileName));
        }

        $definitions = [];

        foreach ($content['services'] as $id => $service) {
            $definitions[$id] = $this->parseDefinition($id, $service);
        }

        return $definitions;
    }

    /**
     * Parses a definition.
     *
     * @param string $id
     * @param array $service
     *
     * @return DefinitionInterface
     * @throws InvalidArgumentException When tags are invalid
     */
    private function parseDefinition($id, $service)
    {
        if (is_string($service) && 0 === strpos($service, '@')) {
            return new AliasDefinition($id, substr($service, 1));
        }

        if (!is_array($service)) {
            throw new InvalidArgumentException(sprintf('A service definition must be an array or a string starting with "@" but %s found for service "%s" in %s. Check your YAML syntax.', gettype($service), $id, $this->fileName));
        }

        if (isset($service['alias'])) {
            if (isset($service['public'])) {
                throw new InvalidArgumentException('The "public" key is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
            }
            return new AliasDefinition($id, $service['alias']);
        }

        if (isset($service['parent'])) {
            throw new InvalidArgumentException('Definition decorators via the "parent" key are not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        $definition = null;

        if (isset($service['class'])) {
            $definition = new InstanceDefinition($id, $service['class']);

            if (isset($service['arguments'])) {
                $arguments = $this->resolveServices($service['arguments']);
                foreach ($arguments as $argument) {
                    $definition->addConstructorArgument($argument);
                }
            }

            if (isset($service['properties'])) {
                $properties = $this->resolveServices($service['properties']);
                foreach ($properties as $name => $property) {
                    $definition->addPropertyAssignment($name, $property);
                }
            }

            if (isset($service['calls'])) {
                if (!is_array($service['calls'])) {
                    throw new InvalidArgumentException(sprintf('Parameter "calls" must be an array for service "%s" in %s. Check your YAML syntax.', $id, $this->fileName));
                }

                foreach ($service['calls'] as $call) {
                    if (isset($call['method'])) {
                        $method = $call['method'];
                        $args = isset($call['arguments']) ? $this->resolveServices($call['arguments']) : array();
                    } else {
                        $method = $call[0];
                        $args = isset($call[1]) ? $this->resolveServices($call[1]) : array();
                    }

                    array_unshift($args, $method);
                    call_user_func_array([$definition, 'addMethodCall'], $args);
                }
            }

        }

        if (isset($service['factory'])) {
            if (is_string($service['factory'])) {
                if (strpos($service['factory'], ':') !== false && strpos($service['factory'], '::') === false) {
                    $parts = explode(':', $service['factory']);
                    $definition = new FactoryDefinition($id, $this->resolveServices('@'.$parts[0]), $parts[1]);
                } elseif (strpos($service['factory'], ':') !== false && strpos($service['factory'], '::') !== false) {
                    $parts = explode('::', $service['factory']);
                    $definition = new FactoryDefinition($id, $parts[0], $parts[1]);
                } else {
                    throw new InvalidArgumentException('A "factory" must be in the format "service_name:method_name" or "class_name::method_name".Got "'.$service['factory'].'"');
                }
            } else {
                $definition = new FactoryDefinition($id, $this->resolveServices($service['factory'][0]), $service['factory'][1]);
            }

            if (isset($service['arguments'])) {
                $arguments = $this->resolveServices($service['arguments']);
                call_user_func_array([$definition, 'setArguments'], $arguments);
            }
        }

        if (isset($service['shared'])) {
            throw new InvalidArgumentException('The "shared" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        if (isset($service['synthetic'])) {
            throw new InvalidArgumentException('The "synthetic" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        if (isset($service['lazy'])) {
            throw new InvalidArgumentException('The "lazy" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        if (isset($service['public'])) {
            throw new InvalidArgumentException('The "public" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        if (isset($service['abstract'])) {
            throw new InvalidArgumentException('The "abstract" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        if (array_key_exists('deprecated', $service)) {
            throw new InvalidArgumentException('The "deprecated" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        if (isset($service['file'])) {
            throw new InvalidArgumentException('The "file" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }


        if (isset($service['configurator'])) {
            throw new InvalidArgumentException('The "configurator" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }


        if (isset($service['tags'])) {
            throw new InvalidArgumentException('The "tags" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        if (isset($service['decorates'])) {
            throw new InvalidArgumentException('The "decorates" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        if (isset($service['autowire'])) {
            throw new InvalidArgumentException('The "autowire" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        if (isset($service['autowiring_types'])) {
            throw new InvalidArgumentException('The "autowiring_types" key in instance definitions is not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        }

        if ($definition === null) {
            throw new InvalidArgumentException(sprintf('Invalid service declaration for "%s" (in %s). You should specify at least a "class", "alias" or "factory" key.', $id, $this->fileName));
        }

        return $definition;
    }

    /**
     * Loads a YAML file.
     *
     * @param string $file
     *
     * @return array The file content
     *
     * @throws InvalidArgumentException when the given file is not a local file or when it does not exist
     */
    protected function loadFile($file)
    {
        if (!stream_is_local($file)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $file));
        }

        if (!is_readable($file)) {
            throw new FileNotFoundException(sprintf('The file "%s" does not exist or is not readable.', $file));
        }

        $yamlParser = new Parser();

        try {
            $configuration = $yamlParser->parse(file_get_contents($file));
        } catch (ParseException $e) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML.', $file), 0, $e);
        }

        return $this->validate($configuration, $file);
    }

    /**
     * Validates a YAML file.
     *
     * @param mixed  $content
     * @param string $file
     *
     * @return array
     *
     * @throws InvalidArgumentException When service file is not valid
     */
    private function validate($content, $file)
    {
        if (null === $content) {
            return $content;
        }

        if (!is_array($content)) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid. It should contain an array. Check your YAML syntax.', $file));
        }

        foreach ($content as $namespace => $data) {
            if (in_array($namespace, array('imports', 'parameters', 'services'))) {
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'Cannot load the configuration for file "%s". Unexpected "%s" key. Expecting one of "imports", "parameters", "services".',
                $file,
                $namespace
            ));
        }

        return $content;
    }

    /**
     * Resolves services.
     *
     * @param string|array $value
     *
     * @return array|string|Reference
     */
    private function resolveServices($value)
    {
        if (is_array($value)) {
            return array_map(array($this, 'resolveServices'), $value);
        } elseif (is_string($value) &&  0 === strpos($value, '@=')) {
            throw new InvalidArgumentException('Expressions (starting by "@=") are not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
        } elseif (is_string($value) &&  0 === strpos($value, '@')) {
            if (0 === strpos($value, '@@')) {
                return substr($value, 1);
            } elseif (0 === strpos($value, '@?')) {
                throw new InvalidArgumentException('Optional services (starting by "@?") are not supported by YamlDefinitionLoader. This is a Symfony specific feature.');
            } else {
                $value = substr($value, 1);
                if ('=' === substr($value, -1)) {
                    throw new InvalidArgumentException('Non-strict services (ending with "=") are not supported by YamlDefinitionLoader. This is a Symfony specific feature.');            } else {
                }
                return new Reference($value);
            }
        } else {
            return $value;
        }
    }
}
