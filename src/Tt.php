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
            }
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
     */
    private function isTestableFromDocblock(string $dockblock): bool
    {
        return preg_match('/@php-tt-/', $dockblock);
    }

    /**
     * @param string $dockblock
     * @return array
     * @php-tt-data php_tt_data
     */
    private function getDataSourceFromDockblock(string $dockblock): ?string
    {
        $splitted = explode("\n", $dockblock);

        foreach($splitted as $line) {
            if (preg_match('/@php-tt-data (.*)/', $line, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    private function doTests(array $parsedMap): void
    {
        foreach($parsedMap as $className => $parsedClass) {
            [$reflectionObject, $methods] = array_values($parsedClass);

            array_walk($methods, function (\ReflectionMethod $method) use($reflectionObject){
                $docBlock = $method->getDocComment();
                $dataSource = $this->getDataSourceFromDockblock($docBlock);

                $data = [];
                if ($dataSource) {
                    $data = array_merge($data, $this->getDataFromDataSource($dataSource, $method->getName()));
                }

                $data = array_merge($data, $this->getDataFromDockblock($docBlock));

                $additional = $this->getAdditionalFromDockblock($docBlock);

                $this->doTest($method, $reflectionObject, $data, $additional);
            });
        }
    }

    private function doTest(\ReflectionMethod $method, \ReflectionClass $classObject, array $data, array $additional = []): void
    {
        $signature = $classObject->getName() . '::' . $method->getName();

        if (!count($data)) {
            $this->alert("No data provided for $signature. Skipping");
            return ;
        }

        $objectToClone = $this->makeClassObject($classObject, $method->getName(), $additional['mock'] ?? []);
        if (get_class($objectToClone) !== $classObject->getName()) {
            $newReflection = new \ReflectionClass(get_class($objectToClone));
            $method = $newReflection->getMethod($method->getName());
        }


        if (!$method->isPublic()) {
            $method->setAccessible(true);
        }

        $allowed_commands = array_keys($this->_commands);
        foreach($data as $item) {
            if (!array_key_exists(2, $item)) {
                $item[2] = 'equals';
            }
            [$params, $expected, $command] = $item;
            if (!in_array($command, $allowed_commands)) {
                $this->alert("Unknown command `$command`. Skipping");
                continue;
            }

            $object = clone $objectToClone;
            if (array_key_exists('before', $additional)) {
                foreach($additional['before'] as $runCommand) {
                    eval($runCommand);
                }
            }

            $executionResult = $this->_commands[$command]($method, $object, $params, $expected);
            [$assertResult, $actualResult] = $executionResult;

            if ($assertResult) {
                $this->assertSuccessful($command, $params, $expected, $actualResult, $method->getName(), $classObject->getName());
            } else {
                $this->assertFailed($command, $params, $expected, $actualResult, $method->getName(), $classObject->getName());
            }

            $this->count++;
        }

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
                $this->error(sprintf("Parameters: %s", join(',', $params)));
                $this->error(sprintf("Expected: %s", print_r($expected, true)));
                $this->error(sprintf("Result: %s", print_r($result, true)));
            }
        }
        $this->countFalse++;
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

    /**
     * @param string $dataSource
     * @return string
     */
    private function getFilenameFromDatasource(string $dataSource): string
    {
        return $this->dataSourceProvider->getFilenameFromDatasource($dataSource);
    }

    private function makeClassObject(\ReflectionClass $classObject, string $methodName, array $mocks = []): mixed
    {
        $class = $classObject->getName();

        $exploded = explode("\\", $class);
        $shortenned = array_pop($exploded);

        $source = file_get_contents($classObject->getFileName());
        $tmp = md5(time());
        $newClass = $shortenned . '_' . $methodName . '_' . $tmp;
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

        return $this->container->get($newFullClass);
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
     * @php-tt-data php_tt_data
     * @php-tt-mock createMockFunction >>> ['hello', '']
     */
    private function replaceMethodSourceWithMock(string $source, array $mock, int $index = 0): string
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
    private function createMockFunction(string $mock, $index = 0): array
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
     * @php-tt-mock dataSourceProvider->replaceHashtaggedDatasourcesWithInclude >>> @@1
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
     * @php-tt-assert '@hello' >>> '/hello\s*\(/'
     * @php-tt-assert 'hello' >>> '/\$this\s*->\s*hello\s*\(/'
     * @php-tt-assert '@$hello->world' >>> '/\$hello\s*->\s*world\s*\(/'
     * @php-tt-assert '@$hello  ->world' >>> '/\$hello\s*->\s*world\s*\(/'
     * @php-tt-assert '@App::run' >>> '/App\s*::\s*run\s*\(/'
     * @php-tt-assert "@App  ::  \nrun" >>> '/App\s*::\s*run\s*\(/'
     */
    private function makeMockLeft($left): string
    {
        $start = ['$this', '->'];
        if (preg_match('/^@.+/', $left)) {
            $left = preg_replace('/^@/', '', $left);
            $start = [];
        }
        $parts = array_merge($start, preg_split('/(::|->)/', $left, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY));
        //$join = "\\s*" . '->' . "\\s*";
        $parts = array_map(static function(string $part) {
            if (in_array($part, ['::', '->'])) {
                return sprintf("\\s*%s\\s*", $part);
            } else {
                return preg_quote(trim($part));
            }
        }, $parts);
        return '/' . join('', $parts) . '\s*\(/';
    }

    /**
     * @param string $dataSource
     * @param string $methodName
     * @return string
     */
    private function getDataKeyFromDataSource(string $dataSource, string $methodName): string
    {
        return $this->dataSourceProvider->getDataKeyFromDataSource($dataSource, $methodName);
    }

    private function getDataFromDockblock(string $docBlock): array
    {
        $result = [];

        $splitted = explode("\n", $docBlock);

        foreach($splitted as $line) {
            $line = $this->prepareDocLine($line);
            $line = $this->prepareAliases($line);
            if (preg_match('/^(@php-tt-go.*)/', $line, $match)) {
                if (null !== $data = $this->processTtGoRecord($match[1])) {
                    $result[] = $data;
                }
            }
        }

        return $result;
    }

    /**
     * @param string $line
     * @return string
     * @php-tt-assert '    * @hello' >>> '@hello'
     * @php-tt-assert '    * @hello@world' >>> '@hello@world'
     * @php-tt-assert "    * @php-tt-assert '@php-tt-go'" >>> "@php-tt-assert '@php-tt-go'"
     */
    private function prepareDocLine(string $line): string
    {
        $atPosition = strpos($line, '@');
        if ($atPosition !== false) {
            return trim(substr($line, $atPosition));
        }

        return trim($line);
    }

    private function prepareAliases(string $line): string
    {
        return preg_replace('/^@php-tt-assert/', '@php-tt-go', $line);
    }

    /**
     * @param string $ttGo
     * @return array|null
     * @php-tt-mock ttGoParser->parse >>> null
     * @php-tt-assert "world 123" >>> null
     */
    private function processTtGoRecord(string $ttGo): ?array
    {
        return $this->ttGoParser->parse($ttGo);
    }

    private function getAdditionalFromDockblock(string $docBlock): array
    {
        $result = [];

        $splitted = explode("\n", $docBlock);

        foreach($splitted as $line) {
            $line = $this->prepareDocLine($line);
            if (preg_match('/^@php-tt-before (.*)/', $line, $match)) {
                if (!array_key_exists('before', $result)) {
                    $result['before'] = [];
                }
                $before = trim($match[1]);

                if (!preg_match('/;$/', $before)) {
                    $before .= ';';
                }

                $result['before'][] = $before;
            }

            if (preg_match('/^@php-tt-mock (.*)/', $line, $match)) {
                if (!array_key_exists('mock', $result)) {
                    $result['mock'] = [];
                }
                $mock = trim($match[1]);

                $result['mock'][] = ['type' => 'mock', 'mock' => $mock];
            }

            if (preg_match('/^@php-tt-exact-mock (.*)/', $line, $match)) {
                if (!array_key_exists('mock', $result)) {
                    $result['mock'] = [];
                }
                $mock = trim($match[1]);

                $result['mock'][] = ['type' => 'exact', 'mock' => $mock];
            }

        }

        return $result;

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
