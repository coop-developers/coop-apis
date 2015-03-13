<?php
require_once(dirname(dirname(__DIR__)).'/global.php');

validate_csrf();
$request = get_json_body();
$user_id = user_check_password($request['username'], $request['password']);

if ($user_id !== FALSE) {
    $_SESSION['user_id'] = $user_id;
    header('HTTP/1.1 204 No Content');
} else {
    headeR('HTTP/1.1 401 Wrong auth details');
    send_json_response(array('message'=>'username or password incorrect'));
}
