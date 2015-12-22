<?php
header('Content-Type: text/html; charset=utf-8');

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm=""');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Доступ ограничен.';
    exit;
}

$user = $_SERVER['PHP_AUTH_USER'];
$pass = $_SERVER['PHP_AUTH_PW'];

if ($user !== 'dev' || $pass !== 'm412bf') {
    header('WWW-Authenticate: Basic realm=""');
    header('HTTP/1.0 401 Unauthorized');
    echo '403 Неверные данные.';
    unset($_SERVER['PHP_AUTH_USER']);
    exit;
}