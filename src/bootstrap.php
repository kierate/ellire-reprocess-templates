<?php

date_default_timezone_set(@date_default_timezone_get());

$autoloaderPathsToTry = array(
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
);

foreach ($autoloaderPathsToTry as $autoloaderPath) {
    if (file_exists($autoloaderPath)) {
        include $autoloaderPath;
        return true;
    }
}

echo "Ellire dependencies have to be installed before using it\n";
exit;