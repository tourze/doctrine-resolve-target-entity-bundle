<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tourze\DoctrineResolveTargetEntityBundle\DependencyInjection\Compiler\ResolveTargetEntityPass;
use Tourze\DoctrineResolveTargetEntityBundle\Service\ResolveTargetEntityService;
use Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestEntity;
use Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestInterface;

class ResolveTargetEntityPassTest extends TestCase
{
    private ContainerBuilder $containerBuilder;
    private Definition $doctrineListenerDefinition;
    private Definition $serviceDefinition;
    
    protected function setUp(): void
    {
        $this->containerBuilder = $this->createMock(ContainerBuilder::class);
        
        $this->doctrineListenerDefinition = $this->createMock(Definition::class);
        $this->serviceDefinition = $this->createMock(Definition::class);
    }
    
    public function testProcess_withBasicMapping(): void
    {
        // 准备 ContainerBuilder 的模拟行为
        $this->containerBuilder->expects($this->once())
            ->method('findDefinition')
            ->with('doctrine.orm.listeners.resolve_target_entity')
            ->willReturn($this->doctrineListenerDefinition);
        
        $this->containerBuilder->expects($this->once())
            ->method('getDefinition')
            ->with(ResolveTargetEntityService::class)
            ->willReturn($this->serviceDefinition);
        
        // 准备 Definition 的模拟行为
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('addResolveTargetEntity', [
                TestInterface::class,
                TestEntity::class,
                [],
            ]);
        
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('hasTag')
            ->with('doctrine.event_subscriber')
            ->willReturn(false);
        
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('addTag')
            ->with('doctrine.event_subscriber');
        
        $this->serviceDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('add', [
                TestInterface::class,
                TestEntity::class,
            ]);
        
        // 创建并执行编译器传递
        $pass = new ResolveTargetEntityPass(
            TestInterface::class,
            TestEntity::class
        );
        
        $pass->process($this->containerBuilder);
    }
    
    public function testProcess_withCustomMapping(): void
    {
        // 测试使用自定义映射参数
        $mapping = ['fetch' => 'EAGER', 'inversedBy' => 'users'];
        
        // 准备 ContainerBuilder 的模拟行为
        $this->containerBuilder->expects($this->once())
            ->method('findDefinition')
            ->with('doctrine.orm.listeners.resolve_target_entity')
            ->willReturn($this->doctrineListenerDefinition);
        
        $this->containerBuilder->expects($this->once())
            ->method('getDefinition')
            ->with(ResolveTargetEntityService::class)
            ->willReturn($this->serviceDefinition);
        
        // 准备 Definition 的模拟行为
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('addResolveTargetEntity', [
                TestInterface::class,
                TestEntity::class,
                $mapping,
            ]);
        
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('hasTag')
            ->with('doctrine.event_subscriber')
            ->willReturn(false);
        
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('addTag')
            ->with('doctrine.event_subscriber');
        
        $this->serviceDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('add', [
                TestInterface::class,
                TestEntity::class,
            ]);
        
        // 创建并执行编译器传递
        $pass = new ResolveTargetEntityPass(
            TestInterface::class,
            TestEntity::class,
            $mapping
        );
        
        $pass->process($this->containerBuilder);
    }
    
    public function testProcess_withExistingTag(): void
    {
        // 测试当服务已经有 doctrine.event_subscriber 标签时
        
        // 准备 ContainerBuilder 的模拟行为
        $this->containerBuilder->expects($this->once())
            ->method('findDefinition')
            ->with('doctrine.orm.listeners.resolve_target_entity')
            ->willReturn($this->doctrineListenerDefinition);
        
        $this->containerBuilder->expects($this->once())
            ->method('getDefinition')
            ->with(ResolveTargetEntityService::class)
            ->willReturn($this->serviceDefinition);
        
        // 准备 Definition 的模拟行为
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('addResolveTargetEntity', [
                TestInterface::class,
                TestEntity::class,
                [],
            ]);
        
        $this->doctrineListenerDefinition->expects($this->once())
            ->method('hasTag')
            ->with('doctrine.event_subscriber')
            ->willReturn(true);
        
        // 此时不应调用 addTag
        $this->doctrineListenerDefinition->expects($this->never())
            ->method('addTag');
        
        $this->serviceDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('add', [
                TestInterface::class,
                TestEntity::class,
            ]);
        
        // 创建并执行编译器传递
        $pass = new ResolveTargetEntityPass(
            TestInterface::class,
            TestEntity::class
        );
        
        $pass->process($this->containerBuilder);
    }
} 