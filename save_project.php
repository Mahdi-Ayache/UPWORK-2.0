<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'freelancer') redirect('login.php');

$project_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Check if already saved
$check = $pdo->prepare("SELECT * FROM saved_projects WHERE user_id = ? AND project_id = ?");
$check->execute([$user_id, $project_id]);
if ($check->rowCount() > 0) {
    // Remove
    $stmt = $pdo->prepare("DELETE FROM saved_projects WHERE user_id = ? AND project_id = ?");
    $stmt->execute([$user_id, $project_id]);
    $_SESSION['success'] = "Projet retiré des favoris.";
} else {
    // Add
    $stmt = $pdo->prepare("INSERT INTO saved_projects (user_id, project_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $project_id]);
    $_SESSION['success'] = "Projet sauvegardé !";
}
redirect('dashboard.php');