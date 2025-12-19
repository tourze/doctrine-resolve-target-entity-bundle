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
 * 集成测试：使用真实的 ContainerBuilder 和 Definition，不使用 Mock。
 *
 * @internal
 */
#[CoversClass(ResolveTargetEntityPass::class)]
final class ResolveTargetEntityPassTest extends TestCase
{
    public function testProcessWithBasicMapping(): void
    {
        // 创建真实的 ContainerBuilder
        $container = new ContainerBuilder();

        // 注册 doctrine.orm.listeners.resolve_target_entity 服务
        $doctrineListenerDef = new Definition();
        $container->setDefinition('doctrine.orm.listeners.resolve_target_entity', $doctrineListenerDef);

        // 注册 ResolveTargetEntityService 服务
        $serviceDef = new Definition(ResolveTargetEntityService::class);
        $container->setDefinition(ResolveTargetEntityService::class, $serviceDef);

        // 创建并执行编译器通道
        $pass = new ResolveTargetEntityPass(
            TestInterface::class,
            TestEntity::class
        );
        $pass->process($container);

        // 验证 doctrine listener 的 methodCall
        $methodCalls = $doctrineListenerDef->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertSame('addResolveTargetEntity', $methodCalls[0][0]);
        $this->assertSame([
            TestInterface::class,
            TestEntity::class,
            [],
        ], $methodCalls[0][1]);

        // 验证 doctrine listener 的标签
        $tags = $doctrineListenerDef->getTags();
        $this->assertArrayHasKey('doctrine.event_listener', $tags);
        $this->assertCount(2, $tags['doctrine.event_listener']);
        $this->assertSame(['event' => 'loadClassMetadata'], $tags['doctrine.event_listener'][0]);
        $this->assertSame(['event' => 'onClassMetadataNotFound'], $tags['doctrine.event_listener'][1]);

        $this->assertArrayHasKey('tourze.doctrine-resolve-target-entity', $tags);
        $this->assertCount(1, $tags['tourze.doctrine-resolve-target-entity']);
        $this->assertSame([
            'original_entity' => TestInterface::class,
            'new_entity' => TestEntity::class,
        ], $tags['tourze.doctrine-resolve-target-entity'][0]);

        // 验证 ResolveTargetEntityService 的 methodCall
        $serviceMethodCalls = $serviceDef->getMethodCalls();
        $this->assertCount(1, $serviceMethodCalls);
        $this->assertSame('add', $serviceMethodCalls[0][0]);
        $this->assertSame([
            TestInterface::class,
            TestEntity::class,
        ], $serviceMethodCalls[0][1]);
    }

    public function testProcessWithCustomMapping(): void
    {
        // 自定义映射参数
        $mapping = ['fetch' => 'EAGER', 'inversedBy' => 'users'];

        // 创建真实的 ContainerBuilder
        $container = new ContainerBuilder();

        // 注册 doctrine.orm.listeners.resolve_target_entity 服务
        $doctrineListenerDef = new Definition();
        $container->setDefinition('doctrine.orm.listeners.resolve_target_entity', $doctrineListenerDef);

        // 注册 ResolveTargetEntityService 服务
        $serviceDef = new Definition(ResolveTargetEntityService::class);
        $container->setDefinition(ResolveTargetEntityService::class, $serviceDef);

        // 创建并执行编译器通道
        $pass = new ResolveTargetEntityPass(
            TestInterface::class,
            TestEntity::class,
            $mapping
        );
        $pass->process($container);

        // 验证 doctrine listener 的 methodCall 包含自定义映射
        $methodCalls = $doctrineListenerDef->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertSame('addResolveTargetEntity', $methodCalls[0][0]);
        $this->assertSame([
            TestInterface::class,
            TestEntity::class,
            $mapping,
        ], $methodCalls[0][1]);

        // 验证 doctrine listener 的标签
        $tags = $doctrineListenerDef->getTags();
        $this->assertArrayHasKey('doctrine.event_listener', $tags);
        $this->assertCount(2, $tags['doctrine.event_listener']);

        $this->assertArrayHasKey('tourze.doctrine-resolve-target-entity', $tags);
        $this->assertCount(1, $tags['tourze.doctrine-resolve-target-entity']);
        $this->assertSame([
            'original_entity' => TestInterface::class,
            'new_entity' => TestEntity::class,
        ], $tags['tourze.doctrine-resolve-target-entity'][0]);

        // 验证 ResolveTargetEntityService 的 methodCall
        $serviceMethodCalls = $serviceDef->getMethodCalls();
        $this->assertCount(1, $serviceMethodCalls);
        $this->assertSame('add', $serviceMethodCalls[0][0]);
        $this->assertSame([
            TestInterface::class,
            TestEntity::class,
        ], $serviceMethodCalls[0][1]);
    }

    public function testProcessWithExistingTag(): void
    {
        // 创建真实的 ContainerBuilder
        $container = new ContainerBuilder();

        // 注册 doctrine.orm.listeners.resolve_target_entity 服务，并预先添加 doctrine.event_listener 标签
        $doctrineListenerDef = new Definition();
        $doctrineListenerDef->addTag('doctrine.event_listener', ['event' => 'preExisting']);
        $container->setDefinition('doctrine.orm.listeners.resolve_target_entity', $doctrineListenerDef);

        // 注册 ResolveTargetEntityService 服务
        $serviceDef = new Definition(ResolveTargetEntityService::class);
        $container->setDefinition(ResolveTargetEntityService::class, $serviceDef);

        // 创建并执行编译器通道
        $pass = new ResolveTargetEntityPass(
            TestInterface::class,
            TestEntity::class
        );
        $pass->process($container);

        // 验证 doctrine listener 的 methodCall
        $methodCalls = $doctrineListenerDef->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertSame('addResolveTargetEntity', $methodCalls[0][0]);

        // 验证 doctrine listener 的标签 - 不应添加新的 doctrine.event_listener 标签
        $tags = $doctrineListenerDef->getTags();
        $this->assertArrayHasKey('doctrine.event_listener', $tags);
        // 只有预先存在的标签
        $this->assertCount(1, $tags['doctrine.event_listener']);
        $this->assertSame(['event' => 'preExisting'], $tags['doctrine.event_listener'][0]);

        // 应该添加 tourze.doctrine-resolve-target-entity 标签
        $this->assertArrayHasKey('tourze.doctrine-resolve-target-entity', $tags);
        $this->assertCount(1, $tags['tourze.doctrine-resolve-target-entity']);
        $this->assertSame([
            'original_entity' => TestInterface::class,
            'new_entity' => TestEntity::class,
        ], $tags['tourze.doctrine-resolve-target-entity'][0]);

        // 验证 ResolveTargetEntityService 的 methodCall
        $serviceMethodCalls = $serviceDef->getMethodCalls();
        $this->assertCount(1, $serviceMethodCalls);
        $this->assertSame('add', $serviceMethodCalls[0][0]);
    }
}
