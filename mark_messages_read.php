<?php
require_once 'config.php';
if (!isLoggedIn()) exit;
// Mark all direct messages as read
$pdo->prepare("UPDATE direct_messages SET is_read = 1 WHERE receiver_id = ?")->execute([$_SESSION['user_id']]);
echo 'ok';