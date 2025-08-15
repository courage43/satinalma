<?php
require_once 'config/auth.php';
$auth->logout();
header('Location: login.php?msg=logout');
exit();
?>