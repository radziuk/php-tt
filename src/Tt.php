<?php

namespace Radziuk\PhpTT;

use DI\Container;
use DI\ContainerBuilder;

class Tt
{
    protected int $count = 0;
    protected int $countTrue = 0;
    protected int $countFalse = 0;

    protected $cache_dir;
    protected $data_dir;

    protected $class_dir;
    protected $_commands = [
    ];

    protected static $_enhancedCommands = [

    ];

    private array $_made = [];
    private Container $container;
    private TtGoParser $ttGoParser;

    private DataSourceProvider $dataSourceProvider;

    private array $_output = [];

    private string $includeMode = 'eval';

    private int $verbosity = 2;

    private bool $isShowWarnings = false;

    public function __construct()
    {
        $builder = new ContainerBuilder();
        $this->container = $builder->build();
        $this->dataSourceProvider = $this->container->get(DataSourceProvider::class);
        $this->dataSourceProvider->setAlertCallback(function (string $string) {
            $this->alert($string);
        });
        $this->ttGoParser = new TtGoParser(new StringReplacer(), $this->dataSourceProvider);
        $this->initCommands();
    }

    /**
     * Execute the console command.
     */
    public function run(string $class_dir, string $data_dir = '', string $cache_dir = '', bool $clearCache = false): void
    {
        $this->class_dir = $directory = $class_dir;

        $this->data_dir = $data_dir;
        $this->dataSourceProvider->setDataDir($data_dir);

        $this->cache_dir = $cache_dir;

        if ($this->cache_dir && is_writable($cache_dir)) {
            $this->includeMode = 'include';
        }

        $directoryIterator = new \RecursiveDirectoryIterator($directory);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);
        $phpFiles = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

        $classMap = [];

        foreach ($phpFiles as $phpFile) {
            $filePath = $phpFile[0];

            // Extract the contents of the file
            $content = file_get_contents($filePath);

            // Use a regular expression to find class names
            $namespace = '';
            $namespacePattern = '/namespace\s+([^;]+);/';
            preg_match($namespacePattern, $content, $namespaceMatches);
            if (!empty($namespaceMatches[1])) {
                $namespace = $namespaceMatches[1] . '\\';
            }

            // Get all class names in the file
            $classPattern = '/class\s+([a-zA-Z0-9_]+)\s*/';
            preg_match_all($classPattern, $content, $classMatches);

            if (!empty($classMatches[1])) {
                foreach ($classMatches[1] as $className) {
                    $fullClassName = $namespace . $className;
                    try {
                        $this->container->get($fullClassName);
                        $classMap[$fullClassName] = $filePath;
                    } catch (\Throwable $e) {
                        continue;

                    }
                }
            }
        }

        $parsedMap = [];
        foreach($classMap as $className => $path) {
            $parsed = $this->parseClass($className);
            if (count($parsed)) {
                $parsedMap[$className] = $parsed;
            }
        }

        $this->doTests($parsedMap);

        if ($clearCache) {
            $this->clearCache();
        }

        $this->info("Assertions done: " . $this->count);
        $this->info("Assertions true: " . $this->countTrue);
        if ($this->countFalse) {
            $this->error("Assertions false: " . $this->countFalse);
        }
    }

    private function initCommands(): void
    {
        $commands = [
            'equals' => function(\ReflectionMethod $method, $object, array $params, $expected): array
            {
                $result = $method->invoke($object, ...$params);
                return [$result === $expected, $result];
            },
            'preg' => function(\ReflectionMethod $method, $object, array $params, $expected): array
            {
                $result = $method->invoke($object, ...$params);
                return [preg_match($expected, $result), $result];
            },
            'contains'  => function(\ReflectionMethod $method, $object, array $params, $expected): array
            {
                $result = $method->invoke($object, ...$params);
                return [str_contains($result, $expected), $result];
            },
            'not-contains'  => function(\ReflectionMethod $method, $object, array $params, $expected): array
            {
                $result = $method->invoke($object, ...$params);
                return [str_contains($result, $expected) === false, $result];
            },
            'exception-contains'  => function(\ReflectionMethod $method, $object, array $params, $expected): array {
                try {
                    $method->invoke($object, ...$params);
                    return [false, null];
                } catch (\Throwable $e) {
                    return [str_contains($e->getMessage(), $expected), $e->getMessage()];
                }
            },
            'exception'  => function(\ReflectionMethod $method, $object, array $params, $expected): array
            {
                try {
                    $method->invoke($object, ...$params);
                    return [false, null];
                } catch(\Throwable $e) {
                    if (null === $expected) {
                        return [true, $e->getMessage()];
                    }
                    return [$e instanceof $expected, get_class($e)];
                }
            },
            'callable' => function(\ReflectionMethod $method, $object, array $params, $expected): array
            {
                $callableParams = [];
                $callable = $expected;
                if (is_array($expected)) {
                    $callable = array_shift($expected);
                    $callableParams = $expected;
                }
                if (!is_callable($callable)) {
                    throw new TtException(sprintf("assert-callable: Expected callable, got %s instead", gettype($callable)));
                }
                $result = $method->invoke($object, ...$params);
                $return = $callable($result, ...$callableParams);
                if (!is_bool($return)) {
                    throw new TtException(sprintf("assert-callable: Callable is expected to return bool, got %s instead", gettype($return)));
                }
                return [$return, $result];
            },
        ];

        foreach($commands as $key => $closure) {
            $this->_commands[$key] = $closure;
        }

        foreach(self::$_enhancedCommands as $key => $closure) {
            if (!array_key_exists($key, $this->_commands)) {
                $this->_commands[$key] = $closure;
            }
        }
    }

    private function makeAlias(string $alias): void
    {
        $preparedAlias = $this->ttGoParser->makeAlias($alias);
        if (array_key_exists($preparedAlias['key'], $this->_commands)) {
            throw new TtException(sprintf('Alias %s already exists', $preparedAlias['key']));
        }
        if (!is_callable($preparedAlias['callback'])) {
            throw new TtException(sprintf("Can't make callable for alias %s", $alias));
        }

        $callable = $preparedAlias['callback'];
        $key = $preparedAlias['key'];
        $this->_commands[$key] = function(\ReflectionMethod $method, $object, array $params, $expected) use($callable, $key): array
        {
            $result = $method->invoke($object, ...$params);
            $return = $callable($result, $expected);
            if (!is_bool($return)) {
                throw new TtException(sprintf("%s: Callable is expected to return bool, got %s instead", $key, gettype($return)));
            }
            return [$return, $result];
        };
    }

    /**
     * @param string $className
     * @return array
     * @throws \ReflectionException
     */
    private function parseClass(string $className): array
    {
        $result = [];
        $reflectionClass = new \ReflectionClass($className);
        $methods = $reflectionClass->getMethods();
        foreach ($methods as $method) {
            if ($this->isTestableFromDocblock($method->getDocComment())) {

                $result[$method->getName()] = $method;
            }
        }


        return  count($result) ? [
            'reflectionObject' => $reflectionClass,
            'methods' => $result
        ] : [];
    }

    /**
     * @param string $dockblock
     * @return bool
     * @php-tt-data php_tt_data
     * @php-tt-assert-callable 'dockblock' >>> function(bool $result){return $result === false;}
     * @php-tt-assert-callable 'dockblock' >>> [function(bool $result, $expected){return $result === $expected;}, false]
     * @php-tt-assert-callable 'dockblock' >>> [#php_tt_data.callable, #php_tt_data.callable_param]
     */
    private function isTestableFromDocblock(string $dockblock): bool
    {
        return preg_match('/@php-tt-/', $dockblock);
    }

    private function doTests(array $parsedMap): void
    {
        foreach($parsedMap as $className => $parsedClass) {
            [$reflectionObject, $methods] = array_values($parsedClass);

            array_walk($methods, function (\ReflectionMethod $method) use($reflectionObject){
                $docBlock = $method->getDocComment();

                $lines = $this->getLinesFromDocblock($docBlock);

                $this->doLines($method, $reflectionObject, $lines);
            });
        }
    }

    /**
     * @param string $docblock
     * @return array
     * @php-tt-data php_tt_data.getLinesFromDocblock
     */
    private function getLinesFromDocblock(string $docblock): array
    {
        $splitted = explode("\n", $docblock);

        $lines = [];
        foreach($splitted as $line) {
            if (preg_match('/^\s*\*\s*@php-tt-(.*)/', $line, $match)) {
                $lines[] = $match[1];
            }
        }

        return $lines;
    }

    private function doLines(\ReflectionMethod $method, \ReflectionClass $classObject, array $lines): void
    {
        $signature = $classObject->getName() . '::' . $method->getName();
        $mocks = [];
        $before = [];

        foreach($lines as $line) {
            $prepared = $this->prepareLine($line);
            switch ($prepared['type']) {
                case 'mock':
                    $mock = $prepared['mock'];
                    $find = $this->createMockFind($mock['mock']);
                    $mocks[$find] = $mock;
                    break;
                case 'unmock':
                    $mock = $prepared['mock'];
                    $find = $this->createMockFind($mock['mock']);
                    if (array_key_exists($find, $mocks)) {
                        unset($mocks[$find]);
                    }
                    break;
                case 'before':
                    $before = [$prepared['before']];
                    break;
                case 'data':
                    $data = $this->getDataFromDataSource($prepared['source'], $method->getName());
                    foreach ($data as $assertion) {
                        $this->doAssert($assertion, $method, $classObject, $mocks, $before);
                        // print_r($assertion);
                    }
                    break;
                case 'assertion':
                    $this->doAssert($prepared['assertion'], $method, $classObject, $mocks, $before);
                    break;
                case 'alias':
                    $this->makeAlias($prepared['alias']);
                    break;
            }
        }
    }


    private function prepareLine(string $line): array
    {
        if (preg_match('/^before (.*)/', $line, $match)) {
            $before = trim($match[1]);

            if (!preg_match('/;$/', $before)) {
                $before .= ';';
            }

            return ['type' => 'before', 'before' => $before];
        }

        if (preg_match('/^mock (.*)/', $line, $match)) {
            $mock = trim($match[1]);

            return ['type' => 'mock', 'mock' => ['mock' => $mock, 'type' => 'mock']];
        }

        if (preg_match('/^unmock (.*)/', $line, $match)) {
            $mock = trim($match[1]);

            return ['type' => 'unmock', 'mock' => ['mock' => $mock, 'type' => 'mock']];
        }

        if (preg_match('/^exact-mock (.*)/', $line, $match)) {
            $mock = trim($match[1]);

            return ['type' => 'mock', 'mock' => ['mock' => $mock, 'type' => 'exact']];
        }

        if (preg_match('/^exact-unmock (.*)/', $line, $match)) {
            $mock = trim($match[1]);

            return ['type' => 'unmock', 'mock' => ['mock' => $mock, 'type' => 'exact']];
        }

        if (preg_match('/^data (.*)/', $line, $match)) {
            $dataSource = trim($match[1]);

            return ['type' => 'data', 'source' => $dataSource];
        }

        if (preg_match('/^alias (.*)/', $line, $match)) {
            $alias = trim($match[1]);

            return ['type' => 'alias', 'alias' => $alias];
        }

        if (preg_match('/^(assert|go).*/', $line)) {
            $command = preg_replace('/^(assert|go)/', '@php-tt-go', $line);
            $parsed = $this->ttGoParser->parse($command);

            return ['type' => 'assertion', 'assertion' => $parsed];
        }


        throw new TtException(sprintf("Can't prepare line %s", $line));
    }

    private function doAssert(array $assertion, \ReflectionMethod $method, \ReflectionClass $classObject, array $mocks, array $before)
    {
        [$object, $newMethod] = $this->makeClassObject2($classObject, $method->getName(), $mocks);


        if (!$newMethod->isPublic()) {
            $newMethod->setAccessible(true);
        }

        $allowed_commands = array_keys($this->_commands);
        if (!array_key_exists(2, $assertion)) {
            $assertion[2] = 'equals';
        }
        [$params, $expected, $command] = $assertion;
        if (!in_array($command, $allowed_commands)) {
            $this->alert("Unknown command `$command`. Skipping");
            return ;
        }

        if (count($before) > 0) {
            foreach($before as $runCommand) {
                eval($runCommand);
            }
        }

        $executionResult = $this->_commands[$command]($newMethod, $object, $params, $expected);
        [$assertResult, $actualResult] = $executionResult;

        if ($assertResult) {
            $this->assertSuccessful($command, $params, $expected, $actualResult, $method->getName(), $classObject->getName());
        } else {
            $this->assertFailed($command, $params, $expected, $actualResult, $method->getName(), $classObject->getName());
        }

        $this->count++;
    }

    private function assertSuccessful(string $command, array $params, $expected, $result, string $methodName, string $className): void
    {
        if ($this->verbosity > 2) {
            $this->info("Assert $command:  true");
        }
        $this->countTrue++;
    }

    private function assertFailed(string $command, array $params, $expected, $result, string $methodName, string $className): void
    {
        if ($this->verbosity > 0) {
            $this->error(sprintf("Test failed for %s->%s", $className, $methodName));
            $this->error(sprintf("Assert %s:  false", $command));
            if ($this->verbosity > 1) {
                $this->error(sprintf("Parameters: %s", $this->_print($params, true)));
                $this->error(sprintf("Expected: %s", $this->_print($expected)));
                $this->error(sprintf("Result: %s", $this->_print($result)));
            }
        }
        $this->countFalse++;
    }

    private function _print(mixed $toprint, bool $join = false): string
    {
        if (is_object($toprint)) {
            return sprintf("object %s", get_class($toprint));
        }

        if (is_array($toprint)) {
            $toprint = array_map(fn ($item) => $this->_print($item), $toprint);
            if ($join) {
                return join(', ', $toprint);
            }
        }

        return print_r($toprint, true);
    }

    /**
     * @param string $dataSource
     * @param string $methodName
     * @return array
     */
    private function getDataFromDataSource(string $dataSource, string $methodName): array
    {
        return $this->dataSourceProvider->getDataFromDataSource($dataSource, $methodName);
    }

    private function makeClassObject2(\ReflectionClass $classObject, string $methodName, array $mocks = []): array
    {
        $class = $classObject->getName();

        $exploded = explode("\\", $class);
        $shortenned = array_pop($exploded);

        $source = file_get_contents($classObject->getFileName());
        $md5 = md5($class . $methodName . print_r($mocks, true));
        $newClass = $shortenned . '_' . $methodName . '_' . $md5;
        if (array_key_exists($newClass, $this->_made)) {
            return $this->getFromMade($newClass);
        }
        if (count($mocks) > 0) {
            $source = $this->makeClassMocks($source, $classObject->getMethod($methodName), $mocks);
        }

        $source = preg_replace(sprintf('/\bclass %s/', $shortenned), sprintf('class %s', $newClass), $source);

        $this->includeSource($source, $newClass);

        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $source, $matches)) {
            $namespace = "\\" . $matches[1] . "\\";
        }
        $newFullClass = $namespace . $newClass;
        //  return new $newFullClass;

        $object = $this->container->get($newFullClass);
        $newReflection = new \ReflectionClass(get_class($object));

        $this->_made[$newClass] = [$object, $newReflection->getMethod($methodName)];

        return $this->getFromMade($newClass);
    }

    private function getFromMade(string $newClass): array
    {
        $return =  $this->_made[$newClass];
        $return[0] = clone $return[0];

        return $return;
    }


    private function includeSource(string $source, string $newClass): void
    {
        if ($this->includeMode === 'eval') {
            $source = preg_replace("/^<\?php/", '', $source);
            eval($source);
        } else {
            $tempFile = $newClass . '.php';
            $tempFilePath = $this->cache_dir . '/' . $tempFile;
            file_put_contents($tempFilePath, $source);
            require_once $tempFilePath;
        }
    }

    private function makeClassMocks(string $source, \ReflectionMethod $method, array $mocks): string
    {
        $start = $method->getStartLine();
        $end = $method->getEndLine();
        $splitten = explode("\n", $source);
        $methodLines = [];
        for($i = $start; $i < $end; $i++) {
            $methodLines[] = $splitten[$i];
        }
        $methodSource = join("\n", $methodLines);
        $methodSourceModified = $methodSource;
        $mockFunctions = [];
        foreach($mocks as $i => $mock) {
            try {
                [$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock['mock'], $i);
            } catch (TtException $e) {
                $this->alert($e->getMessage());
                continue;
            }
            $methodSourceModified = $this->replaceMethodSourceWithMock($methodSourceModified, $mock, $i);
            $mockFunctions[] = $mockFunctionSource;
        }

        $source = str_replace($methodSource, $methodSourceModified, $source);
        $source .= "\n";
        $source .= join("\n", $mockFunctions);

        return $source;
    }

    /**
     * @param string $source
     * @param string $mock
     * @return string
     * @php-tt-mock $this->createMockFunction >>> ['hello', '']
     * @php-tt-data php_tt_data
     */
    private function replaceMethodSourceWithMock(string $source, array $mock, int|string $index = 0): string
    {
        [$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock['mock'], $index);
        if ($mock['type'] === 'exact') {
            $find = $this->createMockFind($mock['mock'], false);
            $replace =  $mockFunctionName;
            if (!preg_match(sprintf("/%s\s*\(/", preg_quote($find)), $source)) {
                $replace .= '()';
            }
            return str_replace($find, $replace, $source);
        } else {
            $mockFind = $this->createMockFind($mock['mock']);
            return preg_replace($mockFind, $mockFunctionName . '(', $source);
        }
    }

    /**
     * @param string $mock
     * @return array
     * @php-tt-mock @md5 >>> #php_tt_data
     * @php-tt-assert 'hello >>> ""' >>> ['mock_123', 'function mock_123(){return "";}']
     * @php-tt-assert 'hello >>> [""]' >>> ['mock_123', 'function mock_123(){return [""];}']
     */
    private function createMockFunction(string $mock, string|int $index = 0): array
    {
        $split = explode('>>>', $mock);
        $return = $this->makeMockFunctionReturn(trim(array_pop($split)), trim($split[0]));
        if (null === $return) {
            throw new TtException("Could not create mock function for $mock");
        }
        $hash = md5(time() . $this->getCount() . $index);
        return [
            sprintf('mock_%s', $hash),
            sprintf("function mock_%s(){return %s;}", $hash, $return),
        ];
    }

    /**
     * @param string $return
     * @param string $defaultKey
     * @return string
     * @php-tt-mock $this->dataSourceProvider->replaceHashtaggedDatasourcesWithInclude >>> @@1
     * @php-tt-assert 'hello . @@1' >>> 'hello . func_get_arg(0)'
     */
    private function makeMockFunctionReturn(string $return, ?string $defaultKey = null): ?string
    {
        $return = $this->dataSourceProvider
            ->replaceHashtaggedDatasourcesWithInclude($return, $defaultKey);

        $return = $this->replaceDoubleAtWithArgv($return);

        return $return;
    }

    /**
     * @param string $string
     * @return string
     * @php-tt-assert '@@1' >>> 'func_get_arg(0)'
     * @php-tt-assert '@@1 . "hello" . @@2' >>> 'func_get_arg(0) . "hello" . func_get_arg(1)'
     */
    private function replaceDoubleAtWithArgv(string $string): string
    {
        return preg_replace_callback('/@@(\d+)/', function ($matches) {
            // $matches[1] contains the digit following '@@', which corresponds to the argument index.
            $argIndex = intval($matches[1]) - 1;

            return sprintf("func_get_arg(%s)", $argIndex);
        }, $string);
    }

    private function createMockFind(string $mock, $make = true): string
    {
        $split = explode('>>>', $mock);
        return $make ? $this->makeMockLeft(trim($split[0])) : trim($split[0]);
    }

    /**
     * @param $left
     * @return string
     * @php-tt-assert '@hello' >>> '/\\\\?hello\s*\(/'
     * @php-tt-assert '$this->hello' >>> '/\$this\s*->\s*hello\s*\(/'
     * @php-tt-assert '@$hello->world' >>> '/\$hello\s*->\s*world\s*\(/'
     * @php-tt-assert '@$hello  ->world' >>> '/\$hello\s*->\s*world\s*\(/'
     * @php-tt-assert '@App::run' >>> '/\\\\?App\s*::\s*run\s*\(/'
     * @php-tt-assert "@App  ::  \nrun" >>> '/\\\\?App\s*::\s*run\s*\(/'
     */
    private function makeMockLeft($left): string
    {
        $left = preg_replace('/^@/', '', $left);//legacy

        $prep = '';
        if (!preg_match('/^\$.+/', $left)) {
                $prep = '\\\\?';
        }
        $parts = preg_split('/(::|->)/', $left, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        //$join = "\\s*" . '->' . "\\s*";
        $parts = array_map(static function(string $part) {
            if (in_array($part, ['::', '->'])) {
                return sprintf("\\s*%s\\s*", $part);
            } else {
                return preg_quote(trim($part));
            }
        }, $parts);
        $return = '/' . $prep . join('', $parts) . '\s*\(/';
        return $return;
    }

    private function clearCache()
    {
        foreach(glob($this->cache_dir . '/*.php') as $file) {
            if(is_file($file)) {
                // Use unlink() function to delete the file
                unlink($file);
            }
        }
    }

    private function getCount()
    {
        return $this->count;
    }
    public function getDataDir()
    {
        return $this->data_dir;
    }

    protected function info(string $string): void
    {
        if (array_key_exists('info', $this->_output) && is_callable($this->_output['info'])) {
            $this->_output['info']($string);
        } else {
            echo "Info: " . $string . "\n";
        }
    }

    protected function error(string $string): void
    {
        if (array_key_exists('error', $this->_output) && is_callable($this->_output['error'])) {
            $this->_output['error']($string);
        } else {
            echo "Error: " . $string . "\n";
        }
    }

    protected function alert(string $string): void
    {
        if ($this->verbosity > 2 || $this->isShowWarnings) {
            if (array_key_exists('alert', $this->_output) && is_callable($this->_output['alert'])) {
                $this->_output['alert']($string);
            } else {
                echo "Alert: " . $string . "\n";
            }
        }
    }

    public function setOutputCallback(string $type, callable $callback): self
    {
        $this->_output[$type] = $callback;

        return $this;
    }

    public function setVerbosity(int $verbosity): self
    {
        $this->verbosity = $verbosity;

        return $this;
    }

    public function increaseVerbosity(int $plus = 1): self
    {
        $this->verbosity += $plus;

        return $this;
    }


    public function decreaseVerbosity(int $minus = 1): self
    {
        $this->verbosity -= $minus;

        return $this;
    }

    public function showWarnings(bool $value = true): self
    {
        $this->isShowWarnings = $value;

        return $this;
    }

    public static function enhance(string $key, callable $closure): void
    {
        self::$_enhancedCommands[$key] = $closure;
    }
}
