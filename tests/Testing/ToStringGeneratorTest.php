<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Testing;

use Nette\PhpGenerator\ClassType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DoctrineResolveTargetEntityBundle\Testing\ToStringGenerator;

/**
 * @internal
 */
#[CoversClass(ToStringGenerator::class)]
final class ToStringGeneratorTest extends TestCase
{
    private ToStringGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ToStringGenerator();
    }

    public function testGenerateToStringMethodForNonExistentInterface(): void
    {
        $class = new ClassType('TestEntity');
        $this->generator->generateToStringMethod($class, 'NonExistentInterface');

        self::assertTrue($class->hasMethod('__toString'));
        $method = $class->getMethod('__toString');
        self::assertSame('string', $method->getReturnType());
    }

    public function testGenerateToStringMethodForInterfaceWithoutIdentifierMethod(): void
    {
        $class = new ClassType('TestEntity');
        // Using TestInterface which doesn't have identifier methods
        $this->generator->generateToStringMethod($class, \Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestInterface::class);

        self::assertTrue($class->hasMethod('__toString'));
    }

    public function testGenerateToStringMethodCreatesDefaultImplementation(): void
    {
        $class = new ClassType('TestEntity');
        $this->generator->generateToStringMethod($class, 'SomeInterface');

        self::assertTrue($class->hasMethod('__toString'));
        $method = $class->getMethod('__toString');
        self::assertStringContainsString('spl_object_hash', $method->getBody());
    }
}
