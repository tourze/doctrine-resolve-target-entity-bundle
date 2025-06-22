<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Testing;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\DoctrineResolveTargetEntityBundle\DependencyInjection\Compiler\ResolveTargetEntityPass;

/**
 * 测试内核助手类
 * 
 * 提供回调函数来简化 resolve target entity 测试配置，避免包之间的直接依赖
 */
class TestKernelHelper
{
    /**
     * 创建用于配置 resolve target entity 映射的回调函数
     *
     * @param array $resolveTargetMappings 接口到实体的映射
     * @return \Closure 返回用于 IntegrationTestKernel containerBuilder 参数的回调
     */
    public static function createResolveTargetCallback(array $resolveTargetMappings): \Closure
    {
        return function (ContainerBuilder $container) use ($resolveTargetMappings) {
            // 注册 resolve target entity 映射
            foreach ($resolveTargetMappings as $interface => $entity) {
                $container->addCompilerPass(
                    new ResolveTargetEntityPass($interface, $entity),
                    PassConfig::TYPE_BEFORE_OPTIMIZATION,
                    1000
                );
            }
        };
    }

    /**
     * 为测试添加标准的实体映射目录
     *
     * @param string $testDir 测试目录，通常是 __DIR__
     * @return array
     */
    public static function getStandardEntityMappings(string $testDir): array
    {
        return [
            'Test\\Entity' => $testDir . '/Fixtures/Entity',
        ];
    }

    /**
     * 为测试配置 Doctrine 映射的回调
     *
     * @param string $namespace 命名空间
     * @param string $directory 目录路径
     * @return \Closure
     */
    public static function createDoctrineConfigurator(string $namespace, string $directory): \Closure
    {
        return function (ContainerBuilder $container) use ($namespace, $directory) {
            if ($container->hasExtension('doctrine')) {
                $container->prependExtensionConfig('doctrine', [
                    'orm' => [
                        'mappings' => [
                            $namespace => [
                                'type' => 'attribute',
                                'dir' => $directory,
                                'prefix' => $namespace,
                                'is_bundle' => false,
                            ]
                        ]
                    ]
                ]);
            }
        };
    }

    /**
     * 创建用于动态实体生成的回调函数
     *
     * @param array $interfaceConfigs 接口配置，格式：[InterfaceClass => ['properties' => [...]]]
     * @param string $cacheDir 缓存目录，通常来自内核的getCacheDir()
     * @return \Closure 返回用于 IntegrationTestKernel containerBuilder 参数的回调
     */
    public static function createDynamicEntitiesCallback(array $interfaceConfigs, string $cacheDir): \Closure
    {
        return function (ContainerBuilder $container) use ($interfaceConfigs, $cacheDir) {
            $generator = new TestEntityGenerator($cacheDir);

            foreach ($interfaceConfigs as $interface => $config) {
                $entityClass = $generator->generateTestEntity($interface, $config['properties'] ?? []);

                $container->addCompilerPass(
                    new ResolveTargetEntityPass($interface, $entityClass),
                    PassConfig::TYPE_BEFORE_OPTIMIZATION,
                    1000
                );
            }
        };
    }
}
