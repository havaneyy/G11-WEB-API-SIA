<?php
require_once __DIR__ . '/../config.php';
unset($_SESSION['codecraft_admin']);
session_regenerate_id(true);
header('Location: login.php');
exit;
