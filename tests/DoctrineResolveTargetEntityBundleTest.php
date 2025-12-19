<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\DoctrineResolveTargetEntityBundle\DoctrineResolveTargetEntityBundle;


#[CoversClass(DoctrineResolveTargetEntityBundle::class)]
final class DoctrineResolveTargetEntityBundleTest extends TestCase
{
    public function testBundleClass(): void
    {
        $bundle = new DoctrineResolveTargetEntityBundle();
        self::assertInstanceOf(Bundle::class, $bundle);
    }
}
