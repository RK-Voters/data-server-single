<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

/**
* @package RK VOTERS
*/
/*
Plugin Name: RK VOTERS
Plugin URI: http://robkforcouncil.com/
Description: Super simple campaign management tool.
Version: 1.0.0
Author: Rob Korobkin
Author URI: http://robkorobkin.org
License: GPLv2 or later
Text Domain: crowdfolio
*/

header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Headers: X-Requested-With, content-type, access-control-allow-origin, access-control-allow-methods, access-control-allow-headers');


include('../rk-config.php');
include('../models/model-app.php');

// load API model (reads request and handles login in constructor)
$data_model = new RKVoters_Model();
$request 		= $data_model -> request;





// process api
if(isset($request['api'])){
	extract($request);

	// check if specified api exists?
	if(!method_exists($data_model, $api)){
		echo json_encode(array("error" => $api . " is not a valid method."));
		exit;
	}

	$response = $data_model -> $api();
	echo json_encode($response, JSON_PRETTY_PRINT);
	exit;
}



echo json_encode(array("error" => "No API or EXPORT requested."));
