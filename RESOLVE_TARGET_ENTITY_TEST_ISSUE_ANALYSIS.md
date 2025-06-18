# Doctrine Resolve Target Entity Bundle 测试解决方案

## 问题概述

在使用 `doctrine-resolve-target-entity-bundle` 实现模块解耦时，测试场景遇到的核心问题：

- **生产环境**：模块 A 依赖接口，模块 B 提供实现，通过 `ResolveTargetEntityPass` 注册映射 ✅
- **测试环境**：模块 A 独立测试时找不到接口的具体实现，Doctrine 无法启动 ❌

## 解决方案：动态生成测试实体

使用 Nette PHP Generator 动态生成实现接口的测试实体，零维护成本，完全自动化。

### 基本用法

```php
use Tourze\DoctrineResolveTargetEntityBundle\Testing\TestKernelHelper;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class UserModuleTest extends KernelTestCase
{
    protected static function createKernel(array $options = []): IntegrationTestKernel
    {
        $kernel = new IntegrationTestKernel(
            'test',
            true,
            [
                FrameworkBundle::class => ['all' => true],
                DoctrineBundle::class => ['all' => true],
                UserModuleBundle::class => ['all' => true],
            ],
            [],
            TestKernelHelper::createDoctrineConfigurator('Test\\Entity', sys_get_temp_dir() . '/test_entities'),
            TestKernelHelper::createDynamicEntitiesCallback(
                [
                    UserInterface::class => [
                        'properties' => [
                            'username' => 'string',
                            'email' => 'string',
                            'roles' => 'json'
                        ]
                    ],
                    CompanyInterface::class => [
                        'properties' => [
                            'name' => 'string',
                            'registrationNumber' => 'string'
                        ]
                    ]
                ],
                sys_get_temp_dir() . '/test_entities_cache'
            )
        );
        return $kernel;
    }
    
    public function testUserCreation(): void
    {
        $this->bootKernel();
        $em = static::getContainer()->get('doctrine')->getManager();
        
        // 动态生成的实体可以正常使用
        $userClass = 'Test\\Entity\\TestUserInterface';
        $user = new $userClass();
        $user->setUsername('test');
        $user->setEmail('test@example.com');
        
        $em->persist($user);
        $em->flush();
        
        $this->assertNotNull($user->getId());
    }
}
```

### 智能推断（零配置）

如果接口定义了标准的 getter 方法，生成器可以自动推断属性：

```php
// 接口有标准的 getter，无需手动配置属性
interface UserInterface
{
    public function getId(): ?int;
    public function getUsername(): string;
    public function getEmail(): string;
    public function getRoles(): array;
}

// 测试中可以省略属性配置
class AutoInferTest extends KernelTestCase
{
    protected static function createKernel(array $options = []): IntegrationTestKernel
    {
        return new IntegrationTestKernel(
            'test',
            true,
            $bundles,
            [],
            TestKernelHelper::createDoctrineConfigurator('Test\\Entity', sys_get_temp_dir() . '/test_entities'),
            TestKernelHelper::createDynamicEntitiesCallback(
                [
                    UserInterface::class => [], // 空配置，自动推断
                    CompanyInterface::class => [], // 空配置，自动推断
                ],
                sys_get_temp_dir() . '/test_entities_cache'
            )
        );
    }
}
```

### 团队标准化基类

```php
use Tourze\DoctrineResolveTargetEntityBundle\Testing\TestKernelHelper;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

abstract class AbstractResolveTargetTest extends KernelTestCase
{
    protected static function getInterfaceConfigs(): array
    {
        return [
            UserInterface::class => [],
            CompanyInterface::class => [],
            OrderInterface::class => [],
        ];
    }
    
    protected static function createKernel(array $options = []): IntegrationTestKernel
    {
        return new IntegrationTestKernel(
            'test',
            true,
            static::getBundles(),
            [],
            TestKernelHelper::createDoctrineConfigurator('Test\\Entity', sys_get_temp_dir() . '/test_entities'),
            TestKernelHelper::createDynamicEntitiesCallback(
                static::getInterfaceConfigs(),
                sys_get_temp_dir() . '/test_entities_cache'
            )
        );
    }
    
    abstract protected static function getBundles(): array;
}

// 具体测试继承基类，超级简洁
class UserModuleTest extends AbstractResolveTargetTest
{
    protected static function getBundles(): array
    {
        return [
            FrameworkBundle::class => ['all' => true],
            DoctrineBundle::class => ['all' => true],
            UserModuleBundle::class => ['all' => true],
        ];
    }
    
    public function testUserCreation(): void
    {
        $this->bootKernel();
        $em = static::getContainer()->get('doctrine')->getManager();
        
        $userClass = 'Test\\Entity\\TestUserInterface';
        $user = new $userClass();
        $user->setUsername('test')->setEmail('test@example.com');
        
        $em->persist($user);
        $em->flush();
        
        $this->assertNotNull($user->getId());
    }
}
```

## 生成的实体特性

动态生成的测试实体具有以下特性：

### 自动添加的 Doctrine 注解
```php
#[ORM\Entity]
#[ORM\Table(name: 'test_user_interface')]
class TestUserInterface implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private ?string $username = null;

    // 自动生成 getter/setter 方法
    public function getId(): ?int { return $this->id; }
    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $username): self { 
        $this->username = $username; 
        return $this; 
    }
}
```

### 类型映射支持
- `string` → `string`
- `int` → `integer` 
- `bool` → `boolean`
- `array` → `json`
- `\DateTime` → `datetime`
- `float` → `float`

## API 参考

### TestKernelHelper 方法

- `createDynamicEntitiesCallback(interfaceConfigs, cacheDir)` - 创建动态实体生成回调
- `createDoctrineConfigurator(namespace, directory)` - 创建 Doctrine 配置回调

### TestEntityGenerator 方法

- `generateTestEntity(interface, properties)` - 生成测试实体类
- 自动推断接口属性
- 支持标准的 Doctrine 类型映射

## 最佳实践

### 1. 接口设计
使用标准的 getter/setter 模式，便于自动推断：

```php
interface UserInterface
{
    public function getId(): ?int;
    public function getUsername(): string;
    public function setUsername(string $username): self;
    public function getEmail(): string;
    public function setEmail(string $email): self;
}
```

### 2. 测试数据创建
利用流式接口创建测试数据：

```php
public function testUserWorkflow(): void
{
    $this->bootKernel();
    $em = static::getContainer()->get('doctrine')->getManager();
    
    $userClass = 'Test\\Entity\\TestUserInterface';
    $user = (new $userClass())
        ->setUsername('john_doe')
        ->setEmail('john@example.com');
    
    $em->persist($user);
    $em->flush();
    
    $this->assertEquals('john_doe', $user->getUsername());
}
```

### 3. 缓存目录管理
建议使用系统临时目录，自动清理：

```php
$cacheDir = sys_get_temp_dir() . '/test_entities_' . uniqid();
```

## 总结

动态生成测试实体的方案具有以下优势：

1. **零维护成本** - 无需手动创建和维护测试实体文件
2. **自动类型推断** - 从接口自动推断属性和类型
3. **完整的 Doctrine 支持** - 自动添加必要的注解和映射
4. **团队标准化** - 通过基类统一测试配置
5. **无包依赖** - 不依赖其他包，保持模块独立性

这个方案完全解决了 resolve target entity 测试问题，让模块 A 可以独立测试，同时保持代码的简洁性和可维护性。

---

**文档版本**：6.0  
**更新日期**：2025年01月27日  
**适用版本**：doctrine-resolve-target-entity-bundle 1.0+