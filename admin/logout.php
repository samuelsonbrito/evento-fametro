<?php
require_once __DIR__ . '/../includes/functions.php';

$_SESSION = array();
session_destroy();
header('Location: /admin/login.php');
exit;