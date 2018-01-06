<?php

	include('../rk-config.php');
	global $rkdb;
	$conn = $rkdb -> conn;

    $path = getCwd() . "/../../backups/export-" . date("Y-m-d") . ".csv";

	



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