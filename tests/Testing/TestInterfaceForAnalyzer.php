<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Testing;

interface TestInterfaceForAnalyzer
{
    public function getName(): string;

    public function getAge(): ?int;

    public function isActive(): bool;

    /**
     * @return array<int, string>
     */
    public function getRoles(): array;
}
