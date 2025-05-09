<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\DoctrineResolveTargetEntityBundle\Exception\EntityClassNotFoundException;

class EntityClassNotFoundExceptionTest extends TestCase
{
    public function testExceptionCreation_withDefaultMessage(): void
    {
        $exception = new EntityClassNotFoundException();
        
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(EntityClassNotFoundException::class, $exception);
    }
    
    public function testExceptionCreation_withCustomMessage(): void
    {
        $message = 'Custom error message';
        $exception = new EntityClassNotFoundException($message);
        
        $this->assertEquals($message, $exception->getMessage());
    }
    
    public function testExceptionCreation_withCustomMessageAndCode(): void
    {
        $message = 'Custom error message';
        $code = 123;
        $exception = new EntityClassNotFoundException($message, $code);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }
} 