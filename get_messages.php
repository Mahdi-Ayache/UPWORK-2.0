<?php
require_once 'config.php';
if (!isLoggedIn()) exit('Unauthorized');

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!$project_id) exit('Invalid project');

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Verify authorization
if ($role == 'client') {
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND client_id = ?");
    $stmt->execute([$project_id, $user_id]);
    $is_authorized = $stmt->rowCount() > 0;
} else {
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE project_id = ? AND freelancer_id = ? AND status = 'accepted'");
    $stmt->execute([$project_id, $user_id]);
    $is_authorized = $stmt->rowCount() > 0;
}
if (!$is_authorized) exit('Unauthorized');

$stmt = $pdo->prepare("SELECT m.*, u.name as sender_name, u.avatar 
                       FROM messages m 
                       JOIN users u ON m.sender_id = u.id 
                       WHERE m.project_id = ? 
                       ORDER BY m.created_at ASC");
$stmt->execute([$project_id]);
$messages = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($messages);