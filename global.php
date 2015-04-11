<?php
error_reporting(E_ALL); 
ini_set( 'display_errors','1');

session_start();
define("ROOT_PATH", realpath(__DIR__));

$config = array();
call_user_func(function() use (&$config) {
    # XXX: actually read the config from a config file.
    $config['db'] = array();
    $config['db']['dsn'] = 'mysql:host=localhost;dbname=test';
    $config['db']['username'] = 'web';
    $config['db']['password'] = '';
    $config['db']['options'] = array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
    );
});

function get_db_session() {
    global $_default_db_session, $config;
    if (!isset($_default_db_session)) {
        $_default_db_session = new PDO(
            $config['db']['dsn'], $config['db']['username'],
            $config['db']['password'], $config['db']['options']);
    }
    return $_default_db_session;
}

function send_json_response($json_body=null) {
    header('Content-Type: application/json');
    if (!is_null($json_body)) {
        echo(json_encode($json_body));
    }
}

function validate_csrf() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SERVER['HTTP_X_XSRF_TOKEN']) || $_SESSION['csrf_token'] !== $_SERVER['HTTP_X_XSRF_TOKEN']) {
        header('HTTP/1.1 403 Invalid CSRF');
        send_json_response(array('message'=>'Invalid CSRF Token', 'cause'=>'invalid_csrf'));
        exit(2);
    }
}

function get_json_body($body_text=null, $validate_csrf=true) {
    if ($validate_csrf) validate_csrf();
    if (is_null($body_text) && strpos(strtolower($_SERVER['CONTENT_TYPE']), 'json') === false) {
        header('HTTP/1.1 400 Invalid Request Content Type');
        send_json_response(array('message'=>'Invalid request content type'));
        exit(1);
    }
    if (is_null($body_text)) {
        $body_text = file_get_contents('php://input');
    }
    return json_decode($body_text, true);
}

function user_load($user_id) {
    $query = get_db_session()->prepare('SELECT * FROM users LEFT JOIN user_profiles ON
        users.id = user_profiles.user_id WHERE users.id = ?');
    $query->execute(array($user_id));
    $user_info = $query->fetch(PDO::FETCH_ASSOC);
    if (!$user_info) {
        return false;
    }
    user_prepare($user_info);
    return $user_info;
}

function user_prepare(&$user_info) {
    $user_info['permissions'] = json_decode($user_info['permissions'], true);
    unset($user_info['user_id']);
    unset($user_info['password_hash']);
}

function user_check_authenticated() {
    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Authentication Needed');
        exit(0);
    }
}

function user_load_current() {
    if (!isset($_SESSION['user_id'])) {
        return NULL;
    }
    return user_load($_SESSION['user_id']);
}

function user_check_password($email, $password) {
    $query = get_db_session()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $query->execute(array($email));
    $user_record = $query->fetch();
    if (!$user_record) {
        return FALSE;
    }
    if (!password_verify($password, $user_record['password_hash'])) {
        return FALSE;
    }
    return $user_record['id'];
}

function table_update_keys($table, $primary_key, $primary_key_value, $valid_keys, $new_data, $allow_insert=FALSE) {
    $keys = array();
    $placeholders = array();
    $values = array();
    foreach ($new_data as $key=>$value) {
        if (in_array($key, $valid_keys)) {
            $keys[] = "`$key`";
            $placeholders[] = '?';
            $values[] = $value;
        }
    }

    if (count($keys) == 0) return null;

    $update_settings = array();
    foreach ($keys as $key) {
        $update_settings[] = "$key = ?";
    }

    $key_list = implode(', ', $keys);
    $placeholder_list = implode(', ', $placeholders);
    $update_list = implode(', ', $update_settings);
    $values[] = $primary_key_value;
    if (!$allow_insert) {
        $query = "UPDATE `$table` SET $update_list WHERE $primary_key = ?";
    } else {
        $dup_update_settings = array();
        foreach ($keys as $key) {
            $dup_update_settings[] = "$key = VALUES($key)";
        }
        $dup_update_list = implode(', ', $dup_update_settings);
        $query = "INSERT INTO `$table` ($key_list, $primary_key) VALUES ($placeholder_list, ?) ON DUPLICATE KEY UPDATE $dup_update_list";
    }


    $query = get_db_session()->prepare($query);
    $query->execute($values);
    return $query;
}

function dict_get(&$dict, $key, $default=null) {
    if (isset($dict[$key])) {
        return $dict[$key];
    }
    return $default;
}

function format_iso8601($datetime) {
        return date("Y-m-d\TH:i:sO",strtotime($datetime));
}

# Ensure this file does not get served
if (basename($_SERVER['SCRIPT_NAME']) == 'global.php') {
    header('HTTP/1.1 403 Forbidden');
    send_json_response(array('message'=>'Forbidden'));
    exit(1);
}
