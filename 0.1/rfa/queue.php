<?php
require_once(dirname(dirname(__DIR__)).'/global.php');

function prepare_rfa_entry(&$rfa_entry) {
    $rfa_entry['time_submited'] = format_iso8601($rfa_entry['time_submited']);
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!isset($_GET['id'])) {
        $filt_columns = array();
        $filt_data = array();
        $allowable_filters = array('job_type', 'current_status', 'user_id');

        foreach ($_GET as $key => $value) {
            if (in_array($key, $allowable_filters)) {
                $filt_columns[] = "`$key` = ?";
                $filt_data[] = $value;
            }
        }

        $filter = implode(' AND ', $filt_columns);
        if (!$filter) $filter = '1';

        $query = get_db_session()->prepare("SELECT rfa_queue.*, users.full_name FROM rfa_queue INNER JOIN users ON rfa_queue.user_id = users.id WHERE ($filter) ");
        $query->setFetchMode(PDO::FETCH_ASSOC);
        $query->execute($filt_data);
        $rfa_entries = $query->fetchAll();
        foreach ($rfa_entries as &$rfa_entry) {
            prepare_rfa_entry($rfa_entry);
        }
        send_json_response($rfa_entries);
    } else {
        $requested_queue_id = $_GET['id'];
        $query = get_db_session()->prepare('SELECT rfa_queue.*, users.full_name FROM rfa_queue INNER JOIN users ON rfa_queue.user_id = users.id WHERE rfa_queue.id = ?');
        $query->setFetchMode(PDO::FETCH_ASSOC);
        $query->execute(array($requested_queue_id));

        $queue_item = $query->fetch();
        if (!$queue_item) {
            header('HTTP/1.1 404 Not Found');
            send_json_response(array('reason'=>'Item not found'));
            exit(0);
        }
        prepare_rfa_entry($queue_item);

        $query = get_db_session()->prepare('SELECT rfa_comments.*, users.full_name FROM rfa_comments INNER JOIN users ON rfa_comments.user_id = users.id WHERE rfa_comments.rfa_id = ? ORDER BY rfa_comments.time DESC');
        $query->setFetchMode(PDO::FETCH_ASSOC);
        $query->execute(array($queue_item['id']));

        $queue_item['comments'] = $query->fetchAll();
        foreach ($queue_item['comments'] as &$comment) {
            $comment['time'] = format_iso8601($comment['time']);
        }
        send_json_response($queue_item);
    }
} else {
    header('HTTP/1.1 405 Invalid Method');
}
