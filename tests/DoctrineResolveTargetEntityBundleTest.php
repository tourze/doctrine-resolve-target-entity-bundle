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
 *
 * @phpstan-ignore phpat.bundleTestMustExtendsAbstractBundleTestCase (intentionally not using AbstractBundleTestCase to avoid dependency)
 */
#[CoversClass(DoctrineResolveTargetEntityBundle::class)]
final class DoctrineResolveTargetEntityBundleTest extends TestCase
{
    public function testBundleClass(): void
    {
        $bundle = new DoctrineResolveTargetEntityBundle();
        // @phpstan-ignore staticMethod.alreadyNarrowedType (this test verifies Bundle inheritance)
        self::assertInstanceOf(Bundle::class, $bundle);
    }
}
