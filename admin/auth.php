<?php
require_once __DIR__ . '/../config.php';

function require_admin(): void {
    if (!empty($_SESSION['codecraft_admin']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
        $_SESSION['codecraft_admin'] = true;
        return;
    }
    header('Location: login.php');
    exit;
}
