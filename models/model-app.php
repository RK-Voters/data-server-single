<?php

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

			// handle login
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

		function _handleLogin(){

			extract($this -> request);

			// is there an access token?
			if(!isset($access_token) || $access_token == '' || !isset($campaign_slug) || $access_token == ''){
				handleError("Please provide an access token and a campaign slug.");
			}

			// look up access token
			$user = $this -> db -> get_rowFromObj("users", array("access_token" => $access_token));
			if(count($user) == 0) handleError("Invalid access token.");


			// what campaign is this access token for?
			$campaign = $this -> db -> get_rowFromObj("campaigns", array("campaignSlug" => $campaign_slug));
			if(count($campaign) == 0) handleError("Invalid campaign slug.");


			$where = array(
				"userId" => $user -> userId,
				"campaignId" => $campaign -> campaignId
			);
			$access = $this -> db -> get_rowFromObj("user_access", $where);

			if(count($access) != 1) handleError("Uh oh. Looks like you don't have access to that campaign");


			// set the campaign id
			$this -> campaignId = $campaign -> campaignId;

			// set the user
			$this -> user = $user;
		}

		function getUserById($userId){
			return $this -> db -> get_rowFromObj("users", array("userId" => $userId));
		}

		/****************************************************************
		*	STREETS & TURFS
		*	- getStreetList()
		* - getTurfList()
		* - updateTurfAssignment()
		* - updateTotals()
		****************************************************************/

		function getStreetAndTurfLists(){
			$sql = 	"SELECT * from voters_turfs " .
					"WHERE campaignId = " . (int) $this -> campaignId .
					" ORDER BY turf_name";

			$turfsRaw = $this -> db -> get_results($sql);


			$sql = "SELECT * from voters_streets
					WHERE campaignId = " . (int) $this -> campaignId . "
					ORDER BY city, street_name";


			$streetsRaw = $this -> db -> get_results($sql);

			
			return array(
				"turfs" => $turfsRaw,
				"streets" => $streetsRaw
			);
		}

		function createTurf(){
			extract($this -> request);
			if(!isset($terms -> turf_name)) handleError("Please provide a turf.");
			$turf = array(
				"turf_name" => $terms -> turf_name,
				"campaignId" => $this -> campaignId
			);
			$turfid = $this -> db -> insert('voters_turfs', $turf);
			return array( "turfid" => $turfid);
		}

		// get list of available turfs
		function getTurfList(){
		}

		// delete turf
		function deleteTurf(){
			extract($this -> request);
			if(!isset($terms ->turfid) || $terms -> turfid == 0) handleError("Please provide a turf id.");

			$this -> db -> delete("voters_turfs", array("turfId" => $terms -> turfid));

			$this -> db -> update("voters_streets", array("turfid" => 0), array("turfid" => $terms -> turfid), false);

			return $this -> getStreetAndTurfLists();
		}

		// update turf assignment for street
		function updateTurfAssignment($oldTurfId = false){
			extract($this -> request);

			
			$newTurfid = ($oldTurfId) ? 0 : $terms -> turfid;
			$streetid = $terms -> streetid;

			// assign street to turf
			$update = array('turfid' => $newTurfid);
			$where = array('streetid' => $streetid);
			$this -> db -> update('voters_streets', $update, $where);


			$turfid = ($oldTurfId) ? $oldTurfId : $terms -> turfid;


			// update that turf and return the revised one
			$turf_totals = array(
				'active_voters' => $this -> db -> get_var("SELECT SUM(active_voters) FROM voters_streets s where s.turfid = " . $turfid),
				'contacts' => $this -> db -> get_var("SELECT SUM(contacts) FROM voters_streets s where s.turfid = " . $turfid),
				'supporters' => $this -> db -> get_var("SELECT SUM(supporters) FROM voters_streets s where s.turfid = " . $turfid)
			);

			$this -> db -> update('voters_turfs', $turf_totals, array('turfid' => $turfid));

			return $this -> db -> get_rowFromObj("voters_turfs", array("turfid" => $turfid));
		}


		function removeStreetFromTurf(){
			return $this -> updateTurfAssignment($this -> request['terms'] -> turfid);
		}



		// update totals
		function updateTotals(){

			extract($this -> getStreetAndTurfLists());


			/* STREETS */
			foreach($streets as $street){

				$sql = 	"SELECT COUNT(*) as total FROM voters " .
								"where streetId='" . (int) $street -> streetid . "'
						 		 and active=1";


				$contact_sql = "SELECT COUNT(*) FROM VOTERS v
								WHERE streetId=" . (int) $street -> streetid . " AND
								EXISTS (SELECT * FROM VOTERS_CONTACTS c WHERE v.rkid = c.rkid and c.status = 'canvassed') ";

				$street_totals = array(
					'active_voters' => $this -> db -> get_var($sql),
					'contacts' => $this -> db -> get_var($contact_sql),
					'supporters' => $this -> db -> get_var($sql . ' and (support_level = 1 or support_level=2)')
				);

				$this -> db -> update('voters_streets', $street_totals,
										array('streetid' => (int) $street -> streetid));

			}

			/* TURFS */
			foreach($turfs as $turf){

				$turf_totals = array(
					'active_voters' => $this -> db -> get_var("SELECT SUM(active_voters) FROM voters_streets s where s.turfid = " . $turf -> turfid),
					'contacts' => $this -> db -> get_var("SELECT SUM(contacts) FROM voters_streets s where s.turfid = " . $turf -> turfid),
					'supporters' => $this -> db -> get_var("SELECT SUM(supporters) FROM voters_streets s where s.turfid = " . $turf -> turfid)
				);

				$this -> db -> update('voters_turfs', $turf_totals, array('turfid' => $turf -> turfid));

			}

			return $this -> getStreetAndTurfLists();
		}






		// get list of people by search criteria
		function get_knocklist(){

			extract((array) $this -> request['listRequest']);

			$limit = ' LIMIT 500';

			$where = array();

			if(isset($support_level) && $support_level != '-'){
				if($support_level == 0){
					$where[] = 	'(support_level=0 or ' .
								'(support_level = 2 and (bio LIKE "%vsc%" or bio = "")))';
				}
				else {
					$where[] = 'support_level=' . $support_level;
				}
			}

			if(isset($party) && $party != '-'){
				$where[] = 'enroll="' . $party . '"';
			}


			if($street_name != 'Select Street...') $where[] = "stname = '$street_name'";
			if(isset($stnum)) $where[] = "stnum = '$stnum'";

			if(isset($search_str) && $search_str != '') {
				$where[] = "(firstname LIKE '%$search_str%' or lastname LIKE '%$search_str%'
								or bio LIKE '%$search_str%' or phone  LIKE '%$search_str%')";
			}



			switch($type) {
				// case 'All' : break;

				case 'Active' :
					$where[] = "active=1";
				break;

				case 'Volunteers' :
					$where[] = "volunteer='true'";
				break;

				case 'Donors' :
					$where[] = "EXISTS(SELECT * from voters_contacts where
									voters.rkid=voters_contacts.rkid
									and voters_contacts.type='Donation')";
				break;

				case 'Phones' :
					$where[] = 'phone <> ""';
					$limit = '';
				break;

				case 'Phones - Open' :
					$where[] = 'phone <> ""';
					$where[] = 'd = 0';
					$limit = '';
				break;


				case 'Phones - Not Called' :
					$where[] = 'phone <> ""';
					$where[] = 'not exists (select * from voters_contacts vc where
									vc.rkid = voters.rkid and vc.type="Phone Call")';
					$limit = '';
				break;

				case 'Phones (Not Anchor) - Not Called' :
					$where[] = 'phoneType = ""';
					$where[] = 'phone <> ""';
					$where[] = 'not exists (select * from voters_contacts vc where
									vc.rkid = voters.rkid and vc.type="Phone Call")';
					$limit = '';
				break;

				case 'Phones - Called' :
					$where[] = 'phone <> ""';
					$where[] = ' exists (select * from voters_contacts vc where
									vc.rkid = voters.rkid and vc.type="Phone Call")';
					$limit = '';
				break;

				case 'Need Postcards' :
					$where[] = 'stnum != 0';
					$where[] = '(support_level=1 or support_level=2)';
					$where[] = 'bio NOT LIKE "%vsc%" and bio <> ""';
					$where[] = 'not exists (select * from voters_contacts vc where
									vc.rkid = voters.rkid and vc.type="Sent Post Card")';
				break;

				case 'Sent Postcards' :
					$limit = '';
					$where[] = ' exists (select * from voters_contacts vc where
									vc.rkid = voters.rkid and vc.type="Sent Post Card")';
				break;

				case 'Seniors - Phones - Not Called' :
					$limit = '';
					$where[] = 'not exists (select * from voters_contacts vc where
									vc.rkid = voters.rkid and vc.type="Phone Call")';
					$where[] = 'phone <> ""';
					$where[] = 'yob < 1950';
					$where[] = 'yob <> 0';
					$where[] = 'phoneType <> "D1"';
				break;

				case 'Seniors - Phones' :
					$limit = '';
					$where[] = 'phone <> ""';
					$where[] = 'yob < 1950';
					$where[] = 'yob <> 0';
				break;

				case 'Active Under 35' :
					$limit = '';
					$where[] = 'dob > 1982';
					$where[] = 'active=1';
					$where[] = 'votedin2011=1';
					$where[] = 'votedin2013=1';
				break;

				case 'Active Under 35 - with phones' :
					$limit = '';
					$where[] = 'yob > 1980';
					$where[] = 'active=1';
					$where[] = 'votedin2011=1';
					$where[] = 'votedin2013=1';
					$where[] = 'phone<>""';
				break;

				case 'West End - Super - No Contact' :
					$limit = '';
					$where[] = 'votedin2011=1';
					$where[] = 'votedin2013=1';
					$where[] = 'phone=""';
					$where[] = 'support_level=0';

				break;

				case 'Parkside - Phones' :
					$limit = '';
					$where[] = 'phone<>""';
				break;

			}

			if(count($where) == 0) return array();

			$sql = "SELECT * FROM voters
					WHERE " . implode(' and ', $where) .
					" ORDER BY stname, stnum, unit, lastname" . $limit;

			$knocklist = $this -> db -> get_results($sql);
			foreach($knocklist as $index => $person){
				foreach($person as $field => $value){
					$knocklist[$index] -> $field = stripSlashes($value);
				}
			}

			// LIMIT TO WEST END
			if($type == 'West End - Super - No Contact'){
				$sql = 'select street_name from voters_streets where turfid=5 or turfid=6';
				$streets = $this -> db -> get_results($sql);
				foreach($streets as $street){
					$streetHash[$street -> street_name] = true;
				}
				foreach($knocklist as $k => $person){
					if($streetHash[$person -> stname1]){
						$response[] = $person;
					}
				}
				return $response;
			}

			// LIMIT TO PARKSIDE
			if($type == 'Parkside - Phones' ){
				$sql = 'select street_name from voters_streets where turfid=3';
				$streets = $this -> db -> get_results($sql);
				foreach($streets as $street){
					$streetHash[$street -> street_name] = true;
				}
				foreach($knocklist as $k => $person){
					if($streetHash[$person -> stname1]){
						$response[] = $person;
					}
				}
				return $response;
			}

			return $knocklist;
		}


		// get call list
		function get_calllist(){
			$this -> request['listRequest']['hasPhone'] = true;

			$list = $this -> get_knocklist();

			return $list;
		}


		// send post cards
		function send_postcards(){

			// get everybody who has a postcard that needs to be sent
			$sql = 'select * from voters v where
					exists (select * from voters_contacts vc where vc.rkid = v.rkid and vc.type="Post Card")
					and
					not exists (select * from voters_contacts vc where vc.rkid = v.rkid and vc.type="Sent Post Card")';

			$recipients = $this -> db -> get_results($sql);

			foreach($recipients as $recipient){
				$contact = array(
					'datetime' => date("Y-m-d H:i:s"),
					'type' => 'Sent Post Card',
					'rkid' => $recipient -> rkid
				);

				$response = $this -> db -> insert('voters_contacts', $contact);
			}

			return array(
				"knocklist" => $this -> get_knocklist()
			);
		}


		// get local supporter email list
		function getLocalSupporterEmails(){
			$sql = 'SELECT email from voters where support_level=1 and (city="Portland" or city="") and email <> ""';
			$list = $this -> db -> get_results($sql);
			$response = '';
			foreach($list as $person){
				$list .= $person -> email . ', ';
			}
			return $list;
		}


		// get mailing list
		function getMailingList(){
			$sql = 'SELECT * FROM `voters`
					WHERE ( support_level=0 OR support_level=2 )
					AND votedin2013=1 ORDER BY stname1,stnum,unit';
			$list = $this -> db -> get_results($sql);

			$oldAddress = false;
			$index = -1;


			/* -- KEEPS NAMES, BUNDLES BY ADDRESS
			foreach($list as $address){

				// address
				$unit = ($address -> unit != '') ? ' ' . $address -> unit : '';
				$address -> address = 	$address -> stnum . ' ' . $address -> stname1 . $unit . ', ' .
										$address -> city . ', ' . $address -> state . ' ' . $address -> zip;
				$address -> name = strtoupper($address -> firstname . ' ' . $address -> lastname);

				// check for match
				if(	$oldAddress &&
					$address -> address == $oldAddress -> address &&
					$address -> stname1 != "CONGRESS ST" &&
					$address -> stname1 != "STATE ST"){
					//  &&  $address -> lastname == $oldAddress -> lastname){
						$response[$index]['name'] .= ' and ' . $address -> name;
				}
				else {
					$index++;
					$response[$index] = array(
						'name' => $address -> name,
						'address' => $address -> address,
						'addr1' => $address -> stnum . ' ' . $address -> stname1 . $unit,
						'addr2' => $address -> city . ', ' . $address -> state . ', ' . $address -> zip
					);

				}
				$oldAddress = $address;
			}
			*/

			// de-dupe by address
			foreach($list as $address){
				$unit = ($address -> unit != '') ? ' ' . $address -> unit : '';
				$addr1 = $address -> stnum . ' ' . $address -> stname1 . $unit;
				$response[$addr1] = array(
					'addr1' => $addr1,
					'addr2' => $address -> city . ', ' . $address -> state . ', ' . $address -> zip
				);
			}


			return $response;
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
			$sql = "SELECT * FROM voters_contacts, users
							WHERE voters_contacts.rkid = $rkid AND voters_contacts.userId = users.userId
							ORDER BY datetime desc";
			$contactsRaw = $this -> db -> get_results($sql);

			// process contacts
			foreach($contactsRaw as $contact){
				$contact -> note = stripSlashes($contact -> note);
				$person['contacts'][] = $contact;
			}

			// get other people at that number
			$person['neighbors'] = array();
			if($person['phone'] != ''){
				$sql = "SELECT * FROM voters WHERE phone = '" . $person['phone'] . "' and rkid <> " . $person['rkid'];
				//echo $sql;
				$sameNumber = $this -> db -> get_results($sql);
				foreach($sameNumber as $contact){
					$contact -> bio = stripSlashes($contact -> bio);
					$contact -> firstname = '(p) ' . $contact -> firstname;
					$person['neighbors'][$contact -> id] = $contact;
				}
			}


			// get other people at the same address
			if($person['stname'] != ''){
				$sql = 	"	SELECT * FROM voters WHERE
									stnum = '{$person['stnum']}'
									and stname = '{$person['stname']}'
									and unit = '{$person['unit']}'
									and rkid <> {$person['rkid']}";
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

			$contact['userId'] = $this -> user -> userId;


			// if you made a phone call, record it for others with the same number
			if($contact['type'] == 'Phone Call' && $person['phone'] != '') {

				$sql = "SELECT * FROM voters WHERE phone = '" . $person['phone'] . "'";
				$sameNumber = $this -> db -> get_results($sql);
				//$this -> request['person']['callcount']++;

				foreach($sameNumber as $target){
					$contact['rkid'] = $target -> rkid;
					$response = $this -> db -> insert('voters_contacts', $contact);
				}
			}

			// if not, just record the contact for the person targetted
			else {
				$contact['support_level'];
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
				"status" => "deleted",
				"knocklist" => $this -> get_knocklist()
			);
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
