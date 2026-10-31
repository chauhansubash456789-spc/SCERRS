<?php
require_once __DIR__ . '/../config/config.php';

$_SESSION = [];
session_destroy();
header('Location: ' . url('index.php'));
exit;
?>