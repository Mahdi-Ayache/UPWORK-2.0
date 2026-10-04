<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'freelancer') redirect('dashboard.php');

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify ownership: freelancer must be assigned to this project
$stmt = $pdo->prepare("SELECT p.id, p.title, p.client_id, a.status 
                       FROM projects p 
                       JOIN applications a ON a.project_id = p.id 
                       WHERE p.id = ? AND a.freelancer_id = ? AND a.status = 'accepted'");
$stmt->execute([$project_id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    $_SESSION['error'] = "Projet introuvable ou vous n'y êtes pas assigné.";
    redirect('dashboard.php');
}

// Mark as ready
$pdo->prepare("UPDATE projects SET freelancer_ready = 1 WHERE id = ?")->execute([$project_id]);

// Notify client
$message_notif = "Le freelancer a marqué le projet '{$project['title']}' comme terminé. Vous pouvez maintenant procéder au paiement.";
$stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, payment_id) VALUES (?, ?, 'project_ready', NULL)");
$stmt->execute([$project['client_id'], $message_notif]);

$_SESSION['success'] = "Projet marqué comme terminé. Le client sera notifié pour le paiement.";
redirect('dashboard.php');