<?php
require_once(dirname(dirname(__DIR__)).'/global.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('HTTP/1.1 405 Invalid Method');
    exit(0);
}
user_check_authenticated();
$request = get_json_body();

$query = get_db_session()->prepare('SELECT * FROM rfa_queue WHERE id = ?');
$query->execute(array($request['rfa_id']));
if (!$query->fetch()) {
    header('HTTP/1.1 404 Not Found');
    send_json_response(array('reason'=>'RFA queue entry not found'));
    exit(0);
}
$query = get_db_session()->prepare('INSERT INTO rfa_comments (rfa_id, user_id, time, comment) VALUES (?, ?, now(), ?)');

$query->execute(array($request['rfa_id'], $_SESSION['user_id'], $request['comment']));
header('HTTP/1.1 204 No Content');
