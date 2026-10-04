<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user_id = $_SESSION['user_id'];
$target_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$target_id || $target_id == $user_id) {
    $_SESSION['error'] = "Action invalide.";
    redirect('dashboard.php');
}

// Check if already following
$stmt = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
$stmt->execute([$user_id, $target_id]);
if ($stmt->rowCount() > 0) {
    // Unfollow
    $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?")->execute([$user_id, $target_id]);
    $_SESSION['success'] = "Vous ne suivez plus cet utilisateur.";
} else {
    // Follow
    $pdo->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)")->execute([$user_id, $target_id]);
    $_SESSION['success'] = "Vous suivez maintenant cet utilisateur.";
}

// Redirect back to the profile page
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$target_id]);
$role = $stmt->fetchColumn();
if ($role == 'client') {
    redirect("client_profile.php?id=$target_id");
} else {
    redirect("freelancer_profile.php?id=$target_id");
}