<?php

	error_reporting(E_ALL);
	ini_set("display_errors", 1);
	header('Content-Disposition: attachment; filename="mailchimp_contacts.csv"');


	// connect to th

	$pathToDBLIB = dirname(__FILE__) . '/../rk-config.php';
	include($pathToDBLIB);
	
	global $rkdb;
	$conn = $rkdb -> conn;

	$sql = "SELECT firstname,lastname,email,zip,city,profession,employer from voters where email <> ''";
	$rows = $rkdb -> get_results($sql);

	$outstream = fopen("php://output", 'w');
  

	foreach($rows as $r){
	  	fputcsv($outstream, (array) $r);
	  }

