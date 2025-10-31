<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineResolveTargetEntityBundle\Exception\InvalidInterfaceException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidInterfaceException::class)]
final class InvalidInterfaceExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCreationWithDefaultMessage(): void
    {
        $exception = new InvalidInterfaceException();

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(InvalidInterfaceException::class, $exception);
    }

    public function testExceptionCreationWithCustomMessage(): void
    {
        $message = 'Interface "SomeInterface" does not exist';
        $exception = new InvalidInterfaceException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionCreationWithCustomMessageAndCode(): void
    {
        $message = 'Interface "SomeInterface" does not exist';
        $code = 123;
        $exception = new InvalidInterfaceException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }
}
