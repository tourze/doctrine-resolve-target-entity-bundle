<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Tests\Testing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DoctrineResolveTargetEntityBundle\Exception\InvalidInterfaceException;
use Tourze\DoctrineResolveTargetEntityBundle\Testing\TestEntityGenerator;

/**
 * @internal
 */
#[CoversClass(TestEntityGenerator::class)]
final class TestEntityGeneratorTest extends TestCase
{
    private string $tempDir;

    private TestEntityGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/test_entity_generator_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        $this->generator = new TestEntityGenerator($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->tempDir);
    }

    public function testGenerateTestEntityWithBasicInterface(): void
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

        $this->assertStringStartsWith('DoctrineResolveTargetForTest\Entity\Test', $entityClass);
        /** @var class-string $interfaceName */
        $this->assertInstanceOf($interfaceName, new $entityClass());

        // 验证生成的类实现了接口
        /** @var class-string $entityClass */
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

    public function testGenerateTestEntityWithCustomProperties(): void
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

        /** @var class-string $interfaceName */
        $this->assertInstanceOf($interfaceName, new $entityClass());

        /** @var class-string $entityClass */
        $reflection = new \ReflectionClass($entityClass);

        // 验证所有属性都被生成
        foreach ($properties as $name => $type) {
            $this->assertTrue($reflection->hasProperty($name), "Property {$name} should exist");
            $getterName = ('boolean' === $type ? 'is' : 'get') . ucfirst($name);
            $this->assertTrue($reflection->hasMethod($getterName), "Getter {$getterName} for {$name} should exist");
            $this->assertTrue($reflection->hasMethod('set' . ucfirst($name)), "Setter for {$name} should exist");
        }
    }

    public function testGenerateTestEntityReturnsSameClassWhenCalledTwice(): void
    {
        $interfaceName = 'TestInterface_' . uniqid();
        eval("interface {$interfaceName} {}");

        $firstCall = $this->generator->generateTestEntity($interfaceName);
        $secondCall = $this->generator->generateTestEntity($interfaceName);

        $this->assertSame($firstCall, $secondCall);
    }

    public function testGenerateTestEntityWithComplexInterface(): void
    {
        $interfaceName = 'TestInterface_' . uniqid();
        $interfaceCode = "<?php
        interface {$interfaceName} {
            public function getTitle(): ?string;
            public function setTitle(?string \$title): self;
            public function isActive(): ?bool;
            public function setActive(?bool \$active): self;
            public function getCreatedAt(): ?\\DateTimeInterface;
            public function setCreatedAt(?\\DateTimeInterface \$createTime): self;
        }";

        eval(substr($interfaceCode, 5));

        $entityClass = $this->generator->generateTestEntity($interfaceName);

        /** @var class-string $entityClass */
        $reflection = new \ReflectionClass($entityClass);

        // 验证从接口推断的属性
        $this->assertTrue($reflection->hasProperty('title'));
        $this->assertTrue($reflection->hasProperty('active'));
        $this->assertTrue($reflection->hasProperty('createdAt'));

        // 创建实例并测试方法
        /** @var object $entity */
        $entity = new $entityClass();

        /** @phpstan-ignore-next-line method.notFound */
        $entity->setTitle('Test Title');
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertEquals('Test Title', $entity->getTitle());

        /** @phpstan-ignore-next-line method.notFound */
        $entity->setActive(true);
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertTrue($entity->isActive());

        $date = new \DateTime();
        /** @phpstan-ignore-next-line method.notFound */
        $entity->setCreatedAt($date);
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertSame($date, $entity->getCreatedAt());
    }

    public function testGenerateTestEntityWithSymfonyUserInterface(): void
    {
        // 创建模拟的 UserInterface 接口
        $interfaceName = 'TestUserInterface_' . uniqid();
        $interfaceCode = "<?php
        interface {$interfaceName} {
            public function getRoles(): array;
            public function getUserIdentifier(): string;
            public function getUsername(): ?string;
            public function eraseCredentials(): void;
        }";

        eval(substr($interfaceCode, 5));

        $entityClass = $this->generator->generateTestEntity($interfaceName);

        /** @var class-string $entityClass */
        $reflection = new \ReflectionClass($entityClass);

        // 验证 getRoles 方法返回 array 而不是 ?array
        $getRolesMethod = $reflection->getMethod('getRoles');
        $returnType = $getRolesMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('array', $returnType->getName());
        $this->assertFalse($returnType->allowsNull(), 'getRoles should return array, not ?array');

        // 验证 getUserIdentifier 方法返回 string 而不是 ?string
        $getUserIdentifierMethod = $reflection->getMethod('getUserIdentifier');
        $returnType = $getUserIdentifierMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('string', $returnType->getName());
        $this->assertFalse($returnType->allowsNull(), 'getUserIdentifier should return string, not ?string');

        // 验证 getUsername 方法返回 ?string（可为空）
        $getUsernameMethod = $reflection->getMethod('getUsername');
        $returnType = $getUsernameMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('string', $returnType->getName());
        $this->assertTrue($returnType->allowsNull(), 'getUsername should return ?string');

        // 验证 eraseCredentials 方法返回 void
        $eraseCredentialsMethod = $reflection->getMethod('eraseCredentials');
        $returnType = $eraseCredentialsMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('void', $returnType->getName());

        // 创建实例并测试方法
        /** @var object $entity */
        $entity = new $entityClass();

        // 测试 getRoles 返回空数组而不是 null
        /** @phpstan-ignore-next-line method.notFound */
        $roles = $entity->getRoles();
        $this->assertIsArray($roles);
        $this->assertEmpty($roles);

        // 测试 getUserIdentifier 返回空字符串而不是 null
        /** @phpstan-ignore-next-line method.notFound */
        $identifier = $entity->getUserIdentifier();
        $this->assertIsString($identifier);
        $this->assertEquals('', $identifier);

        // 测试 getUsername 可以返回 null
        /** @phpstan-ignore-next-line method.notFound */
        $username = $entity->getUsername();
        $this->assertNull($username);

        // 测试 eraseCredentials 不返回任何值
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertNull($entity->eraseCredentials());
    }

    public function testGenerateTestEntityWithMixedNullabilityInterface(): void
    {
        $interfaceName = 'TestMixedInterface_' . uniqid();
        $interfaceCode = "<?php
        interface {$interfaceName} {
            public function getRequiredString(): string;
            public function getOptionalString(): ?string;
            public function getRequiredArray(): array;
            public function getOptionalArray(): ?array;
            public function getRequiredInt(): int;
            public function getOptionalInt(): ?int;
        }";

        eval(substr($interfaceCode, 5));

        $entityClass = $this->generator->generateTestEntity($interfaceName);
        /** @var class-string $entityClass */
        $reflection = new \ReflectionClass($entityClass);

        // 验证必需的方法返回非空类型
        $requiredMethods = ['getRequiredString', 'getRequiredArray', 'getRequiredInt'];
        foreach ($requiredMethods as $methodName) {
            $method = $reflection->getMethod($methodName);
            $returnType = $method->getReturnType();
            $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
            $this->assertFalse($returnType->allowsNull(), "{$methodName} should return non-nullable type");
        }

        // 验证可选的方法返回可空类型
        $optionalMethods = ['getOptionalString', 'getOptionalArray', 'getOptionalInt'];
        foreach ($optionalMethods as $methodName) {
            $method = $reflection->getMethod($methodName);
            $returnType = $method->getReturnType();
            $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
            $this->assertTrue($returnType->allowsNull(), "{$methodName} should return nullable type");
        }

        // 测试实例行为
        /** @var object $entity */
        $entity = new $entityClass();

        // 必需的方法应该返回默认值而不是 null
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertEquals('', $entity->getRequiredString());
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertEquals([], $entity->getRequiredArray());
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertEquals(0, $entity->getRequiredInt());

        // 可选的方法应该返回 null
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertNull($entity->getOptionalString());
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertNull($entity->getOptionalArray());
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertNull($entity->getOptionalInt());
    }

    public function testGenerateTestImplementation(): void
    {
        // 创建一个测试接口
        $interfaceName = 'TestInterface_' . uniqid();
        $interfaceCode = "<?php
        interface {$interfaceName} {
            public function getName(): string;
            public function getId(): ?int;
            public function isActive(): bool;
            public function process(): void;
        }";

        eval(substr($interfaceCode, 5));

        // 测试不带自定义方法实现
        $implementation = $this->generator->generateTestImplementation($interfaceName);

        /** @var class-string $interfaceName */
        $this->assertInstanceOf($interfaceName, $implementation);

        // 验证默认实现
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertEquals('', $implementation->getName());
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertNull($implementation->getId());
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertFalse($implementation->isActive());
        /** @phpstan-ignore-next-line method.notFound */
        $this->assertNull($implementation->process());

        // 测试带自定义方法实现
        $customMethods = [
            'getName' => fn () => 'Custom Name',
        ];

        $customImplementation = $this->generator->generateTestImplementation($interfaceName, $customMethods);
        /** @var class-string $interfaceName */
        $this->assertInstanceOf($interfaceName, $customImplementation);
    }

    public function testGenerateTestImplementationWithInvalidInterface(): void
    {
        $this->expectException(InvalidInterfaceException::class);
        $this->expectExceptionMessage('Interface "NonExistentInterface" does not exist');

        $this->generator->generateTestImplementation('NonExistentInterface');
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
