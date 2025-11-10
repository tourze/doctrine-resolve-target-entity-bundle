<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Testing;

use Nette\PhpGenerator\ClassType;

/**
 * __toString 方法生成器
 *
 * 负责为实体生成合适的 __toString 方法
 */
class ToStringGenerator
{
    /**
     * 生成 __toString() 方法以支持表单渲染等场景
     *
     * 策略：
     * 1. 查找接口中返回 string 的标识方法（如 getUserIdentifier、getName 等）
     * 2. 如果找到，使用该方法
     * 3. 否则，使用 ID 或类名作为后备
     */
    public function generateToStringMethod(ClassType $class, string $interface): void
    {
        if (!interface_exists($interface)) {
            $this->generateDefaultToString($class);

            return;
        }

        $identifierMethod = $this->findStringIdentifierMethod($interface);

        if (null !== $identifierMethod) {
            $this->generateIdentifierBasedToString($class, $identifierMethod);
        } else {
            $this->generateDefaultToString($class);
        }
    }

    /**
     * 查找接口中可以作为标识符的字符串方法
     */
    private function findStringIdentifierMethod(string $interface): ?string
    {
        /** @var class-string $interface */
        $reflection = new \ReflectionClass($interface);

        // 优先级列表：常见的标识符方法
        $candidateMethods = [
            'getUserIdentifier',  // Symfony UserInterface
            'getUsername',        // 旧版 Symfony
            'getName',            // 通用名称
            'getTitle',           // 通用标题
            'getLabel',           // 通用标签
            'getIdentifier',      // 通用标识符
        ];

        foreach ($candidateMethods as $methodName) {
            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                if ($this->methodReturnsString($method)) {
                    return $methodName;
                }
            }
        }

        // 如果没有找到预定义的方法，查找任何返回 string 的 get 方法
        foreach ($reflection->getMethods() as $method) {
            $name = $method->getName();
            if (str_starts_with($name, 'get') && $this->methodReturnsString($method)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * 检查方法是否返回 string（非 nullable）
     */
    private function methodReturnsString(\ReflectionMethod $method): bool
    {
        $returnType = $method->getReturnType();

        if (!$returnType instanceof \ReflectionNamedType) {
            return false;
        }

        return 'string' === $returnType->getName() && !$returnType->allowsNull();
    }

    /**
     * 生成基于标识符方法的 __toString()
     *
     * 对 Doctrine 代理对象安全的实现：
     * - 检查代理是否已初始化
     * - 如果未初始化，返回 ID 或对象哈希
     * - 如果已初始化，才调用标识符方法
     */
    private function generateIdentifierBasedToString(ClassType $class, string $methodName): void
    {
        $body = <<<'PHP'
            // 对 Doctrine 代理对象安全：先检查是否已初始化
            if (method_exists($this, '__isInitialized') && !$this->__isInitialized()) {
                return $this->id ? 'ID:' . $this->id : 'Proxy:' . spl_object_hash($this);
            }

            // 代理已初始化或非代理对象，安全调用标识符方法
            try {
                return $this->%s();
            } catch (\Throwable $e) {
                // 如果调用失败,返回安全的后备值
                return $this->id ? 'ID:' . $this->id : 'Object:' . spl_object_hash($this);
            }
            PHP;

        $class->addMethod('__toString')
            ->setReturnType('string')
            ->setBody(sprintf($body, $methodName))
        ;
    }

    /**
     * 生成默认的 __toString() 实现（使用 ID 或类名）
     */
    private function generateDefaultToString(ClassType $class): void
    {
        $class->addMethod('__toString')
            ->setReturnType('string')
            ->setBody('return $this->id ? (string) $this->id : spl_object_hash($this);')
        ;
    }
}
