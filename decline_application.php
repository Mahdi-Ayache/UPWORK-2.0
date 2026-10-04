<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if ($application_id && $project_id) {
    $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$application_id]);
    $_SESSION['success'] = "Candidature refusée.";
} else {
    $_SESSION['error'] = "Requête invalide.";
}
redirect("applications.php?project_id=$project_id");