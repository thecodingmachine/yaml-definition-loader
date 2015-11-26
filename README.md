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


## Noticeable differences with Symfony YAML services format

- The keys are case sensitive
- Parameters do not accept references (no "@service" reference in the "parameters" section). They can only be scalars.
