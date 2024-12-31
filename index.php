<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
session_start();

if (isAuthenticated()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit; 