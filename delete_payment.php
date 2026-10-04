<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$payment_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Verify ownership: either payer or receiver
$stmt = $pdo->prepare("SELECT payer_id, receiver_id FROM payments WHERE id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();
if (!$payment || ($payment['payer_id'] != $user_id && $payment['receiver_id'] != $user_id)) {
    $_SESSION['error'] = "Action non autorisée.";
    redirect('profile.php#work');
}

// Delete payment (cascade will delete notifications)
$pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$payment_id]);

$_SESSION['success'] = "Paiement supprimé.";
redirect('profile.php#work');