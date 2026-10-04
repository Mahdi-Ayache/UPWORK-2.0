<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

$freelancer_id = isset($_GET['freelancer_id']) ? (int)$_GET['freelancer_id'] : 0;
if (!$freelancer_id) redirect('freelancers.php');

// Get freelancer name for display
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ? AND role = 'freelancer'");
$stmt->execute([$freelancer_id]);
$freelancer = $stmt->fetch();
if (!$freelancer) {
    $_SESSION['error'] = "Freelancer introuvable.";
    redirect('freelancers.php');
}

// Get client's open projects
$stmt = $pdo->prepare("SELECT id, title FROM projects WHERE client_id = ? AND status = 'open'");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = (int)$_POST['project_id'];
    $message = trim($_POST['message']);
    // Check if already offered
    $check = $pdo->prepare("SELECT id FROM offers WHERE project_id = ? AND freelancer_id = ? AND status = 'pending'");
    $check->execute([$project_id, $freelancer_id]);
    if ($check->rowCount() > 0) {
        $_SESSION['error'] = "Vous avez déjà proposé ce projet à ce freelancer.";
        redirect("propose_project.php?freelancer_id=$freelancer_id");
    }
    // Insert offer
    $stmt = $pdo->prepare("INSERT INTO offers (project_id, freelancer_id, client_id, message, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$project_id, $freelancer_id, $_SESSION['user_id'], $message]);
    $_SESSION['success'] = "Offre envoyée à " . htmlspecialchars($freelancer['first_name']) . ".";
    redirect("freelancer_profile.php?id=$freelancer_id");
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h3 class="fw-semibold mb-3">Proposer un projet à <?= htmlspecialchars($freelancer['first_name'] . ' ' . $freelancer['last_name']) ?></h3>
                <?php if (count($projects) == 0): ?>
                    <div class="alert alert-warning">Vous n'avez aucun projet ouvert à proposer.</div>
                    <a href="create_project.php" class="btn btn-primary-custom">Publier un projet</a>
                <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Choisir un projet</label>
                            <select name="project_id" class="form-select" required>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message (optionnel)</label>
                            <textarea name="message" class="form-control" rows="4" placeholder="Expliquez pourquoi ce projet correspond à ses compétences..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary-custom">Envoyer l'offre</button>
                        <a href="freelancer_profile.php?id=<?= $freelancer_id ?>" class="btn btn-outline-secondary ms-2">Annuler</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>