<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\DoctrineResolveTargetEntityBundle\DoctrineResolveTargetEntityBundle;

/**
 * @internal
 * 这个测试用例，不允许依赖 phpunit-symfony-kernel-test
 */
#[CoversClass(DoctrineResolveTargetEntityBundle::class)]
final class DoctrineResolveTargetEntityBundleTest extends TestCase
{
    public function testBundleClass()
    {
        self::assertTrue(is_subclass_of(DoctrineResolveTargetEntityBundle::class, Bundle::class));
    }
}
