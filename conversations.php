<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 1. Project-based conversations
if ($role == 'client') {
    $stmt = $pdo->prepare("SELECT p.id, p.title, u.name as other_name, u.avatar as other_avatar, 'project' as type
                           FROM projects p
                           JOIN applications a ON a.project_id = p.id
                           JOIN users u ON a.freelancer_id = u.id
                           WHERE p.client_id = ? AND p.status = 'in_progress' AND a.status = 'accepted'
                           ORDER BY p.created_at DESC");
    $stmt->execute([$user_id]);
} else {
    $stmt = $pdo->prepare("SELECT p.id, p.title, u.name as other_name, u.avatar as other_avatar, 'project' as type
                           FROM projects p
                           JOIN applications a ON a.project_id = p.id
                           JOIN users u ON p.client_id = u.id
                           WHERE a.freelancer_id = ? AND a.status = 'accepted' AND p.status = 'in_progress'
                           ORDER BY p.created_at DESC");
    $stmt->execute([$user_id]);
}
$project_conversations = $stmt->fetchAll();

// 2. Direct message conversations
$stmt = $pdo->prepare("SELECT DISTINCT 
                           CASE 
                               WHEN sender_id = ? THEN receiver_id
                               ELSE sender_id
                           END as other_user_id
                       FROM direct_messages 
                       WHERE sender_id = ? OR receiver_id = ?");
$stmt->execute([$user_id, $user_id, $user_id]);
$dm_users = $stmt->fetchAll();

$dm_conversations = [];
foreach ($dm_users as $dm) {
    $other_id = $dm['other_user_id'];
    $stmt = $pdo->prepare("SELECT first_name, last_name, avatar FROM users WHERE id = ?");
    $stmt->execute([$other_id]);
    $other = $stmt->fetch();
    $dm_conversations[] = [
        'id' => $other_id,
        'title' => 'Message direct',
        'other_name' => $other['first_name'] . ' ' . $other['last_name'],
        'other_avatar' => $other['avatar'],
        'type' => 'direct'
    ];
}

// Merge
$conversations = array_merge($project_conversations, $dm_conversations);

include 'header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <h1 class="fw-bold mb-4">Mes conversations</h1>
        <?php if (count($conversations) == 0): ?>
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <p class="lead">Aucune conversation active.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($conversations as $conv): ?>
                <div class="card mb-3">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <?php if (!empty($conv['other_avatar']) && file_exists($conv['other_avatar'])): ?>
                                <img src="<?= htmlspecialchars($conv['other_avatar']) ?>" class="rounded-circle" width="48" height="48" style="object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-3x text-muted"></i>
                            <?php endif; ?>
                            <div>
                                <h5 class="card-title fw-semibold"><?= htmlspecialchars($conv['title']) ?></h5>
                                <p class="text-muted small mb-0">Avec: <?= htmlspecialchars($conv['other_name']) ?></p>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($conv['type'] == 'project'): ?>
                                <a href="chat.php?project_id=<?= $conv['id'] ?>" class="btn btn-sm btn-primary-custom">Ouvrir</a>
                            <?php else: ?>
                                <a href="direct_chat.php?user_id=<?= $conv['id'] ?>" class="btn btn-sm btn-primary-custom">Ouvrir</a>
                                <a href="delete_conversation.php?user_id=<?= $conv['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer toute cette conversation ?');">Supprimer</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>