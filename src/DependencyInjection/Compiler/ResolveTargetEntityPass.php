<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\DoctrineResolveTargetEntityBundle\Service\ResolveTargetEntityService;

/**
 * @see https://stackoverflow.com/questions/44751964/doctrine-resolve-target-entities-in-custom-bundle-configuration
 */
final readonly class ResolveTargetEntityPass implements CompilerPassInterface
{
    public function __construct(
        private string $originalEntity,
        private string $newEntity,
        /** @var array<string, mixed> */
        private array $mapping = [],
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $def = $container->findDefinition('doctrine.orm.listeners.resolve_target_entity');

        $def->addMethodCall('addResolveTargetEntity', [
            $this->originalEntity,
            $this->newEntity,
            $this->mapping,
        ]);

        // 根据 Doctrine Bundle 的标准实现，应该使用 doctrine.event_listener 而不是 doctrine.event_subscriber
        if (!$def->hasTag('doctrine.event_listener')) {
            $def->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
            $def->addTag('doctrine.event_listener', ['event' => 'onClassMetadataNotFound']);
        }

        $def->addTag('tourze.doctrine-resolve-target-entity', [
            'original_entity' => $this->originalEntity,
            'new_entity' => $this->newEntity,
        ]);

        $container->getDefinition(ResolveTargetEntityService::class)->addMethodCall('add', [
            $this->originalEntity,
            $this->newEntity,
        ]);
    }
}
