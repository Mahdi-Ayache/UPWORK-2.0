<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'freelancer') redirect('login.php');
include 'header.php';

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT p.* FROM projects p JOIN saved_projects s ON p.id = s.project_id WHERE s.user_id = ? ORDER BY s.saved_at DESC");
$stmt->execute([$user_id]);
$savedProjects = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-12">
        <h2 class="fw-bold mb-4"><i class="fas fa-bookmark me-2 text-primary"></i>Mes projets sauvegardés</h2>
        <?php if (count($savedProjects) == 0): ?>
            <div class="glass-card p-5 text-center">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="lead">Vous n'avez encore sauvegardé aucun projet.</p>
                <a href="dashboard.php" class="btn btn-primary-glow">Explorer les missions</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($savedProjects as $proj): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="glass-card p-4 h-100 d-flex flex-column">
                            <div class="d-flex justify-content-between">
                                <h5><?= htmlspecialchars($proj['title']) ?></h5>
                                <a href="save_project.php?id=<?= $proj['id'] ?>" class="text-danger"><i class="fas fa-trash-alt"></i></a>
                            </div>
                            <p class="text-muted">Budget: <?= number_format($proj['budget'],2) ?> €</p>
                            <p class="flex-grow-1"><?= htmlspecialchars(substr($proj['description'], 0, 100)) ?>...</p>
                            <a href="apply_project.php?id=<?= $proj['id'] ?>" class="btn btn-primary-glow mt-3">Postuler</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>