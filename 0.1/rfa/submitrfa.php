<?php
require_once(dirname(dirname(__DIR__)).'/global.php');
$errors         = array();  	// array to hold validation errors
$data 			= array(); 		// array to pass back data
$request = 	get_json_body();
// validate the variables ======================================================
	if (empty($request['rfatype']))
		$errors['rfatype'] = 'A RFA must be selected.';
	if (empty($request['descrip']))
		$errors['descrip'] = 'Description is required.';
	user_check_authenticated();
// return a response ===========================================================
	// response if there are errors
	if ( !empty($errors)) {
		// if there are items in our errors array, return those errors
		$data['success'] = false;
		$data['errors']  = $errors;

		// return all our data to an AJAX call
	} else if($_SERVER['REQUEST_METHOD'] == 'POST') {
		// if there are no errors
		$data['success'] = true;
		$data['message'] = 'Success!';

		//generate current status
		$current_status = 0;
		$username = $_SESSION['user_id'];

		$options = array(
		    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
		); 

		$query = get_db_session()->prepare("INSERT INTO rfa_queue (user_id, time_submited, job_type, current_status, description) VALUES (:user_id, now(), :type, :current_status, :description)");
	    $query->bindParam(':user_id', $username);
		//$query->bindParam(':time', now());
		$query->bindParam(':type', $request['rfatype']);
		$query->bindParam(':current_status', $current_status);
		$query->bindParam(':description', $request['descrip']);
	    //$query->setFetchMode(PDO::FETCH_ASSOC);
	    $query->execute();

        $query = get_db_session()->prepare('INSERT INTO rfa_comments(rfa_id, user_id, time, comment) VALUES (LAST_INSERT_ID(), ?, now(), ?)');
        $query->execute(array($username, $request['comments']));
	}else{
		header('HTTP/1.1 400 Bad Request');
	}
	echo json_encode($data);
