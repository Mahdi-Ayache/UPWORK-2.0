<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$freelancer_id = isset($_GET['freelancer_id']) ? (int)$_GET['freelancer_id'] : 0;

if (!$project_id || !$freelancer_id) {
    $_SESSION['error'] = "Informations de paiement manquantes.";
    redirect('dashboard.php');
}

// Verify project belongs to client and is ready for payment
$proj_stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND client_id = ?");
$proj_stmt->execute([$project_id, $_SESSION['user_id']]);
$project = $proj_stmt->fetch();
if (!$project) {
    $_SESSION['error'] = "Projet introuvable.";
    redirect('dashboard.php');
}

// Check if freelancer is assigned to this project
$check = $pdo->prepare("SELECT id FROM applications WHERE project_id = ? AND freelancer_id = ? AND status = 'accepted'");
$check->execute([$project_id, $freelancer_id]);
if ($check->rowCount() == 0) {
    $_SESSION['error'] = "Freelancer non assigné à ce projet.";
    redirect('dashboard.php');
}

// Check if project is ready for payment
if ($project['freelancer_ready'] != 1) {
    $_SESSION['error'] = "Le freelancer n'a pas encore marqué le projet comme terminé.";
    redirect('dashboard.php');
}

// Check if already paid
$paid_check = $pdo->prepare("SELECT id FROM payments WHERE project_id = ? AND receiver_id = ?");
$paid_check->execute([$project_id, $freelancer_id]);
if ($paid_check->rowCount() > 0) {
    $_SESSION['error'] = "Ce projet a déjà été payé.";
    redirect('dashboard.php');
}

$amount = $project['budget'];
$final_amount = $amount;
$coupon_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $method = $_POST['method'];
    $coupon_code = strtoupper(trim($_POST['coupon']));
    
    if (!empty($coupon_code)) {
        $coup = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND active = 1");
        $coup->execute([$coupon_code]);
        $coupon = $coup->fetch();
        if ($coupon) {
            $discount = $amount * ($coupon['discount_percent'] / 100);
            $final_amount = $amount - $discount;
            $coupon_msg = "<div class='alert alert-success'>Coupon appliqué : -" . $coupon['discount_percent'] . "%</div>";
        } else {
            $coupon_msg = "<div class='alert alert-danger'>Code promo invalide</div>";
            $final_amount = $amount;
        }
    }
    
    // Process payment (only if coupon valid or no coupon)
    if (empty($coupon_code) || (isset($coupon) && $coupon)) {
        try {
            // Insert payment record
            $stmt = $pdo->prepare("INSERT INTO payments (project_id, payer_id, receiver_id, amount, method, coupon_code, receipt_sent, payer_receipt_viewed, receiver_receipt_viewed) 
                                   VALUES (?, ?, ?, ?, ?, ?, 1, 0, 0)");
            $stmt->execute([$project_id, $_SESSION['user_id'], $freelancer_id, $final_amount, $method, $coupon_code ?? null]);
            $payment_id = $pdo->lastInsertId();
            
            // Insert notifications for both parties
            $message_payer = "Paiement de " . number_format($final_amount, 2) . "€ pour le projet '{$project['title']}' effectué avec succès. Un reçu vous a été envoyé par email.";
            $message_receiver = "Paiement de " . number_format($final_amount, 2) . "€ pour le projet '{$project['title']}' reçu. Un reçu vous a été envoyé par email.";
            
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, payment_id, message, type) VALUES (?, ?, ?, 'payment_sent')");
            $stmt->execute([$_SESSION['user_id'], $payment_id, $message_payer]);
            
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, payment_id, message, type) VALUES (?, ?, ?, 'payment_received')");
            $stmt->execute([$freelancer_id, $payment_id, $message_receiver]);
            
            // Update project status to completed
            $pdo->prepare("UPDATE projects SET status = 'completed', freelancer_ready = 0 WHERE id = ?")->execute([$project_id]);
            
            // Simulate email receipt
            $stmt_mail = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt_mail->execute([$_SESSION['user_id']]);
            $client_email = $stmt_mail->fetchColumn();
            $stmt_mail2 = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt_mail2->execute([$freelancer_id]);
            $freelancer_email = $stmt_mail2->fetchColumn();
            
            $subject = "Reçu de paiement - Upwork 2.0";
            $client_message = "Bonjour,\n\nVous avez payé " . number_format($final_amount, 2) . "€ pour le projet '{$project['title']}' via $method.\n\nConsultez votre historique pour voir le reçu.\n\nMerci de votre confiance !";
            $freelancer_message = "Bonjour,\n\nVous avez reçu " . number_format($final_amount, 2) . "€ pour le projet '{$project['title']}' via $method.\n\nConsultez votre historique pour voir le reçu.\n\nFélicitations !";
            
            @mail($client_email, $subject, $client_message, "From: no-reply@upwork2.com");
            @mail($freelancer_email, $subject, $freelancer_message, "From: no-reply@upwork2.com");
            
            $_SESSION['success'] = "Paiement effectué avec succès ! Un reçu a été envoyé à votre adresse email.";
            redirect('dashboard.php');
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors du paiement. Veuillez réessayer.";
            redirect("make_payment.php?project_id=$project_id&freelancer_id=$freelancer_id");
        }
    }
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h3 class="fw-semibold mb-4"><i class="fas fa-credit-card me-2 text-success"></i>Paiement sécurisé</h3>
                <p><strong>Projet :</strong> <?= htmlspecialchars($project['title']) ?></p>
                <p><strong>Montant initial :</strong> <?= number_format($amount, 2) ?> €</p>
                <?= $coupon_msg ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Code promo (ex: D17, COUPON10)</label>
                        <input type="text" name="coupon" class="form-control" placeholder="Code promo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Méthode de paiement</label>
                        <select name="method" class="form-select" required>
                            <option value="Carte bancaire">Carte bancaire</option>
                            <option value="PayPal">PayPal</option>
                            <option value="Virement">Virement</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <strong>Total à payer :</strong> <?= number_format($final_amount, 2) ?> €
                    </div>
                    <button type="submit" class="btn btn-primary-custom w-100 py-2">Confirmer paiement <i class="fas fa-check-circle ms-2"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>