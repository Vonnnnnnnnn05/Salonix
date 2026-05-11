<?php
require_once __DIR__ . "\\conn.php";
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
