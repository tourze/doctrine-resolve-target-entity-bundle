<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineResolveTargetEntityBundle\DoctrineResolveTargetEntityBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineResolveTargetEntityBundle::class)]
#[RunTestsInSeparateProcesses]
final class DoctrineResolveTargetEntityBundleTest extends AbstractBundleTestCase
{
}
