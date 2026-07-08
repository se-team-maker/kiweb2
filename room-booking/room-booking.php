<?php
$target = '/kiweb/public/room-booking/index.php';
$query = $_SERVER['QUERY_STRING'] ?? '';
if ($query !== '') {
    $target .= '?' . $query;
}
header('Location: ' . $target, true, 302);
exit;
