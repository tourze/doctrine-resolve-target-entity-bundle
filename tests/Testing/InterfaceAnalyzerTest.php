<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Testing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DoctrineResolveTargetEntityBundle\Testing\InterfaceAnalyzer;

/**
 * @internal
 */
#[CoversClass(InterfaceAnalyzer::class)]
final class InterfaceAnalyzerTest extends TestCase
{
    private InterfaceAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new InterfaceAnalyzer();
    }

    public function testInferPropertiesFromInterface(): void
    {
        $properties = $this->analyzer->inferPropertiesFromInterface(TestInterfaceForAnalyzer::class);

        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('age', $properties);
        $this->assertArrayHasKey('active', $properties);
        $this->assertArrayHasKey('roles', $properties);

        $this->assertEquals('string', $properties['name']['type']);
        $this->assertEquals('integer', $properties['age']['type']);
        $this->assertEquals('boolean', $properties['active']['type']);
        $this->assertEquals('json', $properties['roles']['type']);

        $this->assertFalse($properties['name']['nullable']); // string 类型不允许 null
        $this->assertTrue($properties['age']['nullable']); // ?int 类型允许 null
        $this->assertFalse($properties['active']['nullable']); // bool 类型不允许 null
        $this->assertFalse($properties['roles']['nullable']); // getRoles 是特殊方法，不允许为空
    }

    public function testInferPropertiesFromNonExistentInterface(): void
    {
        $properties = $this->analyzer->inferPropertiesFromInterface('NonExistentInterface');

        $this->assertEmpty($properties);
    }
}
