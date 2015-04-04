<?php
require_once(dirname(dirname(__DIR__)).'/global.php');

if ($_SERVER['REQUEST_METHOD'] != 'PUT') {
    header('HTTP/1.1 405 Invalid Method');
    exit(0);
}
user_check_authenticated();
$current_user = user_load_current();

if (!dict_get($current_user['permissions'], 'rfa_moderator', FALSE)) {
    header('HTTP/1.1 403 Access Denied');
    send_json_response(array('reason'=> 'You are not a moderator, you cannot change status'));
    exit(0);
}

$request = get_json_body();

$query = get_db_session()->prepare('SELECT * FROM rfa_queue WHERE id = ?');
$query->execute(array($request['rfa_id']));
if (!$query->fetch()) {
    header('HTTP/1.1 404 Not Found');
    send_json_response(array('reason'=>'RFA queue entry not found'));
    exit(0);
}

$query = get_db_session()->prepare('UPDATE rfa_queue SET current_status = ? WHERE id = ?');

$query->execute(array($request['new_status'], $request['rfa_id']));
header('HTTP/1.1 204 No Content');
