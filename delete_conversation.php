<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$other_user_id) redirect('conversations.php');

$user_id = $_SESSION['user_id'];

// Delete all messages where (sender = current and receiver = other) OR (sender = other and receiver = current)
$stmt = $pdo->prepare("DELETE FROM direct_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
$stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);

$_SESSION['success'] = "Conversation supprimée.";
redirect('conversations.php');