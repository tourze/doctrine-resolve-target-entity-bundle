<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Testing;

use PHPUnit\Framework\TestCase;
use Tourze\DoctrineResolveTargetEntityBundle\Testing\TestEntityGenerator;

class TestEntityGeneratorTest extends TestCase
{
    private string $tempDir;
    private TestEntityGenerator $generator;
    
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_entity_generator_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->generator = new TestEntityGenerator($this->tempDir);
    }
    
    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->tempDir);
    }
    
    public function testGenerateTestEntity_withBasicInterface(): void
    {
        // 创建一个测试接口
        $interfaceName = 'TestInterface_' . uniqid();
        $interfaceCode = "<?php
        interface {$interfaceName} {
            public function getName(): ?string;
            public function setName(?string \$name): self;
        }";
        
        eval(substr($interfaceCode, 5));
        
        $entityClass = $this->generator->generateTestEntity($interfaceName);
        
        $this->assertTrue(class_exists($entityClass));
        $this->assertStringStartsWith('Test\\Entity\\Test', $entityClass);
        
        // 验证生成的类实现了接口
        $reflection = new \ReflectionClass($entityClass);
        $this->assertTrue($reflection->implementsInterface($interfaceName));
        
        // 验证类有 ID 属性和方法
        $this->assertTrue($reflection->hasMethod('getId'));
        $this->assertTrue($reflection->hasProperty('id'));
        
        // 验证类有 name 属性和方法
        $this->assertTrue($reflection->hasMethod('getName'));
        $this->assertTrue($reflection->hasMethod('setName'));
        $this->assertTrue($reflection->hasProperty('name'));
    }
    
    public function testGenerateTestEntity_withCustomProperties(): void
    {
        $interfaceName = 'TestInterface_' . uniqid();
        eval("interface {$interfaceName} {}");
        
        $properties = [
            'title' => 'string',
            'count' => 'integer',
            'isActive' => 'boolean',
            'createdAt' => 'datetime',
        ];
        
        $entityClass = $this->generator->generateTestEntity($interfaceName, $properties);
        
        $this->assertTrue(class_exists($entityClass));
        
        $reflection = new \ReflectionClass($entityClass);
        
        // 验证所有属性都被生成
        foreach ($properties as $name => $type) {
            $this->assertTrue($reflection->hasProperty($name), "Property {$name} should exist");
            $getterName = ($type === 'boolean' ? 'is' : 'get') . ucfirst($name);
            $this->assertTrue($reflection->hasMethod($getterName), "Getter {$getterName} for {$name} should exist");
            $this->assertTrue($reflection->hasMethod('set' . ucfirst($name)), "Setter for {$name} should exist");
        }
    }
    
    public function testGenerateTestEntity_returnsSameClassWhenCalledTwice(): void
    {
        $interfaceName = 'TestInterface_' . uniqid();
        eval("interface {$interfaceName} {}");
        
        $firstCall = $this->generator->generateTestEntity($interfaceName);
        $secondCall = $this->generator->generateTestEntity($interfaceName);
        
        $this->assertSame($firstCall, $secondCall);
    }
    
    public function testGenerateTestEntity_withComplexInterface(): void
    {
        $interfaceName = 'TestInterface_' . uniqid();
        $interfaceCode = "<?php
        interface {$interfaceName} {
            public function getTitle(): ?string;
            public function setTitle(?string \$title): self;
            public function isActive(): ?bool;
            public function setActive(?bool \$active): self;
            public function getCreatedAt(): ?\\DateTimeInterface;
            public function setCreatedAt(?\\DateTimeInterface \$createdAt): self;
        }";
        
        eval(substr($interfaceCode, 5));
        
        $entityClass = $this->generator->generateTestEntity($interfaceName);
        
        $reflection = new \ReflectionClass($entityClass);
        
        // 验证从接口推断的属性
        $this->assertTrue($reflection->hasProperty('title'));
        $this->assertTrue($reflection->hasProperty('active'));
        $this->assertTrue($reflection->hasProperty('createdAt'));
        
        // 创建实例并测试方法
        $entity = new $entityClass();
        
        $entity->setTitle('Test Title');
        $this->assertEquals('Test Title', $entity->getTitle());
        
        $entity->setActive(true);
        $this->assertTrue($entity->isActive());
        
        $date = new \DateTime();
        $entity->setCreatedAt($date);
        $this->assertSame($date, $entity->getCreatedAt());
    }
    
    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}