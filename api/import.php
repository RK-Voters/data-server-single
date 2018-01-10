<?php

	/* FLOW

		Read MailChimp CSV into PHP object

		
		Iterate through object for all three sheets:
			
			- If there's already a user with that email address, skip.

			- If not, add it.

			- If there's two, flag it.


		Unsubscribes:

			- Flip "email_opt_out" flag.

			- Record Unsubscribe as a contact.


		Cleans:

			- Remove email.
	


		
		Opens:

			- Read emails out of CSV and campaign name out of filename.

			- Iterate through and record each open as a contact.


		

	*/


	header('Content-Type: text/plain');

	include(dirname(__FILE__) . '/../rk-config.php');
	
	global $rkdb;



	// EXPORT OUT OF OLD DB
	// $rkdb -> conn -> query('SET CHARACTER SET utf8');
	// $sql = 'SELECT * FROM voters_contacts';
	// $voters = $rkdb -> get_results($sql);
	// $txt =  json_encode($voters, JSON_PRETTY_PRINT);
	// echo ($txt) ? $txt : json_last_error_msg();	


	//IMPORT INTO NEW DB
	$votersRaw = json_decode(file_get_contents("../data/old_crm.json"));
	$missingEmails = 0; 

	foreach($votersRaw as $voter){
		$voter = (array) $voter;

		// $voter['vanid'] = $voter['rkid'];
		// unset($voter['rkid']);
		// unset($voter['campaignId']);
		// unset($voter['streetId']);		
		// $voter['support_level'] = 2;
		// $voter['email_opt_in'] = 1;




		$update = array();

		// these are yielding 1s when they shouldn't be...
		$update['volunteer'] 		= ($voter['volunteer'] == 'true') ? 1 : 0;
		$update['wants_sign'] 		= ($voter['wants_sign'] == 'true') ? 1 : 0;
		$update['host_event']		= ($voter['host_event'] == 'true') ? 1 : 0;
		$update['volunteer_other'] 	= ($voter['volunteer_other'] == 'true') ? 1 : 0;

		$status = $update['volunteer'] . ' ' . $update['wants_sign'] . ' ' . $update['host_event'] . ' ' . $update['volunteer_other'];

		if($status == "0 0 0 0") continue;

		echo $voter['firstname'] . ' ' . $voter['lastname'] . "\n$status\n\n";

	

		if($voter['email'] == ''){
			echo "No email for: " . $voter['firstname'] . ' ' . $voter['lastname'] . "\n";
			$missingEmails++;
			continue;
		}


		$where = array("email" => $voter["email"]);
		$rkdb -> updateOrCreate("voters", $update, $where);


		

	}

	echo "Missing $missingEmails emails.";


	// $contactsRaw = json_decode(file_get_contents("../data/old_crm_contacts.json"));
	// foreach($contactsRaw as $contact){
	// 	$contact = (array) $contact;

	// 	print_r($contact);

	// 	$sql = 'SELECT * FROM voters where vanid=' . $contact['rkid'];
	// 	$voter = (array) $rkdb -> get_row($sql);

	// 	print_R($voter);

	// 	unset($contact['vc_id']);
	// 	$contact['user_name'] = $contact['userName'];
	// 	unset($contact['userName']);

	// 	$contact['rkid'] = $voter['rkid'];

	// 	$rkdb -> insert("voters_contacts", $contact);


		
	// 	// make sure voter comes up as a supporter
	// 	$voter['support_level'] = 1;
	// 	$rkdb -> update("voters", $voter, array("rkid" => $voter['rkid']));


	// }




	exit();







	// LOAD FILE
	$filename = '../data/subscribed.csv';
	$file_str = file_get_contents($filename);	

	$flag = true;
	
	$rows = explode("\n", $file_str);

	// mailchimp hash
	$mailchimp_hash = array(
		"First Name" => "firstname",
		"Email Address" => "email",
		"Last Name" => "lastname",
		"Phone Number" => "phone",
		"Zip Code" => "zip",
		"Source" => "source",
		"Employer" => "employer",
		"Profession" => "profession",
		"Phone2" => "phone2",

	);


// [11] => Street
// [12] => City
// [13] => State
// [14] => Zip
// [15] => Birthday
// [17] => Website
// [20] => Address2
// [19] => Note


// [23] => Want to help?




	
	foreach($rows as $k => $row){
		$fields = $fields = str_getcsv(trim($row));

		print_R($fields);
		exit();


		if($k == 0) {
			$headers = $fields;
			continue;
		}
		
		$row_raw = array_combine($headers, $fields);

		
		$row = array();
		foreach($row_raw as $field => $v){
			$field = trim($field);
			// if(in_array($field, $goodFields) && $v != ''){
			// 	$field = strtolower(str_replace(' ', '', $field));
			// 	$row[$field] = $v;
			// }

			if(isset($mailchimp_hash[$field])) $row[$mailchimp_hash[$field]] = $v;


		}

		if($row_raw['Want to help?'] != ''){
			$mc_str = $row_raw['Want to help?'];
			if(strpos($mc_str, "I'd like to volunteer!") !== false) $row['volunteer'] = 'true';
			if(strpos($mc_str, "Other") !== false) $row['volunteer'] = 'true';
			if(strpos($mc_str, "I'd like a lawn sign!") !== false) $row['wants_sign'] = 'true';
			$row['support_level'] = 1;
		}

		$data[] = $row;
		
	}

	
	

	global $wpdb;
	foreach($data as $voter){

		// make sure there's an email
		if($voter['email'] == ''){
			print_r($voter);
			echo "\nMISSING EMAIL.\n\n\n";
		}		

		$voter['email'] = strtolower($voter['email']);
		$voter['active'] = 1;
		$voter['source'] = 'MailChimp';

		$sql = 'SELECT COUNT(*) FROM voters where email="' . $voter['email'] . '"';
		$count = $wpdb -> get_var($sql);

		if($count == 0){
			echo $voter['email'] . " was not yet in the system.\n";
			$wpdb -> insert("voters", $voter);
		}
		else if($count == 1){
			echo $voter['email'] . " was already in the system.\n";
			$wpdb -> update('voters', $voter, array('email' => $voter['email']));
		}
		else {
			print_r($voter);
			echo "\nIN SYSTEM MULTIPLE TIMES.\n\n\n";
		}
	}




// support_level
// firstname
// lastname
// source
// email
// employer
// profession
// phone
// phoneType "home"
// phone2
// phone2Type "mobile"
// street
// city
// state
// zip
// dob
// website


