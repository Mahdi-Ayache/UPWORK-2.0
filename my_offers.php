<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'freelancer') redirect('dashboard.php');

// Handle accept/decline
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $offer_id = (int)$_POST['offer_id'];
    $action = $_POST['action'];

    $stmt = $pdo->prepare("SELECT o.*, p.title as project_title, p.budget, p.client_id, u.name as client_name 
                           FROM offers o 
                           JOIN projects p ON o.project_id = p.id 
                           JOIN users u ON o.client_id = u.id 
                           WHERE o.id = ? AND o.freelancer_id = ? AND o.status = 'pending'");
    $stmt->execute([$offer_id, $_SESSION['user_id']]);
    $offer = $stmt->fetch();

    if (!$offer) {
        $_SESSION['error'] = "Offre introuvable ou déjà traitée.";
        redirect('my_offers.php');
    }

    if ($action == 'accept') {
        // Update offer status
        $pdo->prepare("UPDATE offers SET status = 'accepted' WHERE id = ?")->execute([$offer_id]);

        // Create application automatically with status 'accepted'
        $stmt = $pdo->prepare("INSERT INTO applications (project_id, freelancer_id, cover_letter, status) VALUES (?, ?, ?, 'accepted')");
        $stmt->execute([$offer['project_id'], $_SESSION['user_id'], "Offre acceptée pour le projet '{$offer['project_title']}'"]);

        // Update project status to in_progress (work can start)
        $pdo->prepare("UPDATE projects SET status = 'in_progress' WHERE id = ?")->execute([$offer['project_id']]);

        // Notify client
        $message_notif = "Votre offre pour le projet '{$offer['project_title']}' a été acceptée par " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . ". Le travail peut commencer.";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, offer_id, message, type, payment_id) VALUES (?, ?, ?, 'offer_accepted', NULL)");
        $stmt->execute([$offer['client_id'], $offer_id, $message_notif]);

        $_SESSION['success'] = "Offre acceptée ! Vous pouvez maintenant commencer à travailler.";
    } elseif ($action == 'decline') {
        // Update offer status
        $pdo->prepare("UPDATE offers SET status = 'declined' WHERE id = ?")->execute([$offer_id]);

        // Notify client
        $message_notif = "Votre offre pour le projet '{$offer['project_title']}' a été refusée par " . $_SESSION['first_name'] . " " . $_SESSION['last_name'];
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, offer_id, message, type, payment_id) VALUES (?, ?, ?, 'offer_declined', NULL)");
        $stmt->execute([$offer['client_id'], $offer_id, $message_notif]);

        $_SESSION['success'] = "Offre refusée.";
    }
    redirect('my_offers.php');
}

// Fetch pending offers
$stmt = $pdo->prepare("SELECT o.*, p.title as project_title, p.budget, u.name as client_name 
                       FROM offers o 
                       JOIN projects p ON o.project_id = p.id 
                       JOIN users u ON o.client_id = u.id 
                       WHERE o.freelancer_id = ? AND o.status = 'pending'
                       ORDER BY o.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$pending_offers = $stmt->fetchAll();

// Fetch accepted offers history
$stmt = $pdo->prepare("SELECT o.*, p.title as project_title, p.budget, u.name as client_name 
                       FROM offers o 
                       JOIN projects p ON o.project_id = p.id 
                       JOIN users u ON o.client_id = u.id 
                       WHERE o.freelancer_id = ? AND o.status != 'pending'
                       ORDER BY o.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$history_offers = $stmt->fetchAll();

include 'header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="fw-bold mb-4">Offres reçues</h1>

        <!-- Pending Offers -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="fw-semibold mb-3">Offres en attente</h5>
                <?php if (count($pending_offers) == 0): ?>
                    <p class="text-muted">Aucune offre en attente.</p>
                <?php else: ?>
                    <?php foreach ($pending_offers as $offer): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="fw-semibold">Projet: <?= htmlspecialchars($offer['project_title']) ?></h6>
                                    <p class="text-muted small mb-1">Client: <?= htmlspecialchars($offer['client_name']) ?></p>
                                    <p class="text-muted small">Budget: <?= number_format($offer['budget'],2) ?> €</p>
                                    <?php if (!empty($offer['message'])): ?>
                                        <p class="small mt-2"><strong>Message:</strong> <?= nl2br(htmlspecialchars($offer['message'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                        <button type="submit" name="action" value="accept" class="btn btn-sm btn-success me-2" onclick="return confirm('Accepter cette offre ?');">Accepter</button>
                                        <button type="submit" name="action" value="decline" class="btn btn-sm btn-danger" onclick="return confirm('Refuser cette offre ?');">Refuser</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Offer History -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-semibold mb-3">Historique des offres</h5>
                <?php if (count($history_offers) == 0): ?>
                    <p class="text-muted">Aucun historique.</p>
                <?php else: ?>
                    <?php foreach ($history_offers as $offer): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="fw-semibold"><?= htmlspecialchars($offer['project_title']) ?></h6>
                                    <p class="text-muted small">Client: <?= htmlspecialchars($offer['client_name']) ?></p>
                                    <p class="text-muted small">Budget: <?= number_format($offer['budget'],2) ?> €</p>
                                </div>
                                <div class="text-end">
                                    <span class="badge <?= $offer['status'] == 'accepted' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $offer['status'] == 'accepted' ? 'Accepté' : 'Refusé' ?>
                                    </span>
                                    <div class="small text-muted mt-1"><?= date('d/m/Y', strtotime($offer['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>