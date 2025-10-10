<?php
// cashier_logout.php
session_start();
session_destroy();
header('Location: cashier_login.php');
exit;