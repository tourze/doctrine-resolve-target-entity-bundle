<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Tourze\DoctrineResolveTargetEntityBundle\Exception\EntityClassNotFoundException;
use Tourze\DoctrineResolveTargetEntityBundle\Service\ResolveTargetEntityService;
use Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestEntity;
use Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestInterface;

class ResolveTargetEntityServiceTest extends TestCase
{
    private ResolveTargetEntityService $service;
    
    protected function setUp(): void
    {
        $this->service = new ResolveTargetEntityService();
    }
    
    public function testAdd_withValidClasses(): void
    {
        // 测试添加有效的接口和实体映射
        $this->service->add(TestInterface::class, TestEntity::class);
        
        $this->assertEquals(
            TestEntity::class,
            $this->service->findEntityClass(TestInterface::class)
        );
    }
    
    public function testAdd_overrideExistingMapping(): void
    {
        // 测试覆盖已存在的映射
        $this->service->add(TestInterface::class, 'OldEntityClass');
        $this->service->add(TestInterface::class, TestEntity::class);
        
        $this->assertEquals(
            TestEntity::class,
            $this->service->findEntityClass(TestInterface::class)
        );
    }
    
    public function testFindEntityClass_withExistingMapping(): void
    {
        // 测试查找已映射的实体类
        $this->service->add(TestInterface::class, TestEntity::class);
        
        $result = $this->service->findEntityClass(TestInterface::class);
        
        $this->assertEquals(TestEntity::class, $result);
    }
    
    public function testFindEntityClass_withNonExistingMapping(): void
    {
        // 测试查找未映射的实体类（应抛出异常）
        $this->expectException(EntityClassNotFoundException::class);
        
        $this->service->findEntityClass('NonExistentInterface');
    }
    
    public function testFindEntityClass_withEmptyString(): void
    {
        // 测试使用空字符串作为参数
        $this->expectException(EntityClassNotFoundException::class);
        
        $this->service->findEntityClass('');
    }
} 