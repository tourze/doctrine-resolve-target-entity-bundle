<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Service;

use Tourze\DoctrineResolveTargetEntityBundle\Exception\EntityClassNotFoundException;

class ResolveTargetEntityService
{
    private array $map = [
        // interface => entity class name
    ];

    public function add(string $interfaceName, string $entityName): void
    {
        $this->map[$interfaceName] = $entityName;
    }

    public function findEntityClass($entityClass): string
    {
        if (!isset($this->map[$entityClass])) {
            throw new EntityClassNotFoundException($entityClass);
        }
        return $this->map[$entityClass];
    }
}
