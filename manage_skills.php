<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'freelancer') redirect('dashboard.php');

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_skill'])) {
    $skill = trim($_POST['skill']);
    if (!empty($skill)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO skills (user_id, skill_name) VALUES (?, ?)");
            $stmt->execute([$user_id, $skill]);
            $_SESSION['success'] = "Compétence ajoutée.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Cette compétence existe déjà.";
        }
    } else {
        $_SESSION['error'] = "Veuillez saisir une compétence.";
    }
    redirect('profile.php#skills');
}

if (isset($_GET['remove_skill'])) {
    $skill_id = (int)$_GET['remove_skill'];
    $stmt = $pdo->prepare("DELETE FROM skills WHERE id = ? AND user_id = ?");
    $stmt->execute([$skill_id, $user_id]);
    $_SESSION['success'] = "Compétence supprimée.";
    redirect('profile.php#skills');
}

redirect('profile.php');