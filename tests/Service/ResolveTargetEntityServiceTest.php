<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DoctrineResolveTargetEntityBundle\Exception\EntityClassNotFoundException;
use Tourze\DoctrineResolveTargetEntityBundle\Service\ResolveTargetEntityService;
use Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestEntity;
use Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestInterface;

/**
 * @internal
 */
#[CoversClass(ResolveTargetEntityService::class)]
final class ResolveTargetEntityServiceTest extends TestCase
{
    private function createResolveTargetEntityService(): ResolveTargetEntityService
    {
        return new ResolveTargetEntityService();
    }

    public function testAddWithValidClasses(): void
    {
        // 测试添加有效的接口和实体映射
        $service = $this->createResolveTargetEntityService();
        $service->add(TestInterface::class, TestEntity::class);

        $this->assertEquals(
            TestEntity::class,
            $service->findEntityClass(TestInterface::class)
        );
    }

    public function testAddOverrideExistingMapping(): void
    {
        // 测试覆盖已存在的映射
        $service = $this->createResolveTargetEntityService();
        $service->add(TestInterface::class, 'OldEntityClass');
        $service->add(TestInterface::class, TestEntity::class);

        $this->assertEquals(
            TestEntity::class,
            $service->findEntityClass(TestInterface::class)
        );
    }

    public function testFindEntityClassWithExistingMapping(): void
    {
        // 测试查找已映射的实体类
        $service = $this->createResolveTargetEntityService();
        $service->add(TestInterface::class, TestEntity::class);

        $result = $service->findEntityClass(TestInterface::class);

        $this->assertEquals(TestEntity::class, $result);
    }

    public function testFindEntityClassWithNonExistingMapping(): void
    {
        // 测试查找未映射的实体类（应抛出异常）
        $this->expectException(EntityClassNotFoundException::class);

        $service = $this->createResolveTargetEntityService();
        $service->findEntityClass('NonExistentInterface');
    }

    public function testFindEntityClassWithEmptyString(): void
    {
        // 测试使用空字符串作为参数
        $this->expectException(EntityClassNotFoundException::class);

        $service = $this->createResolveTargetEntityService();
        $service->findEntityClass('');
    }
}
