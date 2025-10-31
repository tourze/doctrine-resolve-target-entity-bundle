<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineResolveTargetEntityBundle\Exception\EntityClassNotFoundException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(EntityClassNotFoundException::class)]
final class EntityClassNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCreationWithDefaultMessage(): void
    {
        $exception = new EntityClassNotFoundException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(EntityClassNotFoundException::class, $exception);
    }

    public function testExceptionCreationWithCustomMessage(): void
    {
        $message = 'Custom error message';
        $exception = new EntityClassNotFoundException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionCreationWithCustomMessageAndCode(): void
    {
        $message = 'Custom error message';
        $code = 123;
        $exception = new EntityClassNotFoundException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }
}
