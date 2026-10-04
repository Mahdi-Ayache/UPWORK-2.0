<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'followers';

if (!$user_id) redirect('dashboard.php');

$stmt = $pdo->prepare("SELECT first_name, last_name, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) redirect('dashboard.php');

if ($type == 'followers') {
    $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.avatar, u.role
                           FROM follows f
                           JOIN users u ON f.follower_id = u.id
                           WHERE f.following_id = ?");
    $stmt->execute([$user_id]);
    $users = $stmt->fetchAll();
    $title = "Abonnés de " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
} else {
    $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.avatar, u.role
                           FROM follows f
                           JOIN users u ON f.following_id = u.id
                           WHERE f.follower_id = ?");
    $stmt->execute([$user_id]);
    $users = $stmt->fetchAll();
    $title = "Abonnements de " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
}

include 'header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <h1 class="fw-bold mb-4"><?= $title ?></h1>
        <?php if (count($users) == 0): ?>
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p class="lead">Aucun utilisateur trouvé.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($users as $u): ?>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <?php if (!empty($u['avatar']) && file_exists($u['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($u['avatar']) ?>" class="rounded-circle" width="48" height="48" style="object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-3x text-muted"></i>
                                <?php endif; ?>
                                <div>
                                    <h5 class="card-title fw-semibold"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></h5>
                                    <p class="text-muted small"><?= $u['role'] == 'client' ? 'Client' : 'Freelancer' ?></p>
                                    <?php if ($u['role'] == 'client'): ?>
                                        <a href="client_profile.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary">Voir profil</a>
                                    <?php else: ?>
                                        <a href="freelancer_profile.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary">Voir profil</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>