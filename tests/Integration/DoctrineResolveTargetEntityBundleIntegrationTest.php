<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineResolveTargetEntityBundleIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return IntegrationTestKernel::class;
    }
    
    /**
     * 简单测试内核是否可以引导
     */
    public function testKernelBootstrap(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());
    }
} 