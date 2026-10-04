<?php
require_once 'config.php';
if (!isLoggedIn()) exit;
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
echo 'ok';