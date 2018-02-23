<?php

	// open the output stream
	// $fh = fopen('dion_2018.csv', 'w');
        
	// // Start output buffering (to capture stream contents)
	// ob_start();

	

	// // OUTPUT FIELDS
	$fields = array(
		'statefileid', 'support_level', 'firstname', 'lastname', 'middlename','suffix', 
		'dob', 'sex', 'enroll', 
		'stnum','stname','unit','city','county','state','zip');	

	// fputcsv($fh, $fields);


	// CONNECT TO DATABASE
    $cons = mysqli_connect("localhost", "root","root","dion_master") or die("Unable to connect to database.");


	// AND EXPORT...
	$sql = 	"SELECT " . implode(',', $fields) .
			" FROM voters
			WHERE enroll = 'D' and active=1
			INTO OUTFILE 'dion_2018.csv'
			FIELDS TERMINATED BY ','
			ENCLOSED BY '\"'
			LINES TERMINATED BY '\n';";
	
	echo $sql;

	echo "\n\n\n";

  	mysqli_query($cons, $sql)or die($cons -> error);
