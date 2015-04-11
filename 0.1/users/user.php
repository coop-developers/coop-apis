<?php
require_once(dirname(dirname(__DIR__)).'/global.php');

function _create_stub_user($email, $password) {
    $query = get_db_session()->prepare("INSERT INTO users (email, password_hash, full_name, permissions) VALUES (?, ?, '', '');");
    $new_password_hash = password_hash($password, PASSWORD_BCRYPT);
    $query->execute(array($email, $new_password_hash));

    $query = get_db_session()->prepare('SELECT LAST_INSERT_ID() AS id;');
    $query->execute();
    $row = $query->fetch();
    $id = $row['id'];
    return $id;
}

function _update_user_password($user_info, $old_password, $new_password) {
    if (user_check_password($user_info['email'], $old_password) !== FALSE) {
        $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $query = get_db_session()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $query->execute(array($new_password_hash, $user_info['id']));
    } else {
        header('HTTP/1.1 401 Authentication Error');
        send_json_response(array('message' => 'invalid password'));
        exit(0);
    }
}
function _update_user($user_id, $request) {
    table_update_keys('user_profiles', 'user_id', $user_id, array('suite', 'lease_period'), $request, true);
    table_update_keys('users', 'id', $user_id, array('full_name'), $request);
}

function _update_user_permissions($user_id, $new_perms) {
    $new_perms_data = json_encode($new_perms);
    if (is_null($new_perms)) {
        $new_perms_data = '';
    }
    $query = get_db_session()->prepare('UPDATE users SET permissions = ? WHERE id = ?');
    $query->execute(array($new_perms_data, $user_id));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_SESSION['user_id'])) {
        header('HTTP/1.1 400 Invalid Request');
        send_json_response(array('message' => "You cannot create new accounts while logged in"));
        exit(0);
    }
    $request = get_json_body();
    $new_user_id = _create_stub_user($request['email'], $request['password']);
    $_SESSION['user_id'] = $new_user_id;
    user_check_authenticated();
    _update_user($new_user_id, $request);

    send_json_response(user_load($new_user_id));
} else if ($_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'GET') {
    user_check_authenticated();

    $current_user_info = user_load_current();
    if (!isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] == 'PUT') {
        header('HTTP/1.1 400 Invalid Request (must provide id)');
        send_json_response(array('message' => 'You must provide a user id when modifying users'));
        exit(0);
    }

    if (isset($_GET['id'])) {
        $user_id = $_SESSION['user_id'];
        if ($_GET['id'] != 'current') {
            $user_id = (int) $_GET['id'];
        }

        if ($user_id != $current_user_info['id']) {
            if (!dict_get($current_user_info['permissions'], 'user_admin', false)) {
                header('HTTP/1.1 403 Access Denied');
                send_json_response(array('message' => 'You don not have permissions to view other users'));
                exit(0);
            }
        }

        if (!user_load($user_id)) {
            header('HTTP/1.1 404 Not Found');
            send_json_response(array('message' => 'User not found'));
            exit(0);
        }

        if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
            $request = get_json_body();
            if (!dict_get($current_user_info['permissions'], 'user_admin', false) &&
                    isset($request['permissions'])) {
                unset($request['permissions']);
            }


            if (isset($request['new_password'])) {
                if ($user_id == $current_user_info['id']) {
                    _update_user_password($current_user_info, $request['old_password'],
                        $request['new_password']);
                }
            } else {
                _update_user($user_id, $request);
                if (isset($request['permissions'])) {
                    _update_user_permissions($user_id, $request['permissions']);
                }
            }
        }

        send_json_response(user_load($user_id));
    } else {
        if (!dict_get($current_user_info['permissions'], 'user_admin', false)) {
            header('HTTP/1.1 403 Access Denied');
            send_json_response(array('message' => 'You don not have permissions to view all users'));
            exit(0);
        }

        $users = get_db_session()->prepare('SELECT * FROM users LEFT JOIN user_profiles ON users.id = user_profiles.user_id');
        $users->setFetchMode(PDO::FETCH_ASSOC);
        $users->execute();

        $results = array();
        foreach ($users as $user) {
            user_prepare($user);
            $results[] = $user;
        }
        send_json_response($results);
    }
} else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    user_check_authenticated();

    $current_user_info = user_load_current();
    if (!isset($_GET['id'])) {
        header('HTTP/1.1 400 Invalid Request (must provide id)');
        send_json_response(array('message' => 'You must provide a user id when deleting users'));
        exit(0);
    }

    if (!dict_get($current_user_info['permissions'], 'user_admin', false)) {
        header('HTTP/1.1 403 Access Denied');
        send_json_response(array('message' => 'You don not have permissions to delete other users'));
        exit(0);
    }

    validate_csrf();
    $user_id = (int) $_GET['id'];
    $confirm_email = $_SERVER['HTTP_X_CONFIRM_EMAIL'];
    $user_data = user_load($user_id);

    if (!$user_data) {
        header('HTTP/1.1 404 Not Found');
        send_json_response(array('message' => 'User not found'));
        exit(0);
    }

    if ($confirm_email != $user_data['email']) {
        header('HTTP/1.1 400 Email confirmation failed');
        send_json_response(array('message' => 'Confirmation email incorrect'));
        exit(0);
    }

    $query = get_db_session()->prepare('DELETE FROM users WHERE id = ? AND email = ?');
    $query->execute(array($user_id, $confirm_email));
    header('HTTP/1.1 204 No Content');
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    send_json_response(array('message' => 'method not allowed'));
    exit(0);
}
