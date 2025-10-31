<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Testing;

/**
 * 接口分析器
 *
 * 负责分析接口并从中提取属性信息
 */
class InterfaceAnalyzer
{
    /**
     * @return array<string, mixed>
     */
    public function inferPropertiesFromInterface(string $interface): array
    {
        if (!interface_exists($interface)) {
            return [];
        }

        return $this->extractPropertiesFromMethods($interface);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPropertiesFromMethods(string $interface): array
    {
        /** @var class-string $interface */
        $reflection = new \ReflectionClass($interface);
        $properties = [];

        foreach ($reflection->getMethods() as $method) {
            $propertyData = $this->extractPropertyFromMethod($method);
            if (null !== $propertyData) {
                [$propertyName, $typeConfig] = $propertyData;
                $properties[$propertyName] = $typeConfig;
            }
        }

        return $properties;
    }

    /**
     * 从方法中提取属性信息
     *
     * @return array<int, mixed>|null
     */
    private function extractPropertyFromMethod(\ReflectionMethod $method): ?array
    {
        if (!$this->isGetterMethod($method->getName())) {
            return null;
        }

        return $this->processGetterMethod($method);
    }

    /**
     * @return array<int, mixed>|null
     */
    private function processGetterMethod(\ReflectionMethod $method): ?array
    {
        $propertyName = $this->extractPropertyNameFromGetter($method->getName());
        $returnType = $method->getReturnType();

        if (null === $returnType) {
            return null;
        }

        $typeInfo = $this->extractTypeInfo($returnType, $method->getName());
        if (null === $typeInfo) {
            return null;
        }

        return $this->buildPropertyConfig($propertyName, $typeInfo);
    }

    /**
     * @param array<int, mixed> $typeInfo
     * @return array<int, mixed>
     */
    private function buildPropertyConfig(string $propertyName, array $typeInfo): array
    {
        [$typeName, $isNullable] = $typeInfo;

        // 特殊处理：如果是接口类型，保持原有类型名而不是映射为 string
        if ($this->isInterfaceType($typeName)) {
            return [$propertyName, [
                'type' => $typeName,
                'nullable' => $isNullable,
                'is_interface' => true,
            ]];
        }

        $doctrineType = $this->mapPhpTypeToDoctrineType($typeName, $isNullable);

        return [$propertyName, [
            'type' => $doctrineType,
            'nullable' => $isNullable,
        ]];
    }

    /**
     * @return array<int, mixed>|null
     */
    private function extractTypeInfo(\ReflectionType $returnType, string $methodName): ?array
    {
        $typeData = $this->getBasicTypeInfo($returnType);
        if (null === $typeData) {
            return null;
        }

        [$typeName, $isNullable] = $typeData;

        return $this->adjustTypeForSpecialCases($typeName, $isNullable, $methodName);
    }

    /**
     * @return array<int, mixed>|null
     */
    private function getBasicTypeInfo(\ReflectionType $returnType): ?array
    {
        $isNullable = $returnType instanceof \ReflectionNamedType ? $returnType->allowsNull() : true;
        $typeName = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : 'mixed';

        if ('void' === $typeName) {
            return null;
        }

        return [$typeName, $isNullable];
    }

    /**
     * @return array<int, mixed>
     */
    private function adjustTypeForSpecialCases(string $typeName, bool $isNullable, string $methodName): array
    {
        if ('getRoles' === $methodName && 'array' === $typeName) {
            $isNullable = false;
        }

        return [$typeName, $isNullable];
    }

    private function isGetterMethod(string $methodName): bool
    {
        // 排除特殊方法 getMiniProgram，让 TestEntityGenerator 的特殊方法处理逻辑来处理
        if ('getMiniProgram' === $methodName) {
            return false;
        }

        return str_starts_with($methodName, 'get') || str_starts_with($methodName, 'is');
    }

    private function extractPropertyNameFromGetter(string $methodName): string
    {
        $result = preg_replace('/^(get|is)/', '', $methodName);

        return lcfirst($result ?? $methodName);
    }

    private function mapPhpTypeToDoctrineType(string $phpType, bool $isNullable = true): string
    {
        $typeMap = $this->getPhpDoctrineTypeMap();

        return $typeMap[$phpType] ?? 'string';
    }

    /**
     * @return array<string, string>
     */
    private function getPhpDoctrineTypeMap(): array
    {
        return [
            'string' => 'string',
            'int' => 'integer',
            'bool' => 'boolean',
            'float' => 'float',
            'array' => 'json',
            'DateTime' => 'datetime',
            'DateTimeInterface' => 'datetime',
            'DateTimeImmutable' => 'datetime',
        ];
    }

    /**
     * 检查类型是否为接口
     */
    private function isInterfaceType(string $typeName): bool
    {
        // 排除基础类型
        if (in_array($typeName, ['string', 'int', 'bool', 'float', 'array', 'mixed', 'object'], true)) {
            return false;
        }

        // 检查是否为接口
        if (interface_exists($typeName)) {
            return true;
        }

        // 对于常见的接口模式也返回 true
        return str_ends_with($typeName, 'Interface');
    }
}
