<?php

define('KIWEB_ROOT', dirname(__DIR__, 3));
define('ROOM_BOOKING_APP_ROOT', KIWEB_ROOT . '/app/RoomBooking');
define('ROOM_BOOKING_CONFIG_ROOT', KIWEB_ROOT . '/config/room-booking');

$config = require ROOM_BOOKING_CONFIG_ROOT . '/config.php';

$timezone = $config['api']['timezone'] ?? 'Asia/Tokyo';
date_default_timezone_set($timezone);

spl_autoload_register(function ($class) {
    $path = ROOM_BOOKING_APP_ROOT . '/lib/' . $class . '.php';
    if (is_file($path)) {
        require $path;
    }
});

return $config;
