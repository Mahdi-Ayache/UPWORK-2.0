<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

$freelancer_id = isset($_GET['freelancer_id']) ? (int)$_GET['freelancer_id'] : 0;
if (!$freelancer_id) redirect('freelancers.php');

// Fetch freelancer details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'freelancer'");
$stmt->execute([$freelancer_id]);
$freelancer = $stmt->fetch();
if (!$freelancer) {
    $_SESSION['error'] = "Freelancer introuvable.";
    redirect('freelancers.php');
}

// Fetch client's open projects
$stmt = $pdo->prepare("SELECT * FROM projects WHERE client_id = ? AND status = 'open' ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

if (count($projects) == 0) {
    $_SESSION['error'] = "Vous n'avez aucun projet ouvert pour faire une offre.";
    redirect('freelancers.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = (int)$_POST['project_id'];
    $message = trim($_POST['message']);

    // Verify project belongs to client
    $stmt = $pdo->prepare("SELECT title FROM projects WHERE id = ? AND client_id = ? AND status = 'open'");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    if (!$project) {
        $_SESSION['error'] = "Projet invalide.";
        redirect("make_offer.php?freelancer_id=$freelancer_id");
    }

    // Check if offer already exists
    $stmt = $pdo->prepare("SELECT id FROM offers WHERE client_id = ? AND freelancer_id = ? AND project_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id'], $freelancer_id, $project_id]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Une offre est déjà en attente pour ce projet.";
        redirect("make_offer.php?freelancer_id=$freelancer_id");
    }

    // Insert offer
    $stmt = $pdo->prepare("INSERT INTO offers (client_id, freelancer_id, project_id, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $freelancer_id, $project_id, $message]);
    $offer_id = $pdo->lastInsertId();

    // Create notification for freelancer (payment_id is NULL because it's an offer)
    $message_notif = "Vous avez reçu une offre pour le projet '{$project['title']}' de la part de " . $_SESSION['first_name'] . " " . $_SESSION['last_name'];
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, offer_id, message, type, payment_id) VALUES (?, ?, ?, 'offer_received', NULL)");
    $stmt->execute([$freelancer_id, $offer_id, $message_notif]);

    $_SESSION['success'] = "Offre envoyée avec succès ! Le freelancer sera notifié.";
    redirect('freelancers.php');
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="fw-semibold mb-4">Faire une offre</h2>
                <p>Freelancer: <strong><?= htmlspecialchars($freelancer['first_name'] . ' ' . $freelancer['last_name']) ?></strong></p>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Projet</label>
                        <select name="project_id" class="form-select" required>
                            <option value="">Sélectionner un projet</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['title']) ?> (<?= number_format($proj['budget'],2) ?> €)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message (optionnel)</label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Décrivez votre projet et pourquoi vous choisissez ce freelancer..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-custom">Envoyer l'offre</button>
                        <a href="freelancers.php" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>