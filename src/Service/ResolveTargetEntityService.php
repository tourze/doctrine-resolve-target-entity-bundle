<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Service;

use Tourze\DoctrineResolveTargetEntityBundle\Exception\EntityClassNotFoundException;

class ResolveTargetEntityService
{
    /** @var array<string, string> */
    private array $map = [
        // interface => entity class name
    ];

    public function add(string $interfaceName, string $entityName): void
    {
        $this->map[$interfaceName] = $entityName;
    }

    public function findEntityClass(string $entityClass): string
    {
        if (!isset($this->map[$entityClass])) {
            throw new EntityClassNotFoundException($entityClass);
        }

        return $this->map[$entityClass];
    }
}
