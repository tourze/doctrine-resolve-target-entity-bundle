<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures;

interface TestInterface
{
    public function getId(): ?int;

    public function getName(): string;

    public function setName(string $name): self;
}
