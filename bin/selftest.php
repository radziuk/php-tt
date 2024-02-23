<?php

    $directory = __DIR__;
    $dataDir = $directory . '/../data';
    $srcDir = $directory . '/../src';

    $dir = null;
    $dirs = [getcwd(), __DIR__ . '/../../../..'];
    foreach ($dirs as $d) {
        if (file_exists($d . '/vendor/autoload.php')) {
            $dir = $d;
            break;
        }
    }

    if (null === $dir) {
        die("Could not determine vendor/autoload.php location, quitting.");
    }

    require_once $dir . '/vendor/autoload.php';

    \Radziuk\PhpTT\Tt::enhance('not-equals', function(\ReflectionMethod $method, $object, array $params, $expected): array
    {
        $result = $method->invoke($object, ...$params);
        return [$result !== $expected, $result];
    });

    $Tt = new \Radziuk\PhpTT\Tt();

    $cache_dir = '';
    // $cache_dir = getcwd() . '/storage/tt-cache';
    $Tt->run($srcDir, $dataDir, $cache_dir);

