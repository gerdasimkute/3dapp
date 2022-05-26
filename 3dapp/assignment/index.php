<?php

// app expects to be at top level.
$_SERVER['REQUEST_URI'] = preg_replace('#^/~[a-z0-9]+/3dapp/assignment#', '', $_SERVER['REQUEST_URI']);

// error reporting
error_reporting(E_ALL ^ E_DEPRECATED);
ini_set('display_errors', 1);

require_once 'vendor/autoload.php';

// controller
require_once 'controller.php';
require_once 'model.php';

$model = new Model();
// do proper dependency injection
$controller = new Controller($model);

$controller->setup_pages();
$controller->serve_request();
