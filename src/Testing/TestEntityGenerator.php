<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Testing;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Parameter;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;
use Tourze\DoctrineResolveTargetEntityBundle\Exception\InvalidInterfaceException;

/**
 * 测试实体生成器
 *
 * 使用 Nette PHP Generator 动态生成实现接口的测试实体
 */
class TestEntityGenerator
{
    /** @var array<string, string> */
    private array $generatedEntities = [];

    private EntityPropertyGenerator $propertyGenerator;

    private InterfaceAnalyzer $interfaceAnalyzer;

    public function __construct(
        private readonly string $cacheDir,
    ) {
        $this->propertyGenerator = new EntityPropertyGenerator();
        $this->interfaceAnalyzer = new InterfaceAnalyzer();
    }

    public function getNamespace(): string
    {
        return 'DoctrineResolveTargetForTest\Entity';
    }

    /**
     * 为接口生成测试实体类
     *
     * @param array<string, array{type: string, nullable?: bool, is_interface?: bool}|string> $properties
     */
    public function generateTestEntity(string $interface, array $properties = []): string
    {
        return $this->handleEntityGeneration($interface, $properties);
    }

    /**
     * @param array<string, array{type: string, nullable?: bool, is_interface?: bool}|string> $properties
     */
    private function handleEntityGeneration(string $interface, array $properties): string
    {
        $this->tryGetMockPagination($interface);

        $fqcn = $this->resolveOrCreateEntityClass($interface, $properties);

        if (class_exists($fqcn)) {
            return $fqcn;
        }

        $this->generateAndSaveEntityClass($interface, $properties, $fqcn);

        return $fqcn;
    }

    private function tryGetMockPagination(string $interface): void
    {
    }

    /**
     * @param array<string, array{type: string, nullable?: bool, is_interface?: bool}|string> $properties
     */
    private function resolveOrCreateEntityClass(string $interface, array $properties): string
    {
        $cacheKey = md5($interface . serialize($properties));
        if (isset($this->generatedEntities[$cacheKey])) {
            return $this->generatedEntities[$cacheKey];
        }

        $baseName = preg_replace('/[^A-Za-z0-9]/', '', basename(str_replace('\\', '/', $interface)));
        $hash = substr(md5($interface), 0, 8);
        $className = 'Test' . $baseName . $hash;
        $namespace = $this->getNamespace();
        $fqcn = $namespace . '\\' . $className;

        $this->generatedEntities[$cacheKey] = $fqcn;

        return $fqcn;
    }

    /**
     * @param array<string, array{type: string, nullable?: bool, is_interface?: bool}|string> $properties
     */
    private function generateAndSaveEntityClass(string $interface, array $properties, string $fqcn): void
    {
        $classInfo = $this->parseEntityClassInfo($fqcn);
        $phpNamespace = $this->createEntityClass($classInfo, $interface, $properties);
        $this->saveAndLoadEntityClass($phpNamespace, $classInfo['className']);
    }

    /**
     * @return array<string, string>
     */
    private function parseEntityClassInfo(string $fqcn): array
    {
        $parts = explode('\\', $fqcn);
        $className = array_pop($parts);
        $namespace = implode('\\', $parts);

        return ['className' => $className, 'namespace' => $namespace];
    }

    /**
     * @param array<string, string> $classInfo
     * @param array<string, array{type: string, nullable?: bool, is_interface?: bool}|string> $properties
     */
    private function createEntityClass(array $classInfo, string $interface, array $properties): PhpNamespace
    {
        $phpNamespace = new PhpNamespace($classInfo['namespace']);
        $class = $phpNamespace->addClass($classInfo['className']);

        $this->setupEntityClass($class, $interface, $classInfo['className']);
        $this->generateEntityProperties($class, $interface, $properties);
        $this->generateRemainingMethods($class, $interface);

        return $phpNamespace;
    }

    private function setupEntityClass(ClassType $class, string $interface, string $className): void
    {
        $class->addAttribute('Doctrine\ORM\Mapping\Entity');
        $class->addAttribute('Doctrine\ORM\Mapping\Table', [
            'name' => $this->camelCaseToSnakeCase($className),
        ]);
        $class->addImplement($interface);
        $this->generateIdProperty($class);
        $this->generateToStringMethod($class, $interface);
    }

    /**
     * @param array<string, array{type: string, nullable?: bool, is_interface?: bool}|string> $properties
     */
    private function generateEntityProperties(ClassType $class, string $interface, array $properties): void
    {
        if ([] === $properties) {
            $properties = $this->interfaceAnalyzer->inferPropertiesFromInterface($interface);
        }

        foreach ($properties as $name => $propertyConfig) {
            $this->propertyGenerator->generateConfiguredProperty($class, $name, $propertyConfig);
        }
    }

    private function camelCaseToSnakeCase(string $input): string
    {
        $result = preg_replace('/[A-Z]/', '_\0', lcfirst($input));

        return strtolower($result ?? $input);
    }

    private function generateIdProperty(ClassType $class): void
    {
        // 防御性检查：避免在测试进程间重复生成时添加已存在的属性
        // 这可能发生在接口属性推断包含 id 或跨进程文件缓存复用时
        if ($class->hasProperty('id')) {
            return;
        }

        $id = $class->addProperty('id')
            ->setType('?int')
            ->setPrivate()
            ->setValue(null)
        ;

        $id->addAttribute('Doctrine\ORM\Mapping\Id');
        $id->addAttribute('Doctrine\ORM\Mapping\GeneratedValue');
        $id->addAttribute('Doctrine\ORM\Mapping\Column', ['type' => 'integer']);

        // 防御性检查：避免重复添加 getId 方法
        if (!$class->hasMethod('getId')) {
            $class->addMethod('getId')
                ->setReturnType('?int')
                ->setBody('return $this->id;')
            ;
        }
    }

    /**
     * 生成 __toString() 方法以支持表单渲染等场景
     *
     * 策略：
     * 1. 查找接口中返回 string 的标识方法（如 getUserIdentifier、getName 等）
     * 2. 如果找到，使用该方法
     * 3. 否则，使用 ID 或类名作为后备
     */
    private function generateToStringMethod(ClassType $class, string $interface): void
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
                // 如果调用失败，返回安全的后备值
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

    private function generateRemainingMethods(ClassType $class, string $interface): void
    {
        if (!interface_exists($interface)) {
            return;
        }

        $this->addMissingMethods($class, $interface);
    }

    private function addMissingMethods(ClassType $class, string $interface): void
    {
        /** @var class-string $interface */
        $reflection = new \ReflectionClass($interface);
        $existingMethods = array_map(fn ($method) => $method->getName(), $class->getMethods());

        foreach ($reflection->getMethods() as $method) {
            if (!in_array($method->getName(), $existingMethods, true)) {
                $this->generateMethodImplementation($class, $method, null);
            }
        }
    }

    private function generateMethodImplementation(
        ClassType $class,
        \ReflectionMethod $reflectionMethod,
        ?callable $implementation,
    ): void {
        $method = $class->addMethod($reflectionMethod->getName());
        $method->setPublic();

        $this->configureMethod($method, $reflectionMethod, $implementation);
    }

    private function configureMethod(
        Method $method,
        \ReflectionMethod $reflectionMethod,
        ?callable $implementation,
    ): void {
        $this->setupMethodParameters($method, $reflectionMethod);
        $this->setupMethodReturnType($method, $reflectionMethod);
        $this->setupMethodBody($method, $reflectionMethod, $implementation);
    }

    private function setupMethodParameters(
        Method $method,
        \ReflectionMethod $reflectionMethod,
    ): void {
        foreach ($reflectionMethod->getParameters() as $param) {
            $parameter = $method->addParameter($param->getName());
            $this->configureMethodParameter($parameter, $param);
        }
    }

    private function configureMethodParameter(
        Parameter $parameter,
        \ReflectionParameter $param,
    ): void {
        if ($param->hasType()) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType) {
                $parameter->setType($type->getName());
                $parameter->setNullable($type->allowsNull());
            }
        }

        if ($param->isDefaultValueAvailable()) {
            $parameter->setDefaultValue($param->getDefaultValue());
        }
    }

    private function setupMethodReturnType(
        Method $method,
        \ReflectionMethod $reflectionMethod,
    ): void {
        if (!$reflectionMethod->hasReturnType()) {
            return;
        }

        $returnType = $reflectionMethod->getReturnType();
        if ($returnType instanceof \ReflectionNamedType) {
            $typeName = $returnType->getName();

            // 确保接口类型使用完全限定名
            if (interface_exists($typeName) || class_exists($typeName)) {
                $method->setReturnType('\\' . $typeName);
            } else {
                $method->setReturnType($typeName);
            }

            $method->setReturnNullable($returnType->allowsNull());
        }
    }

    private function setupMethodBody(
        Method $method,
        \ReflectionMethod $reflectionMethod,
        ?callable $implementation,
    ): void {
        $body = $this->determineMethodBody($reflectionMethod, $implementation);
        $method->setBody($body);
    }

    private function determineMethodBody(\ReflectionMethod $reflectionMethod, ?callable $implementation): string
    {
        if (null !== $implementation) {
            return '// Custom implementation provided at runtime';
        }

        return $this->generateDefaultMethodBody($reflectionMethod);
    }

    private function generateDefaultMethodBody(\ReflectionMethod $method): string
    {
        $methodName = $method->getName();
        $returnType = $method->getReturnType();

        return $this->generateMethodBodyByType($methodName, $returnType);
    }

    private function generateMethodBodyByType(string $methodName, ?\ReflectionType $returnType): string
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

    private function saveAndLoadEntityClass(PhpNamespace $phpNamespace, string $className): void
    {
        $this->saveGeneratedClass($phpNamespace, $className);
        require_once $this->getClassFilePath($className);
    }

    private function saveGeneratedClass(PhpNamespace $namespace, string $className): void
    {
        $printer = new PsrPrinter();
        $code = "<?php\n\n" . $printer->printNamespace($namespace);

        $dir = $this->cacheDir . '/test_entities';
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }

        file_put_contents($this->getClassFilePath($className), $code);
    }

    private function getClassFilePath(string $className): string
    {
        return $this->cacheDir . '/test_entities/' . str_replace('\\', '_', $className) . '.php';
    }

    /**
     * 为接口生成测试实现（非 Doctrine 实体）
     *
     * @param array<string, callable(): mixed> $methods
     */
    public function generateTestImplementation(string $interface, array $methods = []): object
    {
        $this->validateInterface($interface);

        return $this->createImplementationInstance($interface, $methods);
    }

    private function validateInterface(string $interface): void
    {
        if (!interface_exists($interface)) {
            throw new InvalidInterfaceException(sprintf('Interface "%s" does not exist', $interface));
        }
    }

    /**
     * @param array<string, callable(): mixed> $methods
     */
    private function createImplementationInstance(string $interface, array $methods): object
    {
        $this->tryCreateMockPaginationInstance($interface);

        return $this->resolveOrCreateImplementation($interface, $methods);
    }

    private function tryCreateMockPaginationInstance(string $interface): void
    {
    }

    /**
     * @param array<string, callable(): mixed> $methods
     */
    private function resolveOrCreateImplementation(string $interface, array $methods): object
    {
        $fqcn = $this->resolveImplementationClass($interface, $methods);

        if (class_exists($fqcn)) {
            return new $fqcn();
        }

        return $this->generateAndCreateImplementation($interface, $methods, $fqcn);
    }

    /**
     * @param array<string, mixed> $methods
     */
    private function resolveImplementationClass(string $interface, array $methods): string
    {
        $shortName = basename(str_replace('\\', '/', $interface));
        $methodsHash = md5($interface . serialize(array_keys($methods)));
        $className = 'TestImpl' . preg_replace('/[^A-Za-z0-9]/', '', $shortName) . substr($methodsHash, 0, 8);
        $namespace = 'Test\Generated';

        return $namespace . '\\' . $className;
    }

    /**
     * @param array<string, callable(): mixed> $methods
     */
    private function generateAndCreateImplementation(string $interface, array $methods, string $fqcn): object
    {
        $this->createImplementationFile($interface, $methods, $fqcn);

        return new $fqcn();
    }

    /**
     * @param array<string, callable(): mixed> $methods
     */
    private function createImplementationFile(string $interface, array $methods, string $fqcn): void
    {
        $tmpDir = $this->ensureTempDirectory();
        $phpNamespace = $this->createImplementationClass($interface, $methods, $fqcn);
        $this->saveImplementationToFile($phpNamespace, $fqcn, $tmpDir);
    }

    private function ensureTempDirectory(): string
    {
        $tmpDir = sys_get_temp_dir() . '/test_implementations';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0o777, true);
        }

        return $tmpDir;
    }

    /**
     * @param array<string, callable(): mixed> $methods
     */
    private function createImplementationClass(string $interface, array $methods, string $fqcn): PhpNamespace
    {
        $classInfo = $this->parseClassInfo($fqcn);
        $phpNamespace = $this->buildImplementationNamespace($classInfo, $interface);
        $classes = $phpNamespace->getClasses();
        $class = $classes[$classInfo['className']];
        if ($class instanceof ClassType) {
            $this->configureImplementationClass($class, $interface, $methods);
        }

        return $phpNamespace;
    }

    /**
     * @return array<string, string>
     */
    private function parseClassInfo(string $fqcn): array
    {
        $parts = explode('\\', $fqcn);
        $className = array_pop($parts);
        $namespace = implode('\\', $parts);

        return ['className' => $className, 'namespace' => $namespace];
    }

    /**
     * @param array<string, string> $classInfo
     */
    private function buildImplementationNamespace(array $classInfo, string $interface): PhpNamespace
    {
        $phpNamespace = new PhpNamespace($classInfo['namespace']);
        $class = $phpNamespace->addClass($classInfo['className']);
        $class->addImplement($interface);

        return $phpNamespace;
    }

    /**
     * @param array<string, callable(): mixed> $methods
     */
    private function configureImplementationClass(ClassType $class, string $interface, array $methods): void
    {
        $this->setupTraversableInterface($class, $interface);
        $this->generateInterfaceMethods($class, $interface, $methods);
    }

    private function setupTraversableInterface(ClassType $class, string $interface): void
    {
        /** @var class-string $interface */
        $reflection = new \ReflectionClass($interface);

        if ($reflection->isSubclassOf(\Traversable::class)) {
            $class->addImplement(\IteratorAggregate::class);

            $getIteratorMethod = $class->addMethod('getIterator');
            $getIteratorMethod->setReturnType(\Traversable::class);
            $getIteratorMethod->setBody('return new \ArrayIterator([]);');
        }
    }

    /**
     * @param array<string, callable(): mixed> $methods
     */
    private function generateInterfaceMethods(ClassType $class, string $interface, array $methods): void
    {
        /** @var class-string $interface */
        $reflection = new \ReflectionClass($interface);

        foreach ($reflection->getMethods() as $method) {
            $methodName = $method->getName();
            $this->generateMethodImplementation($class, $method, $methods[$methodName] ?? null);
        }
    }

    private function saveImplementationToFile(PhpNamespace $phpNamespace, string $fqcn, string $tmpDir): void
    {
        $parts = explode('\\', $fqcn);
        $className = array_pop($parts);
        $printer = new PsrPrinter();
        $code = "<?php\n\n" . $printer->printNamespace($phpNamespace);
        $tmpFile = $tmpDir . '/' . $className . '.php';

        file_put_contents($tmpFile, $code);
        require_once $tmpFile;
    }
}
