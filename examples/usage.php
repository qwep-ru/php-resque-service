<?php 
ini_set('apc.enabled', 0);
ini_set('apc.enable_cli', 0);

date_default_timezone_set("UTC");

require __DIR__ . '/../vendor/autoload.php';




$service = new ResqueService\Service(ResqueService\Service::SERVICE_NODE);
$service->work();
