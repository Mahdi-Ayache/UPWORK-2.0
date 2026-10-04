<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'freelancer') redirect('dashboard.php');

$project_id = $_GET['id'];
$check = $pdo->prepare("SELECT * FROM applications WHERE project_id=? AND freelancer_id=?");
$check->execute([$project_id, $_SESSION['user_id']]);
if ($check->rowCount() == 0) {
    $stmt = $pdo->prepare("INSERT INTO applications (project_id, freelancer_id, cover_letter, status) VALUES (?,?,?,'pending')");
    $stmt->execute([$project_id, $_SESSION['user_id'], "Je suis très intéressé par ce projet et j'ai les compétences requises."]);
    
    // Get client ID
    $stmt = $pdo->prepare("SELECT client_id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $client_id = $stmt->fetchColumn();
    
    // Notify client
    $message = "Un freelancer (" . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . ") a postulé à votre projet.";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, payment_id, is_read) VALUES (?, ?, 'new_application', NULL, 0)");
    $stmt->execute([$client_id, $message]);
    
    $_SESSION['success'] = "Votre candidature a bien été envoyée au client !";
} else {
    $_SESSION['error'] = "Vous avez déjà postulé à ce projet.";
}
redirect('dashboard.php');