<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Testing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\DoctrineResolveTargetEntityBundle\DependencyInjection\Compiler\ResolveTargetEntityPass;
use Tourze\DoctrineResolveTargetEntityBundle\Testing\TestKernelHelper;

/**
 * @internal
 */
#[CoversClass(TestKernelHelper::class)]
final class TestKernelHelperTest extends TestCase
{
    public function testCreateResolveTargetCallback(): void
    {
        $mappings = [
            'App\Interface\UserInterface' => 'App\Entity\User',
            'App\Interface\ProductInterface' => 'App\Entity\Product',
        ];

        $callback = TestKernelHelper::createResolveTargetCallback($mappings);

        $this->assertInstanceOf(\Closure::class, $callback);

        /*
         * 为什么使用具体类 ContainerBuilder 而不是接口：
         * 1. Symfony DI 系统中，ContainerBuilder 是主要的构建器实现，没有合适的接口可以抽象它的所有方法
         * 2. 这种使用是必要的，因为测试需要模拟 addCompilerPass 方法
         * 3. ContainerBuilder 是 Symfony 框架的稳定 API，可以安全地用于测试
         */
        $container = $this->createMock(ContainerBuilder::class);

        // 验证会添加正确数量的编译器通过
        $container->expects($this->exactly(2))
            ->method('addCompilerPass')
            ->willReturnCallback(function ($pass, $type, $priority) use ($mappings, $container) {
                $this->assertInstanceOf(ResolveTargetEntityPass::class, $pass);

                // 使用反射获取私有属性来验证
                $reflection = new \ReflectionClass($pass);
                $originalEntityProp = $reflection->getProperty('originalEntity');
                $originalEntityProp->setAccessible(true);
                $newEntityProp = $reflection->getProperty('newEntity');
                $newEntityProp->setAccessible(true);

                $originalEntity = $originalEntityProp->getValue($pass);
                $newEntity = $newEntityProp->getValue($pass);

                // 断言反射读取到的键名为字符串，避免 mixed 作为数组键
                $this->assertIsString($originalEntity);
                $this->assertContains($originalEntity, array_keys($mappings));
                $this->assertEquals($mappings[$originalEntity], $newEntity);

                return $container;
            })
        ;

        // 执行回调
        $callback($container);
    }

    public function testGetStandardEntityMappings(): void
    {
        $testDir = '/path/to/test';
        $mappings = TestKernelHelper::getStandardEntityMappings($testDir);

        $this->assertArrayHasKey('Test\Entity', $mappings);
        $this->assertEquals($testDir . '/Fixtures/Entity', $mappings['Test\Entity']);
    }

    public function testCreateDoctrineConfiguratorWithDoctrineExtension(): void
    {
        $namespace = 'App\Entity';
        $directory = '/path/to/entities';

        $callback = TestKernelHelper::createDoctrineConfigurator($namespace, $directory);

        $this->assertInstanceOf(\Closure::class, $callback);

        /*
         * 为什么使用具体类 ContainerBuilder 而不是接口：
         * 1. Symfony DI 系统中，ContainerBuilder 是主要的构建器实现，没有合适的接口可以抽象它的所有方法
         * 2. 这种使用是必要的，因为测试需要模拟 addCompilerPass 方法
         * 3. ContainerBuilder 是 Symfony 框架的稳定 API，可以安全地用于测试
         */
        $container = $this->createMock(ContainerBuilder::class);

        // 模拟有 doctrine 扩展
        $container->expects($this->once())
            ->method('hasExtension')
            ->with('doctrine')
            ->willReturn(true)
        ;

        // 验证会调用 prependExtensionConfig
        $container->expects($this->once())
            ->method('prependExtensionConfig')
            ->with('doctrine', [
                'orm' => [
                    'mappings' => [
                        $namespace => [
                            'type' => 'attribute',
                            'dir' => $directory,
                            'prefix' => $namespace,
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ])
        ;

        // 执行回调
        $callback($container);
    }

    public function testCreateDoctrineConfiguratorWithoutDoctrineExtension(): void
    {
        $namespace = 'App\Entity';
        $directory = '/path/to/entities';

        $callback = TestKernelHelper::createDoctrineConfigurator($namespace, $directory);

        /*
         * 为什么使用具体类 ContainerBuilder 而不是接口：
         * 1. Symfony DI 系统中，ContainerBuilder 是主要的构建器实现，没有合适的接口可以抽象它的所有方法
         * 2. 这种使用是必要的，因为测试需要模拟 addCompilerPass 方法
         * 3. ContainerBuilder 是 Symfony 框架的稳定 API，可以安全地用于测试
         */
        $container = $this->createMock(ContainerBuilder::class);

        // 模拟没有 doctrine 扩展
        $container->expects($this->once())
            ->method('hasExtension')
            ->with('doctrine')
            ->willReturn(false)
        ;

        // 不应该调用 prependExtensionConfig
        $container->expects($this->never())
            ->method('prependExtensionConfig')
        ;

        // 执行回调
        $callback($container);
    }

    public function testCreateDynamicEntitiesCallback(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_kernel_helper_' . uniqid();
        mkdir($cacheDir, 0o777, true);

        try {
            // 创建测试接口
            $interfaceName = 'TestDynamicInterface_' . uniqid();
            eval("interface {$interfaceName} {}");

            $interfaceConfigs = [
                $interfaceName => [
                    'properties' => [
                        'name' => 'string',
                        'age' => 'integer',
                    ],
                ],
            ];

            $callback = TestKernelHelper::createDynamicEntitiesCallback($interfaceConfigs, $cacheDir);

            $this->assertInstanceOf(\Closure::class, $callback);

            /*
             * 为什么使用具体类 ContainerBuilder 而不是接口：
             * 1. Symfony DI 系统中，ContainerBuilder 是主要的构建器实现，没有合适的接口可以抽象它的所有方法
             * 2. 这种使用是必要的，因为测试需要模拟 addCompilerPass 方法
             * 3. ContainerBuilder 是 Symfony 框架的稳定 API，可以安全地用于测试
             */
            $container = $this->createMock(ContainerBuilder::class);

            // 验证会添加编译器通过
            $container->expects($this->once())
                ->method('addCompilerPass')
                ->willReturnCallback(function ($pass, $type, $priority) use ($interfaceName, $container) {
                    $this->assertInstanceOf(ResolveTargetEntityPass::class, $pass);

                    // 使用反射验证接口名称
                    $reflection = new \ReflectionClass($pass);
                    $originalEntityProp = $reflection->getProperty('originalEntity');
                    $originalEntityProp->setAccessible(true);

                    $this->assertEquals($interfaceName, $originalEntityProp->getValue($pass));

                    return $container;
                })
            ;

            // 执行回调
            $callback($container);
        } finally {
            // 清理临时目录
            $this->recursiveRemoveDirectory($cacheDir);
        }
    }

    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
