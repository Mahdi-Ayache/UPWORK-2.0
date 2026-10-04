<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

$project_id = $_GET['project_id'];
$check_owner = $pdo->prepare("SELECT * FROM projects WHERE id=? AND client_id=?");
$check_owner->execute([$project_id, $_SESSION['user_id']]);
$project = $check_owner->fetch();
if (!$project) redirect('dashboard.php');

// Handle accept (start work) - redirect before any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept'])) {
    $application_id = (int)$_POST['application_id'];
    $freelancer_id = (int)$_POST['freelancer_id'];
    // Update application status
    $pdo->prepare("UPDATE applications SET status = 'accepted' WHERE id = ?")->execute([$application_id]);
    // Update project status to in_progress
    $pdo->prepare("UPDATE projects SET status = 'in_progress' WHERE id = ?")->execute([$project_id]);
    // Notify freelancer
    $message_notif = "Votre candidature pour le projet '{$project['title']}' a été acceptée. Vous pouvez commencer à travailler.";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, payment_id) VALUES (?, ?, 'application_accepted', NULL)");
    $stmt->execute([$freelancer_id, $message_notif]);
    $_SESSION['success'] = "Freelancer accepté. Le travail peut commencer.";
    redirect("applications.php?project_id=$project_id");
}

// Now include header only after processing
include 'header.php';

$apps = $pdo->prepare("SELECT a.*, u.name, u.id as freelancer_id FROM applications a JOIN users u ON a.freelancer_id = u.id WHERE a.project_id=?");
$apps->execute([$project_id]);
$applications = $apps->fetchAll();
?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="fw-semibold mb-4">Candidatures pour : <?= htmlspecialchars($project['title']) ?></h2>
        <?php if(count($applications)==0): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="lead">Aucune candidature reçue pour le moment.</p>
            </div>
        <?php else: ?>
            <?php foreach($applications as $app): ?>
                <div class="card mb-3 border">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="card-title fw-semibold"><?= htmlspecialchars($app['name']) ?></h5>
                                <p class="card-text mt-2"><?= nl2br(htmlspecialchars($app['cover_letter'])) ?></p>
                            </div>
                            <div class="text-end">
                                <span class="badge <?= $app['status'] == 'pending' ? 'bg-warning' : ($app['status'] == 'accepted' ? 'bg-success' : 'bg-danger') ?>">
                                    <?= $app['status'] == 'pending' ? 'En attente' : ($app['status'] == 'accepted' ? 'Accepté' : 'Refusé') ?>
                                </span>
                                <div class="mt-2">
                                    <a href="freelancer_profile.php?id=<?= $app['freelancer_id'] ?>" class="btn btn-sm btn-outline-secondary me-2">Voir profil</a>
                                    <?php if($app['status'] == 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                            <input type="hidden" name="freelancer_id" value="<?= $app['freelancer_id'] ?>">
                                            <button type="submit" name="accept" class="btn btn-sm btn-success me-2">Accepter et démarrer</button>
                                        </form>
                                        <a href="decline_application.php?id=<?= $app['id'] ?>&project_id=<?= $project_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Refuser cette candidature ?');">Refuser</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>