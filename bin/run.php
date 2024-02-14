<?php

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

    $classes_dir = $dir . '/' . ($argv[1] ?? 'app');
    $data_dir = $dir . '/' . ($argv[2] ?? 'tests/php-tt-data');

    if (!is_dir($classes_dir)) {
        die("$classes_dir is not a directory, quitting.");
    }

    $Tt = new \Aradziuk\PhpTT\Tt();

    $data_dir = is_dir($data_dir) ? $data_dir : '';

    $Tt->run($classes_dir, $data_dir);


