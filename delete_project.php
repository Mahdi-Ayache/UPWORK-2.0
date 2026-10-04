<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

$id = $_GET['id'];

// First delete any payments related to this project
$pdo->prepare("DELETE FROM payments WHERE project_id = ?")->execute([$id]);

// Then delete applications for this project
$pdo->prepare("DELETE FROM applications WHERE project_id = ?")->execute([$id]);

// Finally delete the project
$pdo->prepare("DELETE FROM projects WHERE id=? AND client_id=?")->execute([$id, $_SESSION['user_id']]);

$_SESSION['success'] = "Projet supprimé.";
redirect('dashboard.php');