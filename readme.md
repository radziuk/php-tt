Install the package

```composer require --dev radziuk/php-tt```

Run tests

```php vendor/radziuk/php-tt/bin/run.php```

Use Laravel integrated runner

```php vendor/radziuk/php-tt/bin/lararun.php```

**********

### Specify the folder

By default the runner uses the "app" folder to look for tests. You can specify a custom folder

```php vendor/radziuk/php-tt/bin/run.php src/lib```


### Self-test the package

After the package is install you can run the selftest script included in the package
```
php vendor/radziuk/php-tt/bin/selftest.php
```

Should input something like this:
```
Info: Assertions done: 89
Info: Assertions true: 89
```

### Test examples

Simple test, pass "hello" to the function, should return "hello"
```php
    /**
     * @php-tt-assert "hello" >>> "hello"
     */
    public function replaceMarkers(string $string): string

```


Text example #2
```php
    /**
     * @php-tt-assert "'hello', 'world'" >>> "#1, #2"
     */
    public function replaceMarkers(string $string): string
```


Text example #3
```php
    /**
     * @php-tt-assert "'hello', 'world'", 1 >>> "#1, 'world'"
     */
    public function replaceMarkers(string $string, int $count): string

```

Text example #4, passing an array
```php
    /**
     * @php-tt-assert "'hello', 'world'", ['hello' => 'world'] >>> "#1, 'world'"
     */
    public function replaceMarkers(string $string, array $data): string

```

Text example #5, calling a method on the object before testing
```php
    /**
     * @php-tt-before $object->setPattern('#%s')
     * @php-tt-assert "'hello', 'world'", ['hello' => 'world'] >>> "#1, 'world'"
     */
    public function replaceMarkers(string $string, array $data): string

```

Text example #6, mocking $this->... method
```php
    /**
     * @php-tt-mock $this->getFilenameFromDatasource >>> 'test.php'
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
     * @php-tt-mock $this->service->getFilenameFromDatasource >>> 'test.php'
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
     * @php-tt-mock $service->getFilenameFromDatasource >>> 'test.php'
     * @php-tt-mock getDataDirForMethod >>> 'default'
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
     * @php-tt-mock @$service->getFilenameFromDatasource >>> 'test.php'
     * @php-tt-mock $this->data_dir >>> '/var/www'
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
     * @php-tt-mock self::getFilenameFromDatasource >>> 'test.php'
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
     * @php-tt-mock self::getFilenameFromDatasource >>> 'test.php'
     * @php-tt-exact-mock require $methodName >>> '/var/www'
     * @php-tt-data TestData.__my_custom_key__
     */
    private function getFileForDataSource(string $dataSource, string $methodName): array

```

### Use data files to create mocks

In your class

```php
    /**
     * @php-tt-mock self::getFilenameFromDatasource >>> 'test.php'
     * @php-tt-exact-mock require $methodName >>> #TestData.my_mock
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
### Other assertions

#### Assert exception. 

This asserts that the method will throw any exception
```php
    /**
     * @php-tt-assert-exception "'hello', 'world'"
     */
    public function replaceMarkers(string $string): string
```

This asserts that the method will throw an exception of a certain class
```php
    /**
     * @php-tt-assert-exception "'hello', 'world'" >>> \App\My\Exception::class
     */
    public function replaceMarkers(string $string): string
```

#### Assert exception contains
This asserts that the method will throw an exception and the message will contain the text
```php
    /**
     * @php-tt-assert-exception-contains "'hello', 'world'" >>> "My error text"
     */
    public function replaceMarkers(string $string): string
```

#### Assert  contains & preg
This asserts that the result will contain the specified substring
```php
    /**
     * @php-tt-assert-contains "'hello', 'world'" >>> "My error text"
     */
    public function replaceMarkers(string $string): string
```

This asserts that the result will match the specified regular expression
```php
    /**
     * @php-tt-assert-preg "'hello', 'world'" >>> "/^.*$/"
     */
    public function replaceMarkers(string $string): string
```

#### Assert callable
This assertion allows you do define a callable that will handle the result of the method's execution and perform comparison with the expected result
```php
    /**
     * @php-tt-assert-callable "hello, world" >>> [#TestData.my_callable, 'expected result']
     */
    public function replaceMarkers(string $string): string
```
In your tests/php-tt-data/TestData.php
```php
return [
    'my_callable' => function(stdClass $result, $expected):bool {
        return $result->property === $expected;
    },
];

```

Pass more parameters
```php
    /**
     * @php-tt-assert-callable "hello, world" >>> [#TestData.my_callable, 'expected result', false]
     */
    public function replaceMarkers(string $string): string
```

In your tests/php-tt-data/TestData.php
```php
return [
    'my_callable' => function(stdClass $result, $expected, $true = true):bool {
        return $true ? $result->property === $expected : $result->property !== $expected;
    },
];

```

#### Create an alias
```php
    /**
     * @php-tt-alias "property-equals" >>> #TestData.property_equals
     * @php-tt-alias "property-not-equals" >>> #TestData.property_not_equals
     * use your aliases
     * @php-tt-assert-property-equals 'parameter' >>> 'expected'
     * @php-tt-assert-property-not-equals 'parameter' >>> 'expected'
     */
    public function replaceMarkers(string $string): string
```

In your tests/php-tt-data/TestData.php
```php
return [
    'property_equals' => function(stdClass $result, $expected):bool {
        return $result->property === $expected;
    },
    'property_not_equals' => function(stdClass $result, $expected):bool {
        return $result->property !== $expected;
    },
];

```

### Use custom data folder
```php vendor/radziuk/php-tt/bin/run.php app tests/my-folder```

### Increase verbosity of the output

```php vendor/radziuk/php-tt/bin/run.php 3```

Any 1-digit number is interpreted as verbosity level. You can specify you custom folder and verbosity level like the following:

```php vendor/radziuk/php-tt/bin/run.php custom/app 3```

```php vendor/radziuk/php-tt/bin/run.php custom/app custom/data 3```


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
## Support of traits
As of version 0.3 the support of trait has been added.
During the testing, an anonymous class implementing the trait is initialized. To override this default behaviour you can use @php-tt-use-class
```php
    trait MyTrait {
    /**
     * @php-tt-use-class My\Namespace\CustomClass
     * the above command will tell PhpTT to use the object of My\CustomClass for testing this method. The class should use the trait 
     * @php-tt-assert "#1, #2" >>> 'hello, world'
     */
    public function replaceMarkers(string $string): string
```


## Laravel integration

### lararun.php

lararun.php boots your laravel without database, logs and other providers, so in your tests you have access to lots of Laravel functionality, eg. facades, various providers etc
Please note that currently lararun.php is in alpha version, and it is highly recommended to mock out all the code that can cause permanent changes to your data  

```php vendor/radziuk/php-tt/bin/lararun.php```

### Create your custom Laravel command

```php artisan make:command PhpTT```

In app/Console/Commands/PhpTT.php

```php
    $tt = new \Radziuk\PhpTT\Tt();
    $tt->setOutputCallback('info', function (string $string) {
        $this->info($string);
    })->setOutputCallback('error', function (string $string){
        $this->error($string);
    })->setOutputCallback('alert', function (string $string){
        $this->alert($string);
    }); // use artisan generic out put
    
    $tt->run(
        app_path(),
        base_path('tests/php-tt-data')
    );
```

In app/Console/Kernel.php

```php
    
    protected $commands = [
        PhpTT::class,
    ];
```

```
php artisan app:php-tt
```

### Create your custom assertion (same as alias)
```php
    \Radziuk\PhpTT\Tt::enhance('greater-than', function(\ReflectionMethod $method, $object, array $params, $expected): array
    {
        $result = $method->invoke($object, ...$params);
        return [$result > $expected, $result];
    });
```

In your tests
```php
    /**
     * @php-tt-assert-greater-than 2, 2 >>> 3
     */
    public function multiply(int $x, int $y): int
```