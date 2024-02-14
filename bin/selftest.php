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

    $Tt = new \Aradziuk\PhpTT\Tt();
    $Tt->run($srcDir, $dataDir);

