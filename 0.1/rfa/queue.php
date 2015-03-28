<?php
require_once(dirname(dirname(__DIR__)).'/global.php');

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $filt_columns = array();
    $filt_data = array();
    $allowable_filters = array('job_type', 'current_status');

    foreach ($_GET as $key => $value) {
        if (in_array($key, $allowable_filters)) {
            $filt_columns[] = "`$key` = ?";
            $filt_data[] = $value;
        }
    }

    $filter = implode(' AND ', $filt_columns);
    if (!$filter) $filter = '1';

    $query = get_db_session()->prepare("SELECT * FROM rfa_queue WHERE ($filter)");
    $query->setFetchMode(PDO::FETCH_ASSOC);
    $query->execute($filt_data);
    send_json_response($query->fetchAll());
} else {
    header('HTTP/1.1 405 Invalid Method');
}
