[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/thecodingmachine/yaml-definition-loader/badges/quality-score.png?b=1.0)](https://scrutinizer-ci.com/g/thecodingmachine/yaml-definition-loader/?branch=1.0)
[![Build Status](https://travis-ci.org/thecodingmachine/yaml-definition-loader.svg?branch=1.0)](https://travis-ci.org/thecodingmachine/yaml-definition-loader)
[![Coverage Status](https://coveralls.io/repos/thecodingmachine/yaml-definition-loader/badge.svg?branch=1.0&service=github)](https://coveralls.io/github/thecodingmachine/yaml-definition-loader?branch=1.0)

# YAML Definition Loader for *definition-interop*

This package contains a **loader** that can convert YAML files to container definitions compatible with the 
*definition-interop* standard.

In order to keep things simple for newcomers, the supported YAML file is a subset of Symfony services YML file format.

## Installation

You can install this package through Composer:

```json
{
    "require": {
        "thecodingmachine/yaml-definition-loader": "~1.0"
    }
}
```

The packages adheres to the [SemVer](http://semver.org/) specification, and there will be full backward compatibility
between minor versions.

## Automatic discovery

If you want YAML files of your package to be automatically discoverable (using Puli), you should bind your YAML files
to the "definition-interop/yaml-definition-files" binding type.

Lets assume that your service file is in "services/my_service.yml".

In your package, simply type: 

```bash
# This maps the virtual Puli path "/my_vendor/my_package" to the "services" directory. 
puli map /my_vendor/my_package services

# Binds all YML files in the directory services/*.yml (please note how the directory is a virtual Puli directory).
puli bind /my_vendor/my_package/*.yml definition-interop/yaml-definition-files
```

Binded YML files can be discovered automatically if consumers use Puli for Discovery.

## Usage

This package contains a `YamlDefinitionLoader` class. The goal of this class is to take a YAML file and generate
a number of "entry definitions" (as defined in [*definition-interop*](https://github.com/container-interop/definition-interop/)).

These definitions can then be turned into a dependency injection container using the appropriate tools (like [Yaco](https://github.com/thecodingmachine/yaco)). 


```php
use TheCodingMachine\Definition\YamlDefinitionLoader;

$servicesProvider = new YamlDefinitionLoader("my-services.yml");

$definitions = $servicesProvider->getDefinitions();
```

Note: the `YamlDefinitionLoader` implements the `Interop\Container\Definition\DefinitionProviderInterface`.

## File format

### Declare parameters

```yaml
parameters:
    foo: bar
```

### Declare an instance

```yaml
services:
    my_service:
        class: My\ClassName
        arguments: [ foo, bar ]
```

This will declare a "my_service" service, from class `My\ClassName`, passing to the constructor the strings "foo" and "bar".

### Reference an instance

```yaml
services:
    my_reference:
        class: My\ReferencedClass
    my_service:
        class: My\ClassName
        arguments: [ "@my_reference" ]
```

The `my_reference` service will be passed in parameter to the constructor of the `my_service` service.
To reference a service, use the `@` prefix. If you want a string starting with a `@`, you should double it. For instance:

- `@service`
- `@@some text starting with @`

### Call a method of a service

```yaml
services:
    my_service:
        class: My\ClassName
        calls:
            - [ setLogger, [ '@logger' ] ]
```

You can call methods of a service after generating it. For instance, you could call setters.
You need to create a `calls` attribute and pass it a list of methods to be called. The first item is the method name
and the second item is a list of parameters to pass to that method.

### Set a public property of a service

```yaml
services:
    my_service:
        class: My\ClassName
        properties:
            foo: bar
            bar: "@baz"
```

Use the `properties` key to set a public property in a service.

### Aliases

```yaml
services:
    my_service:
        class: My\ClassName
    my_alias:
        alias: my_service
```

You can build services alias using the `alias` attribute.

Alternatively, you can also use this syntax:

```yaml
services:
    my_alias: "@my_service"
```

### Factories

You can use factory methods of other services or classes to build your services.

**Static factories**

```yaml
services:
    my_service:
        factory: My\ClassName::myMethod
```

The `my_service` instance will be returned from a call to `My\ClassName::myMethod`. You can even pass parameters to this
method using the `arguments` attribute:

```yaml
services:
    my_service:
        factory: My\ClassName::myMethod
        attributes: [ '@logger', 42 ] 
```

You can also use this alternative syntax:

```yaml
services:
    my_service:
        factory: [ 'My\ClassName', 'myMethod' ]
```

**Service based factories**

```yaml
services:
    factory:
        class: My\Factory
    my_service:
        factory: factory:myMethod
```

The `my_service` instance will be returned from a call to `myMethod` on the service named `factory`. Notice how we used
a single ':' instead of a double '::'.

You can also use this alternative syntax:

```yaml
services:
    factory:
        class: My\Factory
    my_service:
        factory: 'My\ClassName@myMethod'
```


## Noticeable differences with Symfony YAML services format

- The keys are case sensitive
- Parameters do not accept references (no "@service" reference in the "parameters" section). They can only be scalars.
- These features are not supported:
    - tags
    - public/private services
    - shared services
    - synthetic services
    - lazy services
    - abstract services
    - file based services
    - deprecated services
    - decorated services
    - autowired services
