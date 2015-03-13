<?php
require_once(dirname(dirname(__DIR__)).'/global.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request = get_json_body();
    if (isset($request['new']) && $request['new']) {
        $query = get_db_session()->prepare("INSERT INTO users (email, password_hash, full_name, permissions) VALUES (?, ?, '', '');");
        $new_password_hash = password_hash($request['password'], PASSWORD_BCRYPT);
        $query->execute(array($request['email'], $new_password_hash));

        $query = get_db_session()->prepare('SELECT LAST_INSERT_ID() AS id;');
        $query->execute();
        $row = $query->fetch();
        $id = $row['id'];
        $_SESSION['user_id'] = $id;
        $_GET['id'] = 'current';
    }
}

user_check_authenticated();

$current_user_info = user_load_current();

if (isset($_GET['id'])) {
    $requested_user_id = $_SESSION['user_id'];
    if ($_GET['id'] != 'current') {
        $requested_user_id = (int) $_GET['id'];
    }

    if ($requested_user_id != $current_user_info['id']) {
        if (!dict_get($current_user_info, 'user_admin', false)) {
            header('HTTP/1.1 403 Access Denied');
            send_json_response(array('message' => "You don not have permissions to access this user's information"));
            exit(0);
        }
    }
    $user_info = user_load($requested_user_id);
    if (!$user_info) {
        header('HTTP/1.1 404 Not found');
        exit(0);
    }


    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Change user information
        if (isset($request['new_password'])) {
            if (user_check_password($user_info['email'], $request['old_password']) !== FALSE) {
                $new_password_hash = password_hash($request['new_password'], PASSWORD_BCRYPT);
                $query = get_db_session()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $query->execute(array($new_password_hash, $user_info['id']));
            } else {
                header('HTTP/1.1 401 Authentication Error');
                send_json_response(array('message' => 'invalid password', 'data'=>$request));
                exit(0);
            }
        }

        table_update_keys('user_profiles', 'user_id', $user_info['id'], array('suite', 'lease_period'), $request, true);
        table_update_keys('users', 'id', $user_info['id'], array('full_name'), $request);

        $user_info = user_load($user_info['id']);
    }

    send_json_response($user_info);
} else {
    if (!dict_get($current_user_info, 'user_admin', false)) {
        header('HTTP/1.1 403 Access Denied');
        send_json_response(array('message' => 'You don not have permissions to view all users'));
        exit(0);
    }
}
