<?php


	Class RK_MySQL {

    // constructor
    function __construct($config){
      extract($config);

			// Create connection
			$this -> conn = new mysqli($servername, $username, $password, $database);
			$this -> debugMode = false;
			$this -> linebreak = "\n\n";

			if ($this -> conn->connect_error) {
				die("Connection failed: " . $this -> conn -> connect_error);
			}
		}


		function run_query($sql){
			$response =  $this -> conn -> query($sql);
			if (!$response) {

				$error_str = 	$this -> conn->error .
								$this -> linebreak . $this -> linebreak . $sql;

				// TBD: log to error table

				// return to user
				echo json_encode(array("error" => $error_str));
				exit;
			}
			return $response;
		}


    // ERROR HANDLING
    //
    // not included yet, probably should be.
    //
    // function outputError(){
		// 	// Print last SQL query string
		// 	echo $this -> wpdb->last_query;
    //
		// 	// Print last SQL query result
		// 	echo $this -> wpdb->last_result;
    //
		// 	// Print last SQL query Error
		// 	echo $this -> wpdb->last_error;
    //
		// }






    // GETTERS

    	function get_var($sql){
			 $result = $this -> run_query($sql);
			 return $result ? $result -> fetch_array()[0] : false;
		}

		function get_row($sql){
			$result = $this -> run_query($sql);
			return ($result) ? $result -> fetch_object() : array();
		}

		function get_rowFromObj($table, $where){
			foreach($where as $k => $v) $whereStrs[] = $k . '="' . addSlashes($v) . '"';
			$sql = 'select * from ' . $table . ' where ' . implode(' AND ', $whereStrs);
			if($this -> debugMode) echo $sql . $this -> _linebreak();


			return $this -> get_row($sql);
		}

		function get_results($sql){
			$result = $this -> run_query($sql);
			if(!$result) return array();

			while($response[] = $result -> fetch_object());
			unset($response[count($response) -1]);
			return $response;
		}



    // SETTERS
		function _countWhereStr($table, $where){

			// where string
			if(count($where) == 0) exit("Missing WHERE.");
			foreach($where as $k => $v) {
				if(!is_numeric($v) || $v == 0) exit("Bad WHERE: $k => $v");
				$whereTerms[] = $k . '=' . (int) $v;
			}
			$whereStr = implode(' AND ', $whereTerms);

			// look for it?
			$sql = "SELECT COUNT(*) FROM $table WHERE " . $whereStr;
			$count = $this -> get_var($sql);
			return $count;
		}

		function update($table, $obj, $where, $max = 1){
			$input = (array) $obj;

			// generate sql
			$sql = 'UPDATE ' . $table;
			foreach($input as $k => $v){
				$params[] = $k . "='" . $this -> escape($v) . "'";
			}
			$sql .= ' SET ' . implode(',', $params);


			// CHECK WHERE STRING AND CONCATENATE
			if($max){
				$count = $this -> _countWhereStr($table, $where);
				if($count  > $max) exit("Tried to update $count rows. Limited to $max.");
			}
			foreach($where as $k => $v){
				$whereStrs[] = $k . "='" . $this -> escape($v) . "'";
			}
			$sql .= ' WHERE ' . implode(' AND ', $whereStrs);


			// run query
			if($this -> debugMode) echo $sql . $this -> _linebreak();
			$this -> run_query($sql);

			// return updated object
			$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode(' AND ', $whereStrs);
			if($this -> debugMode) echo $sql . $this -> _linebreak();
			return $this -> get_row($sql);
		}

		function getUpdateSQL($table, $obj, $where){
			$input = (array) $obj;
			$sql = 'UPDATE ' . $table;
			foreach($input as $k => $v){
				$params[] = $k . "='" . $this -> escape($v) . "'";
			}
			$sql .= ' SET ' . implode(',', $params);
			foreach($where as $k => $v){
				$whereStrs[] = $k . "='" . $this -> escape($v) . "'";
			}
			$sql .= ' WHERE ' . implode(' AND ', $whereStrs);
			return $sql;
		}

		function insert($table, $obj){
			$input = (array) $obj;
			foreach($input as $k => $v){
				$kstrs[] = "`" . $k . "`";
				$vstrs[] = "'" . $this -> escape($v) . "'";
			}
			$sql = 	'INSERT INTO ' . $table .
					' (' . implode(', ', $kstrs) . ') VALUES (' . implode(',', $vstrs) . ')';


			if($this -> debugMode) echo $sql . $this -> _linebreak();


			// run query
			$this -> run_query($sql);

			// return input id
			return mysqli_insert_id($this -> conn);

		}

		function updateOrCreate($table, $update, $where){

			$count = $this -> _countWhereStr($table, $where);

			// if it's not there, add it!
			if($count == 0){
				$newObject = $update;
				$this -> insert($table, $newObject);
			}

			// if it is, update it
			else if($count == 1) {
				$this -> update($table, $update, $where);
			}

			return $this -> get_rowFromObj($table, $where);

		}

		function  getOrCreate($table, $obj){

			// look for it?
			$row = $this -> get_rowFromObj($table, $obj);

			// if it's there, return it!
			if(count($row) != 0) return $row;

			// otherwise, create it and return the new row
			$this -> insert($table, $obj);
			return $obj;

		}


		// delete - maybe we shouldn't even have this?
		// will only work if there's at least one term in the where clause and it's an integer
		function delete($table, $where){
			$sql = "DELETE FROM $table WHERE ";

			if($this -> _countWhereStr($table, $where) != 1) exit("Tried to delete $count rows");

			foreach($where as $k => $v) {
				$where[] = $k . '=' . $v;
			}
			$sql .= implode(' AND ', $where);

			//echo $sql;

			$this -> run_query($sql);
		}



		// UTILITIES
		function escape($str){
			return mysqli_real_escape_string($this -> conn, $str);
		}


  }
