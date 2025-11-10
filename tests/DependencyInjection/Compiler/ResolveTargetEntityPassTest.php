<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tourze\DoctrineResolveTargetEntityBundle\DependencyInjection\Compiler\ResolveTargetEntityPass;
use Tourze\DoctrineResolveTargetEntityBundle\Service\ResolveTargetEntityService;
use Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestEntity;
use Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestInterface;

/**
 * @internal
 */
#[CoversClass(ResolveTargetEntityPass::class)]
final class ResolveTargetEntityPassTest extends TestCase
{
    /**
     * @var ContainerBuilder&\PHPUnit\Framework\MockObject\MockObject
     */
    private ContainerBuilder $containerBuilder;

    /**
     * @var Definition&\PHPUnit\Framework\MockObject\MockObject
     */
    private Definition $doctrineListenerDefinition;

    /**
     * @var Definition&\PHPUnit\Framework\MockObject\MockObject
     */
    private Definition $serviceDefinition;

    protected function setUp(): void
    {
        parent::setUp();
        /*
         * 为什么使用具体类 ContainerBuilder 而不是接口：
         * 1. Symfony DI 系统中，ContainerBuilder 是主要的构建器实现，没有合适的接口可以抽象它的所有方法
         * 2. 这种使用是必要的，因为测试需要模拟 findDefinition、getDefinition 等方法
         * 3. ContainerBuilder 是 Symfony 框架的稳定 API，可以安全地用于测试
         */
        $this->containerBuilder = $this->createMock(ContainerBuilder::class);

        /*
         * 为什么使用具体类 Definition 而不是接口：
         * 1. Definition 是 Symfony DI 中服务定义的核心类，没有对应的接口
         * 2. 这种使用是必要的，因为测试需要模拟 addMethodCall、addTag、hasTag 等方法
         * 3. Definition 是 Symfony 框架的稳定 API，可以安全地用于测试
         */
        $this->doctrineListenerDefinition = $this->createMock(Definition::class);
        /*
         * 使用具体类 Definition 的原因同上
         */
        $this->serviceDefinition = $this->createMock(Definition::class);
    }

    public function testProcessWithBasicMapping(): void
    {
        // 准备 ContainerBuilder 的模拟行为
        $this->containerBuilder->expects($this->once())
            ->method('findDefinition')
            ->with('doctrine.orm.listeners.resolve_target_entity')
            ->willReturn($this->doctrineListenerDefinition)
        ;

        $this->containerBuilder->expects($this->once())
            ->method('getDefinition')
            ->with(ResolveTargetEntityService::class)
            ->willReturn($this->serviceDefinition)
        ;

        // 准备 Definition 的模拟行为
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('addResolveTargetEntity', [
                TestInterface::class,
                TestEntity::class,
                [],
            ])
        ;

        $this->doctrineListenerDefinition->expects($this->once())
            ->method('hasTag')
            ->with('doctrine.event_listener')
            ->willReturn(false)
        ;

        // 使用变量记录调用次数
        $addTagCallCount = 0;
        $this->doctrineListenerDefinition->expects($this->exactly(3))
            ->method('addTag')
            ->willReturnCallback(function ($tag, $options = []) use (&$addTagCallCount) {
                ++$addTagCallCount;
                if (1 === $addTagCallCount) {
                    $this->assertEquals('doctrine.event_listener', $tag);
                    $this->assertEquals(['event' => 'loadClassMetadata'], $options);
                } elseif (2 === $addTagCallCount) {
                    $this->assertEquals('doctrine.event_listener', $tag);
                    $this->assertEquals(['event' => 'onClassMetadataNotFound'], $options);
                } elseif (3 === $addTagCallCount) {
                    $this->assertEquals('tourze.doctrine-resolve-target-entity', $tag);
                    $this->assertEquals([
                        'original_entity' => TestInterface::class,
                        'new_entity' => TestEntity::class,
                    ], $options);
                }

                return $this->doctrineListenerDefinition;
            })
        ;

        $this->serviceDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('add', [
                TestInterface::class,
                TestEntity::class,
            ])
        ;

        // 创建并执行编译器传递
        $pass = new ResolveTargetEntityPass(
            TestInterface::class,
            TestEntity::class
        );

        $pass->process($this->containerBuilder);
    }

    public function testProcessWithCustomMapping(): void
    {
        // 测试使用自定义映射参数
        $mapping = ['fetch' => 'EAGER', 'inversedBy' => 'users'];

        // 准备 ContainerBuilder 的模拟行为
        $this->containerBuilder->expects($this->once())
            ->method('findDefinition')
            ->with('doctrine.orm.listeners.resolve_target_entity')
            ->willReturn($this->doctrineListenerDefinition)
        ;

        $this->containerBuilder->expects($this->once())
            ->method('getDefinition')
            ->with(ResolveTargetEntityService::class)
            ->willReturn($this->serviceDefinition)
        ;

        // 准备 Definition 的模拟行为
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('addResolveTargetEntity', [
                TestInterface::class,
                TestEntity::class,
                $mapping,
            ])
        ;

        $this->doctrineListenerDefinition->expects($this->once())
            ->method('hasTag')
            ->with('doctrine.event_listener')
            ->willReturn(false)
        ;

        // 使用变量记录调用次数
        $addTagCallCount = 0;
        $this->doctrineListenerDefinition->expects($this->exactly(3))
            ->method('addTag')
            ->willReturnCallback(function ($tag, $options = []) use (&$addTagCallCount) {
                ++$addTagCallCount;
                if (1 === $addTagCallCount) {
                    $this->assertEquals('doctrine.event_listener', $tag);
                    $this->assertEquals(['event' => 'loadClassMetadata'], $options);
                } elseif (2 === $addTagCallCount) {
                    $this->assertEquals('doctrine.event_listener', $tag);
                    $this->assertEquals(['event' => 'onClassMetadataNotFound'], $options);
                } elseif (3 === $addTagCallCount) {
                    $this->assertEquals('tourze.doctrine-resolve-target-entity', $tag);
                    $this->assertEquals([
                        'original_entity' => TestInterface::class,
                        'new_entity' => TestEntity::class,
                    ], $options);
                }

                return $this->doctrineListenerDefinition;
            })
        ;

        $this->serviceDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('add', [
                TestInterface::class,
                TestEntity::class,
            ])
        ;

        // 创建并执行编译器传递
        $pass = new ResolveTargetEntityPass(
            TestInterface::class,
            TestEntity::class,
            $mapping
        );

        $pass->process($this->containerBuilder);
    }

    public function testProcessWithExistingTag(): void
    {
        // 测试当服务已经有 doctrine.event_subscriber 标签时

        // 准备 ContainerBuilder 的模拟行为
        $this->containerBuilder->expects($this->once())
            ->method('findDefinition')
            ->with('doctrine.orm.listeners.resolve_target_entity')
            ->willReturn($this->doctrineListenerDefinition)
        ;

        $this->containerBuilder->expects($this->once())
            ->method('getDefinition')
            ->with(ResolveTargetEntityService::class)
            ->willReturn($this->serviceDefinition)
        ;

        // 准备 Definition 的模拟行为
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('addResolveTargetEntity', [
                TestInterface::class,
                TestEntity::class,
                [],
            ])
        ;

        $this->doctrineListenerDefinition->expects($this->once())
            ->method('hasTag')
            ->with('doctrine.event_listener')
            ->willReturn(true)
        ;

        // 此时只应调用 addTag 一次（用于添加 tourze.doctrine-resolve-target-entity 标签）
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('addTag')
            ->with('tourze.doctrine-resolve-target-entity', [
                'original_entity' => TestInterface::class,
                'new_entity' => TestEntity::class,
            ])
        ;

        $this->serviceDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('add', [
                TestInterface::class,
                TestEntity::class,
            ])
        ;

        // 创建并执行编译器传递
        $pass = new ResolveTargetEntityPass(
            TestInterface::class,
            TestEntity::class
        );

        $pass->process($this->containerBuilder);
    }
}
