<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$client_id) redirect('dashboard.php');

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'client'");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    $_SESSION['error'] = "Client introuvable.";
    redirect('dashboard.php');
}

// Follower counts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$stmt->execute([$client_id]);
$followers_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$stmt->execute([$client_id]);
$following_count = $stmt->fetchColumn();

// Check if current user follows this client
$is_following = false;
if ($_SESSION['user_id'] != $client_id) {
    $stmt = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$_SESSION['user_id'], $client_id]);
    $is_following = $stmt->rowCount() > 0;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE client_id = ?");
$stmt->execute([$client_id]);
$project_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM projects WHERE client_id = ? AND status = 'open' ORDER BY created_at DESC");
$stmt->execute([$client_id]);
$projects = $stmt->fetchAll();

include 'header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <?php if (!empty($client['avatar']) && file_exists($client['avatar'])): ?>
                            <img src="<?= htmlspecialchars($client['avatar']) ?>" class="rounded-circle img-fluid" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user-circle fa-5x text-muted"></i>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <h3 class="fw-semibold"><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></h3>
                        <p class="text-muted">Client</p>
                        <?php if (!empty($client['country'])): ?>
                            <p><i class="fas fa-map-marker-alt me-2 text-muted"></i><?= htmlspecialchars($client['country']) ?></p>
                        <?php endif; ?>
                        <p><i class="fas fa-briefcase me-2 text-muted"></i><?= $project_count ?> projet(s) publié(s)</p>
                        <?php if (!empty($client['bio'])): ?>
                            <hr>
                            <h5 class="fw-semibold">À propos</h5>
                            <p><?= nl2br(htmlspecialchars($client['bio'])) ?></p>
                        <?php endif; ?>
                        <div class="mt-2">
                            <a href="followers_list.php?id=<?= $client_id ?>&type=followers" class="text-decoration-none me-3"><i class="fas fa-users"></i> <?= $followers_count ?> abonnés</a>
                            <a href="followers_list.php?id=<?= $client_id ?>&type=following" class="text-decoration-none"><i class="fas fa-user-plus"></i> <?= $following_count ?> abonnements</a>
                        </div>
                        <div class="mt-3">
                            <?php if ($_SESSION['user_id'] != $client_id): ?>
                                <a href="follow.php?id=<?= $client_id ?>" class="btn btn-sm <?= $is_following ? 'btn-outline-danger' : 'btn-primary-custom' ?> me-2">
                                    <?= $is_following ? 'Ne plus suivre' : 'Suivre' ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] == 'freelancer'): ?>
                                <a href="direct_chat.php?user_id=<?= $client_id ?>" class="btn btn-sm btn-primary-custom">Envoyer un message</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="fw-semibold mb-3">Projets de ce client</h3>
        <?php if (count($projects) == 0): ?>
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                    <p class="lead">Ce client n'a publié aucun projet pour le moment.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($projects as $proj): ?>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title fw-semibold"><?= htmlspecialchars($proj['title']) ?></h5>
                                    <span class="badge bg-success">Ouvert</span>
                                </div>
                                <p class="text-muted small">Budget: <?= number_format($proj['budget'],2) ?> €</p>
                                <p class="card-text small"><?= htmlspecialchars(substr($proj['description'], 0, 150)) ?>...</p>
                                <div class="mt-3">
                                    <?php
                                    $check_applied = $pdo->prepare("SELECT id FROM applications WHERE project_id = ? AND freelancer_id = ?");
                                    $check_applied->execute([$proj['id'], $_SESSION['user_id']]);
                                    $already_applied = $check_applied->rowCount() > 0;
                                    ?>
                                    <?php if ($already_applied): ?>
                                        <span class="badge bg-secondary">Candidature déjà envoyée</span>
                                    <?php else: ?>
                                        <a href="apply_project.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-primary-custom">Postuler</a>
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