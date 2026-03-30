<?php

$config = require __DIR__ . '/config.php';

$timezone = $config['api']['timezone'] ?? 'Asia/Tokyo';
date_default_timezone_set($timezone);

spl_autoload_register(function ($class) {
    $path = __DIR__ . '/lib/' . $class . '.php';
    if (is_file($path)) {
        require $path;
    }
});

return $config;
