<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Testing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DoctrineResolveTargetEntityBundle\Testing\MethodBodyGenerator;

/**
 * @internal
 */
#[CoversClass(MethodBodyGenerator::class)]
final class MethodBodyGeneratorTest extends TestCase
{
    private MethodBodyGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new MethodBodyGenerator();
    }

    public function testGenerateMethodBodyForString(): void
    {
        // Use TestInterface's methods for reflection
        $interface = new \ReflectionClass(\Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestInterface::class);
        $method = $interface->getMethod('getName');
        $body = $this->generator->generateMethodBody('getName', $method->getReturnType());

        self::assertSame("return '';", $body);
    }

    public function testGenerateMethodBodyForNullableInt(): void
    {
        $interface = new \ReflectionClass(\Tourze\DoctrineResolveTargetEntityBundle\Tests\Fixtures\TestInterface::class);
        $method = $interface->getMethod('getId');
        $body = $this->generator->generateMethodBody('getId', $method->getReturnType());

        // getId() returns ?int, so it should return null
        self::assertSame('return null;', $body);
    }

    public function testGenerateMethodBodyForSpecialRolesMethod(): void
    {
        $body = $this->generator->generateMethodBody('getRoles', null);

        self::assertSame("return ['ROLE_USER'];", $body);
    }

    public function testGenerateMethodBodyForNullableType(): void
    {
        $body = $this->generator->generateMethodBody('getOptional', null);

        self::assertSame('return null;', $body);
    }
}
