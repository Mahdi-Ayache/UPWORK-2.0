<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$payment_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch payment details
$stmt = $pdo->prepare("SELECT p.*, pr.title as project_title, u_payer.name as payer_name, u_receiver.name as receiver_name 
                       FROM payments p 
                       JOIN projects pr ON p.project_id = pr.id 
                       JOIN users u_payer ON p.payer_id = u_payer.id 
                       JOIN users u_receiver ON p.receiver_id = u_receiver.id 
                       WHERE p.id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
    $_SESSION['error'] = "Reçu introuvable.";
    redirect('profile.php#work');
}

// Mark as read for the current user
if ($role == 'client' && $payment['payer_id'] == $user_id) {
    $pdo->prepare("UPDATE payments SET payer_receipt_viewed = 1 WHERE id = ?")->execute([$payment_id]);
} elseif ($role == 'freelancer' && $payment['receiver_id'] == $user_id) {
    $pdo->prepare("UPDATE payments SET receiver_receipt_viewed = 1 WHERE id = ?")->execute([$payment_id]);
} else {
    redirect('profile.php#work');
}

include 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="glass-card p-5">
            <div class="text-center mb-4">
                <i class="fas fa-receipt fa-3x text-primary mb-3"></i>
                <h2 class="fw-bold">Reçu de paiement</h2>
                <p class="text-muted">Upwork 2.0</p>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Projet :</strong> <?= htmlspecialchars($payment['project_title']) ?></p>
                    <p><strong>Montant :</strong> <?= number_format($payment['amount'],2) ?> €</p>
                    <p><strong>Méthode :</strong> <?= htmlspecialchars($payment['method']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Payeur :</strong> <?= htmlspecialchars($payment['payer_name']) ?></p>
                    <p><strong>Bénéficiaire :</strong> <?= htmlspecialchars($payment['receiver_name']) ?></p>
                    <p><strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?></p>
                </div>
            </div>
            <?php if ($payment['coupon_code']): ?>
                <p><strong>Code promo :</strong> <?= htmlspecialchars($payment['coupon_code']) ?></p>
            <?php endif; ?>
            <hr>
            <div class="text-center mt-3">
                <a href="profile.php#work" class="btn btn-primary-glow">Retour</a>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>