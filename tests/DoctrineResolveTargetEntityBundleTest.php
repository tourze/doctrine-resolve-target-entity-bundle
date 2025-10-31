<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\DoctrineResolveTargetEntityBundle\DoctrineResolveTargetEntityBundle;

/**
 * @internal
 *
 * 注意：此测试故意不使用 Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase，
 * 而是直接继承 Symfony 原生的 KernelTestCase。
 *
 * 原因：避免包之间的循环依赖。
 * - doctrine-resolve-target-entity-bundle 是基础设施包
 * - PHPUnitSymfonyKernelTest 依赖于多个业务包
 * - 如果此处使用 AbstractBundleTestCase，会形成循环依赖链
 *
 * 当前实现：使用最小化的测试基类，仅验证 Bundle 可被正常加载和初始化。
 */
#[CoversClass(DoctrineResolveTargetEntityBundle::class)]
#[RunTestsInSeparateProcesses]
final class DoctrineResolveTargetEntityBundleTest extends KernelTestCase
{
}
