# Doctrine Resolve Target Entity Bundle

[![PHP](https://img.shields.io/badge/php-%5E8.1-777BB4.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Build Status](https://github.com/tourze/php-monorepo/workflows/CI/badge.svg)](
https://github.com/tourze/php-monorepo/actions)
[![Coverage Status](https://coveralls.io/repos/github/tourze/php-monorepo/badge.svg?branch=master)](
https://coveralls.io/github/tourze/php-monorepo?branch=master)

[English](README.md) | [中文](README.zh-CN.md)

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
  - [Register the Bundle](#register-the-bundle)
  - [Define an Interface](#define-an-interface)
  - [Create Entity Implementation](#create-entity-implementation)
  - [Use Interface in Related Entities](#use-interface-in-related-entities)
- [Configuration](#configuration)
- [Services](#services)
- [Advanced Usage](#advanced-usage)
- [Error Handling](#error-handling)
- [Security](#security)
- [Dependencies](#dependencies)
- [License](#license)

## Overview

A Symfony bundle that provides entity decoupling functionality through Doctrine's 
ResolveTargetEntity feature. This bundle allows you to define interfaces in your 
entities and map them to concrete implementations at runtime, enabling better 
architectural separation and flexibility.

## Features

- **Entity Decoupling**: Use interfaces instead of concrete classes in entity relationships
- **Runtime Resolution**: Map interfaces to concrete implementations at container compilation time
- **Flexible Architecture**: Easily swap implementations without changing entity definitions
- **Doctrine Integration**: Seamlessly integrates with Doctrine ORM's ResolveTargetEntity feature

## Installation

```bash
composer require tourze/doctrine-resolve-target-entity-bundle
```

## Quick Start

### Register the Bundle

Add the bundle to your `config/bundles.php`:

```php
return [
    // ...
    Tourze\DoctrineResolveTargetEntityBundle\DoctrineResolveTargetEntityBundle::class => ['all' => true],
];
```

### Define an Interface

```php
<?php

namespace App\Entity;

interface UserInterface
{
    public function getId(): int;
    public function getName(): string;
}
```

### Create Entity Implementation

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $name;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
```

## Configuration

```php
<?php

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Tourze\DoctrineResolveTargetEntityBundle\DependencyInjection\Compiler\ResolveTargetEntityPass;
use App\Entity\UserInterface;
use App\Entity\User;

class AppExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ResolveTargetEntityPass(
            UserInterface::class,
            User::class
        ));
    }
}
```

### Use Interface in Related Entities

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Article
{
    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    private UserInterface $author;

    public function getAuthor(): UserInterface
    {
        return $this->author;
    }
}
```

## Services

### ResolveTargetEntityService

The bundle provides a service for managing entity mappings:

```php
use Tourze\DoctrineResolveTargetEntityBundle\Service\ResolveTargetEntityService;

class MyService
{
    public function __construct(
        private ResolveTargetEntityService $resolveTargetEntityService
    ) {}

    public function findEntityClass(string $interface): string
    {
        return $this->resolveTargetEntityService->findEntityClass($interface);
    }
}
```

## Advanced Usage

### Testing Support

The bundle includes testing utilities:

- `TestEntityGenerator`: Generates test entities dynamically
- `TestKernelHelper`: Assists with test kernel setup

### Custom Interface Mappings

You can register multiple interface mappings:

```php
// Multiple mappings
$container->addCompilerPass(new ResolveTargetEntityPass(
    'App\Entity\UserInterface',
    'App\Entity\User'
));

$container->addCompilerPass(new ResolveTargetEntityPass(
    'App\Entity\ProductInterface',
    'App\Entity\Product'
));
```

## Error Handling

The bundle provides specific exceptions:

- `EntityClassNotFoundException`: Thrown when an interface mapping is not found
- `InvalidInterfaceException`: Thrown when an interface is invalid or doesn't exist

## Security

This bundle follows Symfony security best practices:

- All services are properly configured in the service container
- No user input is directly processed without validation
- Interface resolution is performed at container compilation time

## Dependencies

This bundle requires:

- PHP 8.1 or higher
- Symfony 6.4 or higher
- Doctrine ORM 3.0 or higher
- Doctrine Bundle 2.13 or higher

## License

This bundle is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.