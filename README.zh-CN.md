# Doctrine 解析目标实体 Bundle

[![PHP](https://img.shields.io/badge/php-%5E8.1-777BB4.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Build Status](https://github.com/tourze/php-monorepo/workflows/CI/badge.svg)](
https://github.com/tourze/php-monorepo/actions)
[![Coverage Status](https://coveralls.io/repos/github/tourze/php-monorepo/badge.svg?branch=master)](
https://coveralls.io/github/tourze/php-monorepo?branch=master)

[English](README.md) | [中文](README.zh-CN.md)

## 目录

- [概述](#概述)
- [功能特性](#功能特性)
- [安装](#安装)
- [快速开始](#快速开始)
  - [注册 Bundle](#注册-bundle)
  - [定义接口](#定义接口)
  - [创建实体实现](#创建实体实现)
  - [配置目标实体解析](#配置目标实体解析)
  - [在相关实体中使用接口](#在相关实体中使用接口)
- [服务](#服务)
- [高级用法](#高级用法)
- [错误处理](#错误处理)
- [安全性](#安全性)
- [依赖关系](#依赖关系)
- [许可证](#许可证)

## 概述

一个 Symfony Bundle，通过 Doctrine 的 ResolveTargetEntity 功能提供实体解耦功能。
该 Bundle 允许你在实体中定义接口，并在运行时将其映射到具体实现，从而实现更好的
架构分离和灵活性。

## 功能特性

- **实体解耦**：在实体关系中使用接口而非具体类
- **运行时解析**：在容器编译时将接口映射到具体实现
- **灵活架构**：轻松切换实现而无需更改实体定义
- **Doctrine 集成**：与 Doctrine ORM 的 ResolveTargetEntity 功能无缝集成

## 安装

```bash
composer require tourze/doctrine-resolve-target-entity-bundle
```

## 快速开始

### 注册 Bundle

将 Bundle 添加到你的 `config/bundles.php`：

```php
return [
    // ...
    Tourze\DoctrineResolveTargetEntityBundle\DoctrineResolveTargetEntityBundle::class => ['all' => true],
];
```

### 定义接口

```php
<?php

namespace App\Entity;

interface UserInterface
{
    public function getId(): int;
    public function getName(): string;
}
```

### 创建实体实现

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

## 配置目标实体解析

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

### 在相关实体中使用接口

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

## 配置

Bundle 通过编译器 Pass 注册接口到类的映射。你可以在应用扩展中注册映射：

```php
use Tourze\DoctrineResolveTargetEntityBundle\DependencyInjection\Compiler\ResolveTargetEntityPass;

$container->addCompilerPass(new ResolveTargetEntityPass(
    'App\Entity\UserInterface',
    'App\Entity\User'
));
```

## 服务

### ResolveTargetEntityService

Bundle 提供了管理实体映射的服务：

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

## 高级用法

### 测试支持

Bundle 包含测试实用工具：

- `TestEntityGenerator`：动态生成测试实体
- `TestKernelHelper`：协助测试内核设置

### 自定义接口映射

你可以注册多个接口映射：

```php
// 多个映射
$container->addCompilerPass(new ResolveTargetEntityPass(
    'App\Entity\UserInterface',
    'App\Entity\User'
));

$container->addCompilerPass(new ResolveTargetEntityPass(
    'App\Entity\ProductInterface',
    'App\Entity\Product'
));
```

## 错误处理

Bundle 提供特定的异常：

- `EntityClassNotFoundException`：当找不到接口映射时抛出
- `InvalidInterfaceException`：当接口无效或不存在时抛出

## 安全性

此 Bundle 遵循 Symfony 安全最佳实践：

- 所有服务均在服务容器中正确配置
- 不直接处理用户输入而不进行验证
- 接口解析在容器编译时执行

## 依赖关系

此 Bundle 需要：

- PHP 8.1 或更高版本
- Symfony 6.4 或更高版本
- Doctrine ORM 3.0 或更高版本
- Doctrine Bundle 2.13 或更高版本

## 许可证

该 Bundle 采用 MIT 许可证。详情请参阅 [LICENSE](LICENSE) 文件。
