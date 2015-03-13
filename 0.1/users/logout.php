<?php
require_once(dirname(dirname(__DIR__)).'/global.php');

validate_csrf();
unset($_SESSION['user_id']);
