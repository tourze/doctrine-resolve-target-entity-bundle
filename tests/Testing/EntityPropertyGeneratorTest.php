<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Testing;

use Nette\PhpGenerator\ClassType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DoctrineResolveTargetEntityBundle\Testing\EntityPropertyGenerator;

/**
 * @internal
 */
#[CoversClass(EntityPropertyGenerator::class)]
final class EntityPropertyGeneratorTest extends TestCase
{
    private EntityPropertyGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new EntityPropertyGenerator();
    }

    public function testGeneratePropertyWithString(): void
    {
        $class = new ClassType('TestClass');

        $this->generator->generateProperty($class, 'title', 'string', true);

        $this->assertTrue($class->hasProperty('title'));
        $this->assertTrue($class->hasMethod('getTitle'));
        $this->assertTrue($class->hasMethod('setTitle'));
    }

    public function testGeneratePropertyWithInteger(): void
    {
        $class = new ClassType('TestClass');

        $this->generator->generateProperty($class, 'count', 'integer', false);

        $this->assertTrue($class->hasProperty('count'));
        $this->assertTrue($class->hasMethod('getCount'));
        $this->assertTrue($class->hasMethod('setCount'));
    }

    public function testGenerateConfiguredPropertyWithStringType(): void
    {
        $class = new ClassType('TestClass');

        $this->generator->generateConfiguredProperty($class, 'name', 'string');

        $this->assertTrue($class->hasProperty('name'));
        $this->assertTrue($class->hasMethod('getName'));
        $this->assertTrue($class->hasMethod('setName'));
    }

    public function testGenerateConfiguredPropertyWithArrayConfig(): void
    {
        $class = new ClassType('TestClass');

        $this->generator->generateConfiguredProperty($class, 'data', [
            'type' => 'json',
            'nullable' => false,
        ]);

        $this->assertTrue($class->hasProperty('data'));
        $this->assertTrue($class->hasMethod('getData'));
        $this->assertTrue($class->hasMethod('setData'));
    }

    public function testGenerateInterfaceProperty(): void
    {
        $class = new ClassType('TestClass');

        $this->generator->generateInterfaceProperty($class, 'user', 'UserInterface', true);

        $this->assertTrue($class->hasProperty('user'));
        $this->assertTrue($class->hasMethod('getUser'));
        $this->assertTrue($class->hasMethod('setUser'));

        $property = $class->getProperty('user');
        $this->assertEquals('UserInterface', $property->getType());
        $this->assertTrue($property->isNullable());
    }

    public function testGenerateInterfacePropertyNotNullable(): void
    {
        $class = new ClassType('TestClass');

        $this->generator->generateInterfaceProperty($class, 'admin', 'AdminInterface', false);

        $this->assertTrue($class->hasProperty('admin'));
        $this->assertTrue($class->hasMethod('getAdmin'));
        $this->assertTrue($class->hasMethod('setAdmin'));

        $property = $class->getProperty('admin');
        $this->assertEquals('AdminInterface', $property->getType());
        $this->assertFalse($property->isNullable());
    }
}
