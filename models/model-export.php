<?php


// handle csv export request
if(isset($request['export'])){
	header("Content-Type: text/csv");
	header("Content-Disposition: attachment; filename=\"Contacts.csv\"");

	// export emails
	if($request['export'] == 'emails'){
		$contacts = $data_model -> getContactsWithEmails();
		echo $data_model -> generate_csv($contacts);
		exit;
	}

	// export mailing list
	if($request['export'] == 'mailinglist'){
		$contacts = $data_model -> getMailingList();

		echo "Name; Address 1; Address 2 \n";
		foreach($contacts as $k => $contact){
			echo "Everybody at; " . $contact['addr1'] . '; ' . $contact['addr2'] . "\n";
		}
		exit;
	}


	// export donors
	if($request['export'] == 'donors'){
		$contacts = $data_model -> exportDonations();
		echo $data_model -> generate_csv($contacts);
		exit;
	}

}





	if(isset($_GET['export']) && $isAdmin){
		header("Content-Type: text/csv");
		header("Content-Disposition: attachment; filename=\"Contacts.csv\"");


		// export emails
		if($_GET['export'] == 'emails'){
			$contacts = $data_client -> getContactsWithEmails();

			// headers
			$keys = array_keys($contacts[0]);
			echo implode(',', $keys) . "\n";

			foreach($contacts as $contact){
				echo implode(',', $contact) . "\n";
			}

			exit;
		}

		// export mailing list
		if($_GET['export'] == 'mailinglist'){
			$contacts = $data_client -> getMailingList();

			echo "Name; Address 1; Address 2 \n";
			foreach($contacts as $k => $contact){
				echo "Everybody at; " . $contact['addr1'] . '; ' . $contact['addr2'] . "\n";
			}
			exit;
		}


		// export donors
		if($_GET['export'] == 'donors'){
			$contacts = $data_client -> exportDonations();

			// headers
			$keys = array_keys($contacts[0]);
			echo implode(',', $keys) . "\n";

			foreach($contacts as $contact){
				echo implode(',', $contact) . "\n";
			}

			exit;
		}

	}

}
