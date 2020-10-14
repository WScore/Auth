<?php

use Composer\Autoload\ClassLoader;

if (file_exists(__DIR__ . '/../vendor/')) {
    require_once(__DIR__ . '/../vendor/autoload.php');
} elseif (file_exists(__DIR__ . '/../../../../vendor/')) {
    require_once(__DIR__ . '/../../../../vendor/autoload.php');
} else {
    die('vendor directory not found');
}

