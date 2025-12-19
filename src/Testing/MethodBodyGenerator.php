<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Testing;

/**
 * 方法体生成器
 *
 * 负责根据方法签名生成合适的默认实现
 */
final class MethodBodyGenerator
{
    /**
     * 根据方法名和返回类型生成方法体
     */
    public function generateMethodBody(string $methodName, ?\ReflectionType $returnType): string
    {
        if ($this->isSpecialMethod($methodName)) {
            return $this->generateSpecialMethodBody($methodName);
        }

        if ($this->isMiniProgramMethod($methodName)) {
            return $this->generateMiniProgramMethodBody();
        }

        return $this->generateStandardMethodBody($returnType);
    }

    private function isSpecialMethod(string $methodName): bool
    {
        return 'getRoles' === $methodName;
    }

    private function generateSpecialMethodBody(string $methodName): string
    {
        return match ($methodName) {
            'getRoles' => "return ['ROLE_USER'];",
            default => 'return null;',
        };
    }

    private function isMiniProgramMethod(string $methodName): bool
    {
        return 'getMiniProgram' === $methodName;
    }

    private function generateMiniProgramMethodBody(): string
    {
        return 'return new class implements \Tourze\WechatMiniProgramAppIDContracts\MiniProgramInterface { public function getAppId(): string { return "test_app_id"; } public function getAppSecret(): string { return "test_app_secret"; } };';
    }

    private function generateStandardMethodBody(?\ReflectionType $returnType): string
    {
        if (null === $returnType || $returnType->allowsNull()) {
            return 'return null;';
        }

        if ($returnType instanceof \ReflectionNamedType) {
            return $this->generateTypedMethodBody($returnType->getName());
        }

        return 'return null;';
    }

    private function generateTypedMethodBody(string $typeName): string
    {
        $simpleTypes = [
            'void' => '// void method',
            'string' => "return '';",
            'int' => 'return 0;',
            'float' => 'return 0.0;',
            'bool' => 'return false;',
            'array' => 'return [];',
        ];

        if (isset($simpleTypes[$typeName])) {
            return $simpleTypes[$typeName];
        }

        if (in_array($typeName, ['self', 'static'], true)) {
            return 'return $this;';
        }

        return $this->generateComplexTypeMethodBody($typeName);
    }

    private function generateComplexTypeMethodBody(string $typeName): string
    {
        if (class_exists($typeName) || interface_exists($typeName)) {
            return "throw new \\RuntimeException('Not implemented');";
        }

        return 'return null;';
    }
}
