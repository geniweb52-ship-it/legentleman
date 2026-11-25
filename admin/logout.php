<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
$_SESSION = [];
session_destroy();
redirect('login.php');
