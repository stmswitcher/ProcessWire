<?php
if(!defined("PROCESSWIRE")) die();

// Общие параметры (не зависящие от среды)
$config->userAuthSalt = '0c93bcf2170197eb3e0e9c086d2d94c1';
$config->chmodDir     = '2775'; // permission for directories created by ProcessWire
$config->chmodFile    = '2775'; // permission for files created by ProcessWire
$config->timezone     = 'Europe/Moscow';

$config->prependTemplateFile = '_init.php';
$config->appendTemplateFile  = '_main.php';

// Определение окружения
$env = defined('APPLICATION_ENV') ? APPLICATION_ENV : false;

if ($env === "dev")
    include('./site/config/config-dev.php'); // Конфиг девелопера
else                                         // Тут еще можно добавить, например, конфиг тестера
    include('./site/config/config-prod.php'); // Конфиг прода