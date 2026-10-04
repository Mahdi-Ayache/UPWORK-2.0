<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'freelancer') redirect('dashboard.php');

$stmt = $pdo->prepare("SELECT p.*, u.name as client_name 
                       FROM projects p 
                       JOIN applications a ON a.project_id = p.id 
                       JOIN users u ON p.client_id = u.id 
                       WHERE a.freelancer_id = ? AND a.status = 'accepted' AND p.status = 'in_progress'
                       ORDER BY p.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$active_projects = $stmt->fetchAll();

include 'header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="fw-bold mb-4">Mes projets en cours</h1>

        <?php if (count($active_projects) == 0): ?>
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                    <p class="lead">Aucun projet en cours.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($active_projects as $proj): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title fw-semibold"><?= htmlspecialchars($proj['title']) ?></h5>
                                <p class="text-muted small">Client: <?= htmlspecialchars($proj['client_name']) ?></p>
                                <p class="text-muted small">Budget: <?= number_format($proj['budget'],2) ?> €</p>
                                <p class="card-text small"><?= htmlspecialchars(substr($proj['description'], 0, 100)) ?>...</p>
                                <div class="mt-3 d-flex gap-2">
                                    <a href="chat.php?project_id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-primary">Chat</a>
                                    <?php if ($proj['freelancer_ready'] == 0): ?>
                                        <a href="mark_project_ready.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-primary-custom" onclick="return confirm('Confirmer que le projet est terminé ?');">Marquer comme prêt</a>
                                    <?php else: ?>
                                        <span class="badge bg-success">En attente de paiement</span>
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