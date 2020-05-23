<?php 
//session_start();
require_once("vendor/autoload.php");

use \Slim\Slim;
use \Just\Page;
use \Just\PageAdmin;

$app = new \Slim\Slim();

$app->config('debug', true);

$app->get('/', function() {
    
	$page = new Page();

	$page->setTpl("index");

});

$app->get('/admin', function() {
    
	$page = new PageAdmin();

	$page->setTpl("index");

});



$app->run();

 ?>