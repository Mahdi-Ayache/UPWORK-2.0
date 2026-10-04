<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete'])) {
    $project_id = $_POST['project_id'];
    // Verify ownership
    $check = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND client_id = ?");
    $check->execute([$project_id, $_SESSION['user_id']]);
    if ($check->rowCount() > 0) {
        $pdo->prepare("UPDATE projects SET status = 'completed' WHERE id = ?")->execute([$project_id]);
        $_SESSION['success'] = "Projet marqué comme terminé. Vous pouvez maintenant évaluer le freelancer sur son profil.";
    } else {
        $_SESSION['error'] = "Action non autorisée.";
    }
}
redirect('dashboard.php');