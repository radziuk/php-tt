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
                ['mock' => 'createMockFind', 'type' => 'mock'],
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
                ['mock' => 'createMockFind', 'type' => 'mock'],
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
                ['mock' => 'createMockFind', 'type' => 'mock'],
            ], '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = hello($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);'
        ],

        3 => [
            [
                '[$mockFunctionName, $mockFunctionSource] = $this->createMockFunction($mock);
        $mockFind = $this->object->method($mock);
        return preg_replace($mockFind, $mockFunctionName, $source);',
                ['mock' => 'object->method', 'type' => 'mock'],
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
                ['mock' => 'object->method', 'type' => 'mock'],
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
                ['mock' => '@callFunction', 'type' => 'mock'],
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
                ['mock' => '@$global->callFunction', 'type' => 'mock'],
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
    ]
];
