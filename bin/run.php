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

$args = $argv;
$verbosity = 2;
foreach($argv as $i => $value) {
    if (preg_match('/^\d$/', $value)) {
        $verbosity = intval($value);
        unset($args[$i]);
        $args = array_values($args);
        break;
    }
}


$classes_dir = $dir . '/' . ($args[1] ?? 'app');
$data_dir = $dir . '/' . ($args[2] ?? 'tests/php-tt-data');

if (!is_dir($classes_dir)) {
    die("$classes_dir is not a directory, quitting.");
}

$Tt = new \Radziuk\PhpTT\Tt();
$Tt->setVerbosity($verbosity)
    ->showWarnings($verbosity > 1);

$data_dir = is_dir($data_dir) ? $data_dir : '';

$Tt->run($classes_dir, $data_dir);


