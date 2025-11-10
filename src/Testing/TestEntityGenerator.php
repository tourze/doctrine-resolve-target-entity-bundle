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

    private MethodBodyGenerator $methodBodyGenerator;

    private ToStringGenerator $toStringGenerator;

    public function __construct(
        private readonly string $cacheDir,
    ) {
        $this->propertyGenerator = new EntityPropertyGenerator();
        $this->interfaceAnalyzer = new InterfaceAnalyzer();
        $this->methodBodyGenerator = new MethodBodyGenerator();
        $this->toStringGenerator = new ToStringGenerator();
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

        // 注意：不在此处无条件生成 getId()，交由后续根据接口签名自动生成，避免签名不兼容
        // 如果实现的接口中根本没有声明 getId，则补一个默认的 ?int 版本，方便常规使用
        $hasInterfaceGetId = false;
        foreach ($class->getImplements() as $implemented) {
            if (interface_exists($implemented)) {
                $iface = new \ReflectionClass($implemented);
                if ($iface->hasMethod('getId')) {
                    $hasInterfaceGetId = true;
                    break;
                }
            }
        }

        if (!$hasInterfaceGetId && !$class->hasMethod('getId')) {
            $class->addMethod('getId')
                ->setReturnType('?int')
                ->setBody('return $this->id;')
            ;
        }
    }

    private function generateToStringMethod(ClassType $class, string $interface): void
    {
        $this->toStringGenerator->generateToStringMethod($class, $interface);
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

        return $this->methodBodyGenerator->generateMethodBody(
            $reflectionMethod->getName(),
            $reflectionMethod->getReturnType()
        );
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
