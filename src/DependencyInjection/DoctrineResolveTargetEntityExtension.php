<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class DoctrineResolveTargetEntityExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
