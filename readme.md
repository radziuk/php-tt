Install the package

```composer require aradziuk/php-tt```

Run tests

```php vendor/aradziuk/php-tt/bin/run.php```

**********

### Specify the folder

By default the runner uses the "app" folder to look for tests. You can specify a custom folder

```php vendor/aradziuk/php-tt/bin/run.php src/lib```


### Self-test the package

After the package is install you can run the selftest script included in the package
```
    php vendor/aradziuk/php-tt/bin/selftest.php
```

Should input something like this:
```
Info: Assertions done: 60
Info: Assertions true: 60
```

### Test examples

Simple test, pass "hello" to the function, should return "hello"
```php
    /**
     * @param string $string
     * @return string
     * @php-tt-assert "hello" >>> "hello"
     */
    public function replaceMarkers(string $string): string

```


Text example #2
```php
    /**
     * @param string $string
     * @return string
     * @php-tt-assert "'hello', 'world'" >>> "#1, #2"
     */
    public function replaceMarkers(string $string): string

```


Text example #3
```php
    /**
     * @param string $string
     * @param int $count
     * @return string
     * @php-tt-assert "'hello', 'world'", 1 >>> "#1, 'world'"
     */
    public function replaceMarkers(string $string, int $count): string

```

Text example #4, passing an array
```php
    /**
     * @param string $string
     * @param int $count
     * @return string
     * @php-tt-assert "'hello', 'world'", ['hello' => 'world'] >>> "#1, 'world'"
     */
    public function replaceMarkers(string $string, array $data): string

```

Text example #5, calling a method on the object before testing
```php
    /**
     * @param string $string
     * @param int $count
     * @return string
     * @php-tt-before $object->setPattern('#%s')
     * @php-tt-assert "'hello', 'world'", ['hello' => 'world'] >>> "#1, 'world'"
     */
    public function replaceMarkers(string $string, array $data): string

```

Text example #6, mocking $this->... method
```php
    /**
     * @param string $dataSource
     * @param string $methodName
     * @return array
     * @php-tt-mock getFilenameFromDatasource >>> 'test.php'
     * @php-tt-assert 'Any.key', 'default' >>> ['default/test.php']
     */
    private function getFileForDataSource(string $dataSource, string $methodName): array
    {
        $dataDir = $this->getDataDirForMethod($methodName);
        $fileName = $this->getFilenameFromDatasource($dataSource);
        $file = $dataDir . '/' . $fileName;
        return [$file];
    }

```


Text example #7, mocking  method of a dependency
```php
    /**
     * @param string $dataSource
     * @param string $methodName
     * @return array
     * @php-tt-mock service->getFilenameFromDatasource >>> 'test.php'
     * @php-tt-assert 'Any.key', 'default' >>> ['default/test.php']
     */
    private function getFileForDataSource(string $dataSource, string $methodName): array
    {
        $dataDir = $this->getDataDirForMethod($methodName);
        $fileName = $this->service->getFilenameFromDatasource($dataSource);
        $file = $dataDir . '/' . $fileName;
        return [$file];
    }

```


Text example #8, mocking global functions and methods
```php
    /**
     * @param string $dataSource
     * @param string $methodName
     * @return array
     * @php-tt-mock @$service->getFilenameFromDatasource >>> 'test.php'
     * @php-tt-mock @getDataDirForMethod >>> 'default'
     * @php-tt-assert 'Any.key', 'default' >>> ['default/test.php']
     */
    private function getFileForDataSource(string $dataSource, string $methodName): array
    {
        $dataDir = getDataDirForMethod($methodName);
        $fileName = $service->getFilenameFromDatasource($dataSource);
        $file = $dataDir . '/' . $fileName;
        return [$file];
    }

```

Text example #9, mocking a property
```php
    /**
     * @param string $dataSource
     * @param string $methodName
     * @return array
     * @php-tt-mock @$service->getFilenameFromDatasource >>> 'test.php'
     * @php-tt-exact-mock $this->data_dir >>> '/var/www'
     * @php-tt-assert 'Any.key', 'default' >>> ['/var/www/test.php']
     */
    private function getFileForDataSource(string $dataSource, string $methodName): array
    {
        $dataDir = $this->data_dir;
        $fileName = $service->getFilenameFromDatasource($dataSource);
        $file = $dataDir . '/' . $fileName;
        return [$file];
    }

```

Text example #10, mocking anything
```php
    /**
     * @param string $dataSource
     * @param string $methodName
     * @return array
     * @php-tt-mock @self::getFilenameFromDatasource >>> 'test.php'
     * @php-tt-exact-mock require $methodName >>> '/var/www'
     * @php-tt-assert 'Any.key', 'default' >>> ['/var/www/test.php']
     */
    private function getFileForDataSource(string $dataSource, string $methodName): array
    {
        $dataDir = require $methodName;
        $fileName = self::getFilenameFromDatasource($dataSource);
        $file = $dataDir . '/' . $fileName;
        return [$file];
    }

```

### Define your test data in a separate file

By default, the runner is looking for tests/php-tt-data folder, so you can create your data files there

Create a data file
```
    touch tests/php-tt-data/TestData.php
```

In your data file
```php
<?php

    return [

        'getFileForDataSource' => [
            0 => [
                ['DoTest.test', 'methodName'],//parameters as array
                'test',//expected result
            ],
            1 => [
                ['DoTest', 'methodName'],
                'methodName'
            ]
        ],
];
```

In your class

```php
    /**
     * @param string $dataSource
     * @param string $methodName
     * @return array
     * @php-tt-mock @self::getFilenameFromDatasource >>> 'test.php'
     * @php-tt-exact-mock require $methodName >>> '/var/www'
     * @php-tt-data TestData
     */
    private function getFileForDataSource(string $dataSource, string $methodName): array

```

### Use custom keys in your data file. The default key is the method name.

In your data file
```php
<?php

    return [

        '__my_custom_key__' => [
            0 => [
                ['DoTest.test', 'methodName'],//parameters as array
                'test',//expected result
            ],
            1 => [
                ['DoTest', 'methodName'],
                'methodName'
            ]
        ],
];
```

In your class

```php
    /**
     * @param string $dataSource
     * @param string $methodName
     * @return array
     * @php-tt-mock @self::getFilenameFromDatasource >>> 'test.php'
     * @php-tt-exact-mock require $methodName >>> '/var/www'
     * @php-tt-data TestData.__my_custom_key__
     */
    private function getFileForDataSource(string $dataSource, string $methodName): array

```

### Use data files to create mocks

In your class

```php
    /**
     * @param string $dataSource
     * @param string $methodName
     * @return array
     * @php-tt-mock @self::getFilenameFromDatasource >>> 'test.php'
     * @php-tt-exact-mock require $methodName >>> @TestData.my_mock
     * @php-tt-data TestData
     */
    private function getFileForDataSource(string $dataSource, string $methodName): array

```

In your data file
```php
<?php
    
    return [

        'my_mock' => (function(){
            return 'hello';
        })(),
];
```

### Use custom data folder
```php vendor/aradziuk/php-tt/bin/run.php app tests/my-folder```

### Increase verbosity of the output

```php vendor/aradziuk/php-tt/bin/run.php 3```

Any 1-digit number is interpreted as verbosity level. You can specify you custom folder and verbosity level like the following:

```php vendor/aradziuk/php-tt/bin/run.php custom/app 3```

```php vendor/aradziuk/php-tt/bin/run.php custom/app custom/data 3```


### Create your custom runner

```touch testrunner.php```

```php
<?php

require __DIR__.'/vendor/autoload.php';

$tt = new \Aradziuk\PhpTT\Tt();

$tt->run(
    __DIR__ . '/app', //dir with your classes
    __DIR__ . '/test/php-tt' // dir with your data
);
```