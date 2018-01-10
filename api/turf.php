<?php


	header('Content-Type: text/plain');

	include(dirname(__FILE__) . '/../rk-config.php');
	
	global $rkdb;


	$sql = "SELECT DISTINCT(stname) from voters where enroll='d' and active=1 and zip='04105' order by stname";

	$stnames = $rkdb -> get_results($sql);


	$streets = array();
	foreach($stnames as $row){
		$sql = "SELECT COUNT(*) from voters where enroll='d' and active=1 and zip='04105' and stname='" . $rkdb -> escape($row -> stname) . "' and city='falmouth'";
		$streets[$row -> stname] = $rkdb -> get_var($sql);
	}

	asort($streets);
	$streets = array_reverse($streets, true);

	print_r($streets);