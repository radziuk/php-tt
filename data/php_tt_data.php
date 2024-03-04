<?php

return [
    '_return1' => '#1',
    'processTtDoRecord' => [
        0 => [
            ['@tt-do 1, 2 = 12'],
            [['1', '2'], '12', 'equals']
        ],
        1 => [
            ['@tt-do hello,   world = @fine!'],
            [['hello', 'world'], '@fine!', 'equals']
        ],
    ],
    'getDataKeyFromDataSource' => [
        0 => [
            ['DoTest.test', 'methodName'],
            'test'
        ],
        1 => [
            ['DoTest', 'methodName'],
            'methodName'
        ]
    ],
    'getFilenameFromDatasource' => [
        0 => [
            ['DoTest'],
            'DoTest.php'
        ],
        1 => [
            ['DoTest.methodName'],
            'DoTest.php'
        ],
        2 => [
            ['data-file_1.methodName'],
            'data-file_1.php'
        ],
    ],
    'getDataSourceFromDockblock' => [

    ],
    'isTestableFromDocblock' => [
        0 => [
            [
                '/**
         * @param string $dockblock
         * @return array
         * @php-tt-data data1
         */'
            ],
            true,
        ],

        1 => [
            [
                '/**
         * @param string $dockblock
         * @return array
         */'
            ],
            false,
        ],


    ],
    '@md5' => '123',

    'replaceMethodSourceWithMock' => [
        0 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = $this->createMockFind($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => '$this->createMockFind', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],

        1 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = $this
        ->  createMockFind($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => '$this->createMockFind', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],

        2 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = $this

        ->
                createMockFind        ($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => '$this->createMockFind', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],

        3 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = $this->object->method($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => '$this->object->method', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],
        4 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = $this
        ->
          object

            ->          method      ($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => '$this->object->method', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],

        5 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = callFunction
        ($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => 'callFunction', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],

        6 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = $global     ->

        callFunction        ($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => '$global->callFunction', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],
        7 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = \createMockFind($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => 'createMockFind', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],
        8 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = \request()->createMockFind($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => 'request()->createMockFind', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],
        9 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = \request("hello")->createMockFind($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => 'request("hello")->createMockFind', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],
        10 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = \request("hello", $world)
        ->createMockFind($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => 'request("hello", $world)->createMockFind', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],
    ],
    'getDataSourceInclude' => [
        1 => 'My.test',
        2 => "(include '/var/www/My.php')['test']",
    ],
    '_replace' => [
        'Hello, #Data.1, world', 'Hello, #replace, world'
    ],

    'getLinesFromDocblock' => [
        0 => [
            ['/**
         * @param string $dockblock
         * @return array
         * @php-tt-data data1
         */'], ['data data1']
        ],

        1 => [
            ['/**
         * @param string $dockblock
         * @return array
         * @php-tt-data data1
          @php-tt-data data1
         */'], ['data data1']
        ],

        2 => [
            ['/**
         * @param string $dockblock
         * @return array
         * @php-tt-data data1
         * @php-tt-data data1
          @php-tt-data data1
         * @php-tt-assert-exception "data1" >>> hello::class
         */'], ['data data1', 'data data1', 'assert-exception "data1" >>> hello::class']
        ],
        3 => [
            ['/**
         * @param string $dockblock
         * @return array
         * @php-tt-assert * @php-tt-assert
         */'], ['assert * @php-tt-assert']
        ],

        4 => [
            ['/**
         * @param string $dockblock
         * @return array
*       @php-tt-assert * @php-tt-assert
         */'], ['assert * @php-tt-assert']
        ],

        5 => [
            ['/**
         * @param string $dockblock
         * @return array
*       @php-tt-assert * @php-tt-assert
       @php-tt-assert * @php-tt-assert
         */'], ['assert * @php-tt-assert']
        ],
    ],
    'callable' => function($result, $param){
        return $result !== $param;
    },
    'callable_param' => true,
    'class_with_trait_source' => [
        '1' => "class Tt extends Test
{
    use TestTrait;
    use MoreTrait;
    }",
        '2' => "class Tt extends Test
{
    use Hello\\World\\TestTrait;
    use MoreTrait;
    }"
    ],
    'class_with_many_traits' => [
        '1' => "class Tt extends Test
{
    use TestTrait, TestTestTrait;
    }",
        '2' => "class Tt extends Test
{
    use TestTrait123, TestTestTrait;
    }"]
];
