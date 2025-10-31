<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineResolveTargetEntityBundle\DependencyInjection\DoctrineResolveTargetEntityExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineResolveTargetEntityExtension::class)]
final class DoctrineResolveTargetEntityExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private DoctrineResolveTargetEntityExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new DoctrineResolveTargetEntityExtension();
    }

    public function testExtensionCanBeCreated(): void
    {
        $this->assertInstanceOf(DoctrineResolveTargetEntityExtension::class, $this->extension);
    }

    public function testGetAlias(): void
    {
        $this->assertSame('doctrine_resolve_target_entity', $this->extension->getAlias());
    }

    public function testGetConfigDir(): void
    {
        $reflection = new \ReflectionClass($this->extension);
        $method = $reflection->getMethod('getConfigDir');
        $method->setAccessible(true);

        $configDir = $method->invoke($this->extension);
        $this->assertStringEndsWith('/Resources/config', $configDir);
    }
}
