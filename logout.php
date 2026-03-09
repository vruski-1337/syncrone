<?php
session_start();
require_once 'includes/auth.php';
logout();
header('Location: index.php');
exit;
