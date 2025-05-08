<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\DoctrineResolveTargetEntityBundle\Service\ResolveTargetEntityService;

/**
 * @see https://stackoverflow.com/questions/44751964/doctrine-resolve-target-entities-in-custom-bundle-configuration
 */
class ResolveTargetEntityPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $originalEntity,
        private readonly string $newEntity,
        private readonly array $mapping = [],
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

        if (!$def->hasTag('doctrine.event_subscriber')) {
            $def->addTag('doctrine.event_subscriber');
        }

        $container->getDefinition(ResolveTargetEntityService::class)->addMethodCall('add', [
            $this->originalEntity,
            $this->newEntity,
        ]);
    }
}
