<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Integration\Entity;

interface TestInterfaceForORM
{
    public function getId(): ?int;
    
    public function setId(int $id): self;
    
    public function getName(): string;
    
    public function setName(string $name): self;
} 