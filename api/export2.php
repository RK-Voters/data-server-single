<?php

	$pathToDBLIB = dirname(__FILE__) . '/../rk-config.php';
	include($pathToDBLIB);
	
	global $rkdb;
	$conn = $rkdb -> conn;



	// open the output stream
	// $fh = fopen('dion_2018.csv', 'w');
        
	// // Start output buffering (to capture stream contents)
	// ob_start();

    $path = "/var/lib/mysql-files/rkvoters-export-york.csv";

	

	// // OUTPUT FIELDS
	$fields = array(
		'statefileid', 'support_level', 'firstname', 'lastname', 'middlename','suffix', 
		'dob', 'sex', 'enroll', 
		'stnum','stname','unit','city','county','state','zip');	

	// fputcsv($fh, $fields);


	
	// AND EXPORT...
	$sql = 	"SELECT " . implode(',', $fields) .
			" FROM voters
			WHERE enroll = 'D' and active=1 and county='York'
			INTO OUTFILE '" . $path . "'
			FIELDS TERMINATED BY ','
			ENCLOSED BY '\"'
			LINES TERMINATED BY '\n';";
	
	echo $sql;

	echo "\n\n\n";

  	mysqli_query($conn, $sql)or die($cons -> error);
