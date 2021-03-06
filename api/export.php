<?php

	$pathToDBLIB = dirname(__FILE__) . '/../rk-config.php';
	include($pathToDBLIB);
	
	global $rkdb;
	$conn = $rkdb -> conn;


	// THIS IS THE PATH ON DEV - IF YOU WANT IT TO RUN LOCALLY, YOU'LL HAVE TO CHANGE IT...
    $path = "/var/lib/mysql-files/rkvoters-export-" . date("Y-m-d") . ".csv";

	

	
	// $result = mysqli_query($conn, 'SHOW VARIABLES LIKE "secure_file_priv"');
	// if (!$result) {
	//     echo 'Could not run query: ' . mysqli_error();
	//     exit;
	// }
	// if (mysqli_num_rows($result) > 0) {
		
	//     while ($row = mysqli_fetch_assoc($result)) {
	//         print_r($row);
	//     }
	// }


	$sql = "SELECT * FROM voters
			INTO OUTFILE '" . $path . "'
			FIELDS ENCLOSED BY '\"' 
			TERMINATED BY ';' 
			ESCAPED BY '\"' 
			LINES TERMINATED BY '\r\n'";


	mysqli_query($conn, $sql) or die($conn -> error);



	$result = mysqli_query($conn, "SHOW COLUMNS FROM voters");
	if (!$result) {
	    echo 'Could not run query: ' . mysqli_error();
	    exit;
	}
	if (mysqli_num_rows($result) > 0) {
		$fields = array();
	    while ($row = mysqli_fetch_assoc($result)) {
	        $fields[] = $row['Field'];
	    }
	}

	$fields_str = "\n--\n". implode(',', $fields);

	file_put_contents($path, $fields_str, FILE_APPEND);