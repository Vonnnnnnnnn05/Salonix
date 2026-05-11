<?php
include "../config/session.php";
$_SESSION = [];
session_destroy();
header("Location: ../index.php");
exit;

