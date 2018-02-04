<?php

	$city = "Durham";


	header('Content-Type: text/plain');

	include(dirname(__FILE__) . '/../rk-config.php');
	
	global $rkdb;


	$sql = "SELECT DISTINCT(stname) from voters where enroll='d' and active=1 and city='$city' order by stname";

	$stnames = $rkdb -> get_results($sql);


	$streets = array();
	foreach($stnames as $row){
		$sql = "SELECT COUNT(*) from voters where enroll='d' and active=1 and stname='" . $rkdb -> escape($row -> stname) . "' and city='$city'";
		$streets[$row -> stname] = $rkdb -> get_var($sql);
	}

	asort($streets);
	$streets = array_reverse($streets, true);

	print_r($streets);


$BANGOR = array(
	"Ohio St" => 186, 
    "Essex St" => 165, 
    "Union St" => 76, 
    "Maple St" => 75, 
    "Fern St" => 70, 
    "Broadway" => 68, 
    "Kenduskeag Ave" => 66, 
    "Husson Ave" => 60, 
    "Finson Rd" => 54, 
    "Elm St" => 52, 
    "Birch St" => 51, 
    "Grove St" => 50, 
    "Pearl St" => 47, 
    "Hammond St" => 43, 
    "Forest Ave" => 42, 
    "Main St" => 42, 
    "Stillwater Ave" => 42, 
    "Silver Rd" => 40, 
    "W Broadway" => 40, 
    "Lincoln St" => 38, 
    "Center St" => 37, 
    "Seventh St" => 36, 
    "Parkview Ave" => 34, 
    "Norway Rd" => 34, 
    "Hancock St" => 33, 
    "Howard St" => 32, 
    "Thornton Rd" => 32, 
    "State St" => 31, 
    "Harlow St" => 30, 
);
