<?php
	// the test...

	function handleError($errorMessage){
		echo json_encode(array("error" => $errorMessage));
		exit;
	}


	Class RKVoters_Model {

		function __construct(){
			global $rkdb;
			$this -> db = $rkdb;


			// load request
			$this -> request = $this -> _loadRequest();

			// handle login - need to rewrite this
			$this -> _handleLogin();

		}

		function _loadRequest(){
			// get request (handle both json and typical http post)
			if(count($_POST) == 0){
				try {
						$request = (array) json_decode(file_get_contents('php://input'));
				}
				catch(Exception $error){
					print_r($error);
					echo json_encode(array("error" => "Bad JSON."));
				}
			}
			else {
				$request = $_POST;
			}

			// append URI vars to REQUEST
			foreach($_GET as $k => $v){
				$request[$k] = $v;
			}

			// bounce empty requests
			if(count($request) == 0 && count($_GET) == 0){
				echo json_encode(array("error" => "No request sent."));
				exit;
			}

			return $request;
		}



		// THIS SHOULD BE WRITTEN ASAP
		// OTHERWISE, IT'S A PUBLIC API
		// BUT IT CAN LAUNCH WITHOUT IT
		function _handleLogin(){

			extract($this -> request);

			// is there an access token?
			// if(!isset($access_token) || $access_token == '' || !isset($campaign_slug) || $access_token == ''){
			// 	handleError("Please provide an access token and a campaign slug.");
			// }

			// // look up access token
			// $user = $this -> db -> get_rowFromObj("users", array("access_token" => $access_token));
			// if(count($user) == 0) handleError("Invalid access token.");


			// // what campaign is this access token for?
			// $campaign = $this -> db -> get_rowFromObj("campaigns", array("campaignSlug" => $campaign_slug));
			// if(count($campaign) == 0) handleError("Invalid campaign slug.");


			// $where = array(
			// 	"userId" => $user -> userId,
			// 	"campaignId" => $campaign -> campaignId
			// );
			// $access = $this -> db -> get_rowFromObj("user_access", $where);

			// if(count($access) != 1) handleError("Uh oh. Looks like you don't have access to that campaign");


			// is there a user name?
			if(!isset($user_name) || $user_name == ''){
				handleError("Please provide a user name.");
			}


			// set the campaign id
			//$this -> campaignId = $campaign -> campaignId;

			// set the user
			//$this -> user = $user;

			$this -> user_name = $user_name;
		}

		function getUserById($userId){
			return $this -> db -> get_rowFromObj("users", array("userId" => $userId));
		}




		// get list of people by search criteria
		function get_voterlist(){

			extract((array) $this -> request['listRequest']);

			$limit = ' LIMIT 500';

			$where = array();


			// FIRST NAME
			if($firstname != ''){
				$where[] = "firstname='" . $this -> db -> escape($firstname) . "'";
			}


			// LAST NAME
			if($lastname != ''){
				$where[] = "lastname='" . $this -> db -> escape($lastname) . "'";
			}


			// SUPPORT LEVEL
			if($support_level != 'Support Level'){
				if($support_level == 'X'){
					$where[] = 'support_level<>0';
				}
				else if($support_level == 0){
					$where[] = 	'(support_level=0 or ' .
								'(support_level = 2 and (bio LIKE "%vsc%" or bio = "")))';
				}
				else {
					$where[] = 'support_level=' . (int) $support_level;
				}
			}


			// PARTY
			if($enroll != 'Party'){
				$where[] = "enroll='" . $this -> db -> escape($enroll) . "'";
			}


			// GENDER
			if($sex != 'Gender'){
				$where[] = "sex='" . $this -> db -> escape($sex) . "'";
			}


			// AGE
			if($age_range != 'Age'){

				$year = (int) date("Y");

				switch($age_range){

					case "18-35":
						$max = $year - 34;
						$where[] = "dob > " . $max;
					break;

					case "35-50":
						$min = $year - 34;
						$max = $year - 49;
						$where[] = "(dob < " . $min . " AND dob > " . $max . ")";
					break;

					case "50-65":
						$min = $year - 49;
						$max = $year - 64;
						$where[] = "(dob < " . $min . " AND dob > " . $max . ")";
					break;

					case "65-80":
						$min = $year - 64;
						$max = $year - 79;
						$where[] = "(dob < " . $min . " AND dob > " . $max . ")";
					break;

					case "80+":
						$min = $year - 79;
						$where[] = "dob < " . $min;
					break;

				}
			}



			// CHECKBOXES
			$cb_fields = array("volunteer", "wants_sign", "host_event", "volunteer_other", "active");
			foreach($cb_fields as $f){
				if(isset($$f) && $$f == "true"){
					$where[] = $f . '=1';
				}
			}
			if(isset($has_phone) && $has_phone == "true"){ 
				$where[] = "(phone != '' OR phone2 != '')";
			}
			if(isset($never_called) && $never_called == "true"){ 
				$where[] = "callcount=0";
			}


			// ADDRESS
			$addr_fields = array("stname", "stnum", "city", "zip", "county");
			foreach($addr_fields as $f){
				if($f == "county" && $$f == "County") continue;
				if(isset($$f) && $$f != "") $where[] = $f . "='" . $this -> db -> escape($$f) . "'";
			}




			// BIO SEARCH
			// if(isset($search_str) && $search_str != '') {
			// 	$where[] = "(firstname LIKE '%$search_str%' or lastname LIKE '%$search_str%'
			// 					or bio LIKE '%$search_str%' or phone  LIKE '%$search_str%')";
			// }

			

			if(count($where) == 0) return array();

			$sql = "SELECT * FROM voters
					WHERE " . implode(' and ', $where) .
					" ORDER BY stname, stnum, unit, lastname" . $limit;

			//echo $sql; exit();

			$knocklist = $this -> db -> get_results($sql);

			

			return $knocklist;
		}




		// get person's full record
		function getFullPerson($rkid = false){

			// get rkid
			if(!$rkid && isset($this -> request["rkid"])) {
				$rkid = $this -> request["rkid"];
			}
			if(!$rkid) return array("error" => "No rkid requested");
			$rkid = (int) $rkid;


			// get person from voters table
			$sql = "SELECT * FROM voters WHERE rkid = $rkid";
			$person = (array) $this -> db -> get_row($sql);
			if(count($person) == 0){
				 return array("error" => "No person found for that rkid.");
			}

			// remove slashes from data response (is this necessary? here?)
			foreach($person as $k => $v){
				$person[$k] = stripSlashes($v);
			}


			// get contacts
			// AND voters_contacts.userId = users.userId 
			// once we have authentication in place, we'll be able to fetch info on the volunteer who had the contact.

			$sql = "SELECT * FROM voters_contacts
							WHERE voters_contacts.rkid = $rkid 
							ORDER BY datetime desc";

			// echo $sql; exit();


			$person['contacts'] = $this -> db -> get_results($sql);

			

			// get other people at that number
			$person['neighbors'] = array();
			if($person['phone'] != ''){
				$sql = "SELECT * FROM voters WHERE 
							phone = '" . $this -> db -> escape($person['phone']) . "' 
							and rkid <> " . $this -> db -> escape($person['rkid']) . " 
							and city='" . $this -> db -> escape($person['city']) . "'";

				//echo $sql;
				$sameNumber = $this -> db -> get_results($sql);

				foreach($sameNumber as $contact){
					$contact -> firstname = '(p) ' . $contact -> firstname;
					$person['neighbors'][$contact -> rkid] = $contact;
				}
			}


			// get other people at the same address
			if($person['stname'] != ''){
				$sql = 	"	SELECT * FROM voters WHERE
							stnum = '" . $this -> db -> escape($person['stnum']) . "'
							and stname = '" . $this -> db -> escape($person['stname']) . "'
							and unit = '" . $this -> db -> escape($person['unit']) . "'
							and rkid <> " . $this -> db -> escape($person['rkid']) . "
							and city='" . $this -> db -> escape($person['city']) . "'";
				//echo $sql;

				$housemates = $this -> db -> get_results($sql);
				foreach($housemates as $contact){
					$contact -> bio = stripSlashes($contact -> bio);
					$contact -> firstname = '(a) ' . $contact -> firstname;
					if(!isset($person['neighbors'][$contact -> rkid])){
						$person['neighbors'][$contact -> rkid] = $contact;
					}
				}
			}

			if(count($person['neighbors']) == 0) $person['neighbors'] = false;

			return $person;
		}


		// create person
		function addPerson(){
			extract($this -> request);
			if(count($person) == 0){
				return array("error" => "No person submitted.");
			}
			$response = array(
					"rkid" => $this -> db -> insert('voters', $person)
			);

			if(isset($type)){
				$response['knocklist'] = $this -> get_knocklist();
			}
			return $response;
		}

		// update person
		function updatePerson(){
			extract($this -> request);
			$where = array('rkid' => $rkid);
			$update = (array) $person;

			unset($update['address']);
			unset($update['age']);
			unset($update['contacts']);
			unset($update['residentLabel']);
			unset($update['neighbors']);
			unset($update['$$hashKey']);

			$cb_fields = array("volunteer", "wants_sign", "host_event", "volunteer_other");
			foreach($cb_fields as $f){
				$update[$f] = (isset($update[$f]) && $update[$f] == "true") ? 1 : 0;
			}


			$this -> db -> update('voters', $update, $where);


			/* TEST BLOCK
				// Print last SQL query string
				echo $this -> db->last_query;
				echo $this -> db->last_result;
				echo $this -> db->last_error;
				exit;
			*/


			return $this -> getFullPerson($person -> rkid);
		}


		// add contact
		function recordContact(){


			extract($this -> request);
			$contact = (array) $contact;
			$person = (array) $person;

			if(!isset($contact['datetime']) || $contact['datetime'] == ''){
				$contact['datetime'] = date("Y-m-d H:i:s");
			}
			else {
				$datetime = strtotime($contact['datetime']);
				$contact['datetime'] = date("Y-m-d H:i:s", $datetime);
			}

			//$contact['userId'] = $this -> user -> userId;
			$contact['user_name'] = $this -> user_name;


			// if you made a phone call, record it for others with the same number
			if($contact['type'] == 'Phone Call' && $person['phone'] != '') {

				$sql = "SELECT * FROM voters WHERE 
								phone = '" . $this -> db -> escape($person['phone']) . "' 
								and city='" . $this -> db -> escape($person['city']) . "'";

				$sameNumber = $this -> db -> get_results($sql);
				//$this -> request['person']['callcount']++;

				foreach($sameNumber as $target){
					$contact['rkid'] = $target -> rkid;
					$response = $this -> db -> insert('voters_contacts', $contact);
				}
			}

			// if not, just record the contact for the person targetted
			else {
				//$contact['support_level'];
				//print_r($contact);
				$response = $this -> db -> insert('voters_contacts', $contact);
			}

			$this -> updatePerson();




			return $this -> getFullPerson($person['rkid']);
		}

		// delete contact
		function deleteContact(){
			extract($this -> request);
			$where = array('vc_id' => $vc_id);
			$this -> db -> delete( 'voters_contacts', $where);
			return $this -> getFullPerson($rkid);
		}


		// remove person
		function removePerson(){
			extract($this -> request);
			$where = array('rkid' => $rkid);
			$this -> db -> delete('voters', $where);
			return array(
				"status" => "deleted"
			);
		}


		// merge people
		function merge_people(){
			extract($this -> request);

			// transfer contact's contacts to voter
			$contact_rkid = (int) $contact -> rkid;
			$voter_rkid = (int) $voter -> rkid;
			$sql = 'UPDATE voters_contacts set rkid=' . $voter_rkid . ' where rkid=' . $contact_rkid;
			$this -> db -> run_query($sql);


			// transfer contact's data onto voter
			$fields = array("support_level", "bio","volunteer","wants_sign","host_event","volunteer_other",
							"firstname","lastname","email","email_opt_in","phone","phoneType","phone2","phone2Type","profession","employer","website");
			foreach($fields as $f){
				$update[$f] = $contact -> $f;
			}

			// fix checkbox fields
			$cb_fields = array("volunteer", "wants_sign", "host_event", "volunteer_other");
			foreach($cb_fields as $f){
				$update[$f] = (isset($update[$f]) && $update[$f] == "true") ? 1 : 0;
			}


			$update['active'] = 1;
			$where = array("rkid" => $voter_rkid);
			$this -> db -> update("voters", $update, $where);


			// remove contact entry
			$this -> db -> delete('voters', array("rkid" => $contact_rkid));


			// return updated voter list
			return $this -> get_voterlist();

		}


		// drop lit bomb
		function litBomb(){
			extract($this -> request);

			if($date != ''){
				$datetime = strtotime($date);
				$datestr = date("Y-m-d H:i:s", $datetime);
			}
			else {
				$datestr = date("Y-m-d H:i:s");
			}

			foreach($rkids as $vid){
				$contact['datetime'] = $datestr;
				$contact['rkid'] = $vid;
				$contact['type'] = 'Lit Drop';
				$this -> db -> insert('voters_contacts', $contact);
			}
			return $this -> get_knocklist();
		}


		// get contacts with emails
		function getContactsWithEmails(){
			$sql = 'select firstname, lastname, stname1, city, state, zip, yob, email, phone, bio
					from voters where email <> ""';
			$list = $this -> db -> get_results($sql, 'ARRAY_A');
			return $list;

		}


		// get donations
		function getDonations(){
			$sql = 'SELECT v.firstname, v.lastname, v.rkid, v.id, vc.amount, v.city, vc.datetime
					FROM voters v
					INNER JOIN voters_contacts vc ON v.rkid = vc.rkid
					WHERE vc.type = "Donation"
					ORDER BY vc.datetime';

			$results = $this -> db -> get_results($sql);
			$donors = [];
			$prev_id = 0;

			$donSets[0]['title'] = 'Non-Local';
			$donSets[1]['title'] = 'Local';
			$donSets[2]['title'] = 'Small';

			foreach($results as $r){

				if($r -> amount <= 50){
					$setIndex = 2;
				}
				else {
					$setIndex = (strtoupper($r -> city) == 'PORTLAND') ? 1 : 0;
					foreach($r as $k => $v){
						if($k == 'firstname' || $k == 'lastname'){
							$r -> $k = stripSlashes(strtoupper($v));
						}
					}
				}

				$datetime = strtotime($r -> datetime);
				$r -> datetime = date("M j", $datetime);

				$donSets[$setIndex]['donors'][] = $r;
				$donSets[$setIndex]['total'] += $r -> amount;
			}

			return $donSets;
		}


		// export donations
		function exportDonations(){
			$sql = 'SELECT v.*,
					vc.amount, v.city, vc.datetime
					FROM voters v
					INNER JOIN voters_contacts vc ON v.rkid = vc.rkid
					WHERE vc.type = "Donation"
					ORDER BY vc.datetime';

			$results = $this -> db -> get_results($sql);
			$response = array();

			foreach($results as $r){

				foreach($r as $k => $v){
					$r -> $k = stripSlashes(strtoupper($v));
				}

				$unit = ($r -> unit != '') ? ' ' . $r -> unit : '';
				$addr1 = $r -> stnum . ' ' . $r -> stname1 . $unit;
				if($addr1 == 0) $addr1 = '';

				$type = ($r -> firstname == 'ROBERT' && $r -> lastname == 'KOROBKIN') ? 1 : 2;

				$total += $r -> amount;

				if($r -> amount <= 50) {
					$cash += $r -> amount;
					continue;
				}

				$donor = array(
					'Date' => date("M j", strtotime($r -> datetime)),
					'First' => $r -> firstname,
					'Last' => $r -> lastname,
					'Street' => $addr1,
					'City' => $r -> city,
					'State' => $r -> state,
					'Zip' => $r -> zip,
					'Profession' => $r -> profession,
					'Employer' => $r -> employer,
					'Amount' => $r -> amount,
					'Type' => $type
				);

				$response[] = $donor;

			}

			$response[] = array(
				'Date' => '',
				'First' => 'DONATIONS UNDER $50',
				'Last' => '',
				'Street' => '',
				'City' => '',
				'State' => '',
				'Zip' => '',
				'Profession' => '',
				'Employer' => '',
				'Amount' => $cash,
				'Type' => 8
			);


			$response[] = array(
				'Date' => '',
				'First' => 'TOTAL DONATIONS',
				'Last' => '',
				'Street' => '',
				'City' => '',
				'State' => '',
				'Zip' => '',
				'Profession' => '',
				'Employer' => '',
				'Amount' => $total,
				'Type' => ''
			);

			return $response;
		}

		// UTILITIES
		function generateCsv($data){
			$csv = '';
			$keys = array_keys($data[0]);
			$csv .= implode(',', $keys) . "\n";
			foreach($data as $row){
				$csv .= implode(',', $row) . "\n";
			}
			return $csv;
		}



	}
