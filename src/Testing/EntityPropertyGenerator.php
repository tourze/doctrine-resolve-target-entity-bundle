<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Testing;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

/**
 * 实体属性生成器
 *
 * 负责为动态生成的实体类添加属性、getter 和 setter 方法
 *
 * @phpstan-type PropertyConfig array{name: string, type: string, defaultValue: mixed, doctrineType: string}
 * @phpstan-type GetterConfig array{0: string, 1: string, 2: string}
 */
class EntityPropertyGenerator
{
    public function generateProperty(ClassType $class, string $name, string $doctrineType, bool $nullable = true): void
    {
        if ($class->hasProperty($name)) {
            return;
        }
        $this->addPropertyToClass($class, $name, $doctrineType, $nullable);
        $this->addGetterMethod($class, $name, $doctrineType, $nullable);
        $this->addSetterMethod($class, $name, $doctrineType, $nullable);
    }

    /**
     * 生成接口类型属性（不持久化到数据库）
     */
    public function generateInterfaceProperty(ClassType $class, string $name, string $interfaceType, bool $nullable = true): void
    {
        $this->addInterfacePropertyToClass($class, $name, $interfaceType, $nullable);
        $this->addInterfaceGetterMethod($class, $name, $interfaceType, $nullable);
        $this->addInterfaceSetterMethod($class, $name, $interfaceType, $nullable);
    }

    /**
     * @param array{type: string, nullable?: bool, is_interface?: bool}|string $propertyConfig
     */
    public function generateConfiguredProperty(ClassType $class, string $name, array|string $propertyConfig): void
    {
        if (is_string($propertyConfig)) {
            $this->generateProperty($class, $name, $propertyConfig);
        } else {
            // 如果是接口类型，生成接口属性
            $isInterface = true === ($propertyConfig['is_interface'] ?? false);
            $nullable = (bool) ($propertyConfig['nullable'] ?? true);
            $type = $propertyConfig['type'];

            if ($isInterface) {
                $this->generateInterfaceProperty($class, $name, $type, $nullable);
            } else {
                $this->generateProperty($class, $name, $type, $nullable);
            }
        }
    }

    private function addPropertyToClass(ClassType $class, string $name, string $doctrineType, bool $nullable): void
    {
        $propertyConfig = $this->buildClassPropertyConfig($name, $doctrineType, $nullable);
        $this->createClassProperty($class, $propertyConfig);
    }

    /**
     * @return PropertyConfig
     */
    private function buildClassPropertyConfig(string $name, string $doctrineType, bool $nullable): array
    {
        $phpType = $this->mapDoctrineTypeToPhpType($doctrineType);
        $propertyType = $nullable ? ('?' . $phpType) : $phpType;
        $defaultValue = $nullable ? null : $this->getDefaultValueForType($phpType);

        return [
            'name' => $name,
            'type' => $propertyType,
            'defaultValue' => $defaultValue,
            'doctrineType' => $doctrineType,
        ];
    }

    /**
     * @param PropertyConfig $config
     */
    private function createClassProperty(ClassType $class, array $config): void
    {
        $property = $class->addProperty($config['name'])
            ->setType($config['type'])
            ->setPrivate()
            ->setValue($config['defaultValue'])
        ;

        $property->addAttribute('Doctrine\ORM\Mapping\Column', [
            'type' => $config['doctrineType'],
        ]);
    }

    private function addGetterMethod(ClassType $class, string $name, string $doctrineType, bool $nullable): void
    {
        $methodConfig = $this->buildGetterConfig($name, $doctrineType, $nullable);
        $this->createGetterMethod($class, $methodConfig);
    }

    /**
     * @return GetterConfig
     */
    private function buildGetterConfig(string $name, string $doctrineType, bool $nullable): array
    {
        $phpType = $this->mapDoctrineTypeToPhpType($doctrineType);
        $getterName = ('boolean' === $doctrineType ? 'is' : 'get') . ucfirst($name);
        $getterReturnType = $nullable ? ('?' . $phpType) : $phpType;
        $getterBody = $this->buildGetterBody($name, $phpType, $nullable);

        return [$getterName, $getterReturnType, $getterBody];
    }

    private function buildGetterBody(string $name, string $phpType, bool $nullable): string
    {
        if ($nullable) {
            return 'return $this->' . $name . ';';
        }

        return 'return $this->' . $name . ' ?? ' . $this->getDefaultValueString($phpType) . ';';
    }

    /**
     * @param GetterConfig $config
     */
    private function createGetterMethod(ClassType $class, array $config): void
    {
        [$getterName, $getterReturnType, $getterBody] = $config;

        $class->addMethod($getterName)
            ->setReturnType($getterReturnType)
            ->setBody($getterBody)
        ;
    }

    private function addSetterMethod(ClassType $class, string $name, string $doctrineType, bool $nullable): void
    {
        $setter = $this->createSetterMethod($class, $name);
        $this->configureSetterParameter($setter, $name, $doctrineType, $nullable);
        $this->configureSetterBody($setter, $name);
    }

    private function createSetterMethod(ClassType $class, string $name): Method
    {
        return $class->addMethod('set' . ucfirst($name));
    }

    private function configureSetterParameter(
        Method $setter,
        string $name,
        string $doctrineType,
        bool $nullable,
    ): void {
        $phpType = $this->mapDoctrineTypeToPhpType($doctrineType);
        $parameter = $setter->addParameter($name)->setType($phpType);

        if ($nullable) {
            $parameter->setNullable();
        }
    }

    private function configureSetterBody(Method $setter, string $name): void
    {
        $setter->setBody('$this->' . $name . ' = $' . $name . ';')
            ->setReturnType('self')
            ->addBody('return $this;')
        ;
    }

    private function mapDoctrineTypeToPhpType(string $doctrineType): string
    {
        $typeMap = $this->getDoctrinePhpTypeMap();

        return $typeMap[$doctrineType] ?? 'mixed';
    }

    /**
     * @return array<string, string>
     */
    private function getDoctrinePhpTypeMap(): array
    {
        return [
            'string' => 'string',
            'text' => 'string',
            'integer' => 'int',
            'bigint' => 'int',
            'boolean' => 'bool',
            'datetime' => '\DateTimeInterface',
            'datetime_immutable' => '\DateTimeInterface',
            'json' => 'array',
            'decimal' => 'float',
            'float' => 'float',
        ];
    }

    private function getDefaultValueForType(string $phpType): mixed
    {
        $defaultMap = $this->getDefaultValueMap();

        return $defaultMap[$phpType] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultValueMap(): array
    {
        return [
            'string' => '',
            'int' => 0,
            'bool' => false,
            'float' => 0.0,
            'array' => [],
        ];
    }

    private function getDefaultValueString(string $phpType): string
    {
        $stringMap = $this->getDefaultValueStringMap();

        return $stringMap[$phpType] ?? 'null';
    }

    /**
     * @return array<string, string>
     */
    private function getDefaultValueStringMap(): array
    {
        return [
            'string' => "''",
            'int' => '0',
            'bool' => 'false',
            'float' => '0.0',
            'array' => '[]',
        ];
    }

    /**
     * 为接口类型添加属性（不持久化）
     */
    private function addInterfacePropertyToClass(ClassType $class, string $name, string $interfaceType, bool $nullable): void
    {
        $property = $class->addProperty($name)
            ->setType($interfaceType)
            ->setPrivate()
            ->setNullable($nullable)
        ;

        // 对于接口类型，默认值总是 null
        if ($nullable) {
            $property->setValue(null);
        }

        // 不添加 ORM 注解，因为接口类型不持久化到数据库
    }

    /**
     * 为接口类型添加 getter 方法
     */
    private function addInterfaceGetterMethod(ClassType $class, string $name, string $interfaceType, bool $nullable): void
    {
        $getterName = 'get' . ucfirst($name);

        $method = $class->addMethod($getterName)
            ->setReturnType($interfaceType)
            ->setReturnNullable($nullable)
            ->setBody('return $this->' . $name . ';')
        ;
    }

    /**
     * 为接口类型添加 setter 方法
     */
    private function addInterfaceSetterMethod(ClassType $class, string $name, string $interfaceType, bool $nullable): void
    {
        $setterName = 'set' . ucfirst($name);

        $setter = $class->addMethod($setterName)
            ->setReturnType('self')
            ->setBody('$this->' . $name . ' = $' . $name . ';' . "\n" . 'return $this;')
        ;

        $parameter = $setter->addParameter($name)
            ->setType($interfaceType)
            ->setNullable($nullable)
        ;
    }
}
