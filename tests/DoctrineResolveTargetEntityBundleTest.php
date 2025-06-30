<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\DoctrineResolveTargetEntityBundle\DoctrineResolveTargetEntityBundle;

class DoctrineResolveTargetEntityBundleTest extends TestCase
{
    public function testBundleCreation(): void
    {
        $bundle = new DoctrineResolveTargetEntityBundle();
        
        $this->assertInstanceOf(Bundle::class, $bundle);
        $this->assertInstanceOf(DoctrineResolveTargetEntityBundle::class, $bundle);
    }
    
    public function testGetPath(): void
    {
        $bundle = new DoctrineResolveTargetEntityBundle();
        
        $path = $bundle->getPath();
        
        $this->assertStringEndsWith('src', $path);
        $this->assertFileExists($path);
    }
} 