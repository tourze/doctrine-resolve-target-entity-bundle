<?php

namespace Tourze\DoctrineResolveTargetEntityBundle\Testing;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;

/**
 * 测试实体生成器
 * 
 * 使用 Nette PHP Generator 动态生成实现接口的测试实体
 */
class TestEntityGenerator
{
    private string $cacheDir;
    
    public function __construct(string $cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }
    
    /**
     * 为接口生成测试实体类
     * 
     * @param string $interface 接口的完全限定类名
     * @param array $properties 属性配置，格式：['propertyName' => 'doctrineType']
     * @return string 生成的实体类的完全限定类名
     */
    public function generateTestEntity(string $interface, array $properties = []): string
    {
        $className = 'Test' . basename(str_replace('\\', '/', $interface));
        $namespace = 'Test\\Entity';
        $fqcn = $namespace . '\\' . $className;
        
        // 如果类已存在，直接返回
        if (class_exists($fqcn)) {
            return $fqcn;
        }
        
        // 使用 Nette PHP Generator 创建类
        $phpNamespace = new PhpNamespace($namespace);
        $class = $phpNamespace->addClass($className);
        
        // 添加 Doctrine 属性
        $class->addAttribute('Doctrine\\ORM\\Mapping\\Entity');
        $class->addAttribute('Doctrine\\ORM\\Mapping\\Table', [
            'name' => $this->camelCaseToSnakeCase($className)
        ]);
        
        // 实现接口
        $class->addImplement($interface);
        
        // 生成 ID 属性
        $this->generateIdProperty($class);
        
        // 如果没有提供属性配置，从接口推断
        if (empty($properties)) {
            $properties = $this->inferPropertiesFromInterface($interface);
        }
        
        // 生成其他属性
        foreach ($properties as $name => $type) {
            $this->generateProperty($class, $name, $type);
        }
        
        // 保存并加载类
        $this->saveGeneratedClass($phpNamespace, $className);
        require_once $this->getClassFilePath($className);
        
        return $fqcn;
    }
    
    private function camelCaseToSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($input)));
    }
    
    private function generateIdProperty(ClassType $class): void
    {
        $id = $class->addProperty('id')
            ->setType('?int')
            ->setPrivate()
            ->setValue(null);

        $id->addAttribute('Doctrine\\ORM\\Mapping\\Id');
        $id->addAttribute('Doctrine\\ORM\\Mapping\\GeneratedValue');
        $id->addAttribute('Doctrine\\ORM\\Mapping\\Column', ['type' => 'integer']);

        $class->addMethod('getId')
            ->setReturnType('?int')
            ->setBody('return $this->id;');
    }
    
    private function inferPropertiesFromInterface(string $interface): array
    {
        $properties = [];
        
        if (!interface_exists($interface)) {
            return $properties;
        }
        
        $reflection = new \ReflectionClass($interface);
        
        foreach ($reflection->getMethods() as $method) {
            $methodName = $method->getName();
            
            // 处理 getter 方法
            if (str_starts_with($methodName, 'get') || str_starts_with($methodName, 'is')) {
                $propertyName = lcfirst(preg_replace('/^(get|is)/', '', $methodName));
                
                if (!isset($properties[$propertyName])) {
                    $returnType = $method->getReturnType();
                    if ($returnType !== null) {
                        $typeName = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : 'mixed';
                        $doctrineType = $this->mapPhpTypeToDoctrineType($typeName);
                        $properties[$propertyName] = $doctrineType;
                    }
                }
            }
        }
        
        return $properties;
    }
    
    private function mapPhpTypeToDoctrineType(string $phpType): string
    {
        return match($phpType) {
            'string' => 'string',
            'int' => 'integer',
            'bool' => 'boolean',
            'float' => 'float',
            'array' => 'json',
            '\\DateTime', '\\DateTimeInterface', '\\DateTimeImmutable' => 'datetime',
            default => 'string'
        };
    }
    
    private function generateProperty(ClassType $class, string $name, string $doctrineType): void
    {
        $phpType = $this->mapDoctrineTypeToPhpType($doctrineType);

        $property = $class->addProperty($name)
            ->setType('?' . $phpType)
            ->setPrivate()
            ->setNullable()
            ->setValue(null);

        $property->addAttribute('Doctrine\\ORM\\Mapping\\Column', [
            'type' => $doctrineType
        ]);

        // Getter
        $class->addMethod('get' . ucfirst($name))
            ->setReturnType('?' . $phpType)
            ->setBody('return $this->' . $name . ';');

        // Setter
        $setter = $class->addMethod('set' . ucfirst($name));
        $setter->addParameter($name)->setType($phpType)->setNullable();
        $setter->setBody('$this->' . $name . ' = $' . $name . ';')
            ->setReturnType('self')
            ->addBody('return $this;');
    }
    
    private function mapDoctrineTypeToPhpType(string $doctrineType): string
    {
        return match($doctrineType) {
            'string', 'text' => 'string',
            'integer', 'bigint' => 'int',
            'boolean' => 'bool',
            'datetime', 'datetime_immutable' => '\\DateTimeInterface',
            'json' => 'array',
            'decimal', 'float' => 'float',
            default => 'mixed'
        };
    }
    
    private function saveGeneratedClass(PhpNamespace $namespace, string $className): void
    {
        $printer = new PsrPrinter();
        $code = "<?php\n\n" . $printer->printNamespace($namespace);

        $dir = $this->cacheDir . '/test_entities';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($this->getClassFilePath($className), $code);
    }
    
    private function getClassFilePath(string $className): string
    {
        return $this->cacheDir . '/test_entities/' . $className . '.php';
    }
}