<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * PHPStan规则：检查继承Doctrine\Bundle\FixturesBundle\Fixture且注入ResolveTargetEntityService的类
 * 必须实现OrderedFixtureInterface并且getOrder()返回值要大于9999
 *
 * @implements Rule<Class_>
 */
class DataFixtureOrderedInterfaceRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_ || $node->isAbstract() || $node->isAnonymous()) {
            return [];
        }

        $className = $scope->getClassReflection()?->getName();
        if (!$className) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (!$classReflection) {
            return [];
        }

        // 检查是否继承自 Doctrine\Bundle\FixturesBundle\Fixture
        if (!$classReflection->isSubclassOf('Doctrine\Bundle\FixturesBundle\Fixture')) {
            return [];
        }

        // 检查是否注入了 ResolveTargetEntityService
        if (!$this->hasResolveTargetEntityServiceInjection($classReflection)) {
            return [];
        }

        $errors = [];

        // 检查是否实现了 OrderedFixtureInterface
        if (!$classReflection->implementsInterface('Doctrine\Common\DataFixtures\OrderedFixtureInterface')) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    'DataFixture 类 %s 继承了 Fixture 且注入了 ResolveTargetEntityService，必须实现 OrderedFixtureInterface 接口。',
                    $className
                )
            )
                ->line($node->getStartLine())
                ->tip('添加 "implements OrderedFixtureInterface" 到类定义并实现 getOrder() 方法。')
                ->build()
            ;
        } else {
            // 检查 getOrder() 方法的返回值
            $getOrderMethod = $this->findGetOrderMethod($node);
            if (null !== $getOrderMethod) {
                $orderValue = $this->getOrderReturnValue($getOrderMethod);
                if (null !== $orderValue && $orderValue <= 9999) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf(
                            'DataFixture 类 %s 的 getOrder() 方法返回值 %d 必须大于 9999。',
                            $className,
                            $orderValue
                        )
                    )
                        ->line($getOrderMethod->getLine())
                        ->tip('将 getOrder() 方法的返回值改为大于 9999 的数字。')
                        ->build()
                    ;
                }
            }
        }

        return $errors;
    }

    /**
     * 检查是否注入了 ResolveTargetEntityService
     */
    private function hasResolveTargetEntityServiceInjection(ClassReflection $classReflection): bool
    {
        // 使用反射检查构造函数参数
        if ($classReflection->hasMethod('__construct')) {
            $constructor = $classReflection->getMethod('__construct');
            $variants = $constructor->getVariants();
            foreach ($variants as $variant) {
                foreach ($variant->getParameters() as $param) {
                    $paramType = $param->getType();
                    if ($paramType instanceof ObjectType) {
                        $className = $paramType->getClassName();
                        if ($this->isResolveTargetEntityService($className)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * 检查是否为 ResolveTargetEntityService
     */
    private function isResolveTargetEntityService(string $typeName): bool
    {
        return 'Tourze\DoctrineResolveTargetEntityBundle\Service\ResolveTargetEntityService' === $typeName;
    }

    /**
     * 查找 getOrder 方法
     */
    private function findGetOrderMethod(Class_ $node): ?ClassMethod
    {
        foreach ($node->getMethods() as $method) {
            if ('getOrder' === $method->name->toString()) {
                return $method;
            }
        }

        return null;
    }

    /**
     * 获取 getOrder 方法的返回值
     */
    private function getOrderReturnValue(ClassMethod $method): ?int
    {
        foreach ($method->getStmts() ?? [] as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof LNumber) {
                return $stmt->expr->value;
            }
        }

        return null;
    }
}
