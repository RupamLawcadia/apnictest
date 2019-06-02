<?php

define('ROOT', dirname(__DIR__));

//Confif File
require '/Lawcadia_API/API_4_0_CONFIG/config.php';

//Origin Header

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Headers: Auth-Token');
header('Access-Control-Allow-Headers: Public-Api-Token');

header('X-Content-Type-Options: nosniff');
header("X-XSS-Protection: 1;mode=block");

//Content Type Header
header('Content-Type: application/json');

//Disable Cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

//Add API Version Header
header('Lawcadia-APP-Last-Updated: 3-Aug-2017-11-16-AM');

//Custom Headers
require ROOT.'../../headers.php';

//Composer
//require '../../composer/vendor/autoload.php'; 
//SQL table mapper to map ids and names automatically
require ROOT.'/system/sql_table_mapper.php';

//Load PHP Required Files
require ROOT.'/system/ext_notifications.class.php';
require ROOT.'/system/errors_class.class.php';
require ROOT.'/route_paths.php';
require ROOT.'/system/router_class.php';
require ROOT.'/system/database_connector_class.php';

//CO - Classes Object

$CO = (object) array();
$CO->errors_class = new errors_class();
$CO->database_connector = new database_connector($CO->errors_class);
$CO->models = new models($CO);
$CO->validation = new validation($CO->errors_class);
$CO->hybrid_db = new hybrid_db($CO);

//Routing
$router_class = new router_class($routes, $CO);
$router_class->map_routes();
