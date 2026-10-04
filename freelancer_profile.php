<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$freelancer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$freelancer_id) redirect('dashboard.php');

// Fetch freelancer details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'freelancer'");
$stmt->execute([$freelancer_id]);
$freelancer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$freelancer) {
    $_SESSION['error'] = "Freelancer introuvable.";
    redirect('dashboard.php');
}

// Fetch skills
$stmt = $pdo->prepare("SELECT skill_name FROM skills WHERE user_id = ?");
$stmt->execute([$freelancer_id]);
$skills = $stmt->fetchAll();

// Fetch ratings
$stmt = $pdo->prepare("SELECT r.*, u.name as client_name, p.title as project_title 
                       FROM ratings r 
                       JOIN users u ON r.client_id = u.id 
                       JOIN projects p ON r.project_id = p.id 
                       WHERE r.freelancer_id = ? 
                       ORDER BY r.created_at DESC");
$stmt->execute([$freelancer_id]);
$ratings = $stmt->fetchAll();

// Calculate average rating
$avg_rating = 0;
if (count($ratings) > 0) {
    $total = array_sum(array_column($ratings, 'rating'));
    $avg_rating = round($total / count($ratings), 1);
}

// Follower counts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$stmt->execute([$freelancer_id]);
$followers_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$stmt->execute([$freelancer_id]);
$following_count = $stmt->fetchColumn();

// Check if current user follows this freelancer
$is_following = false;
if ($_SESSION['user_id'] != $freelancer_id) {
    $stmt = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$_SESSION['user_id'], $freelancer_id]);
    $is_following = $stmt->rowCount() > 0;
}

// Check if current user (client) can rate this freelancer
$can_rate = false;
$completed_projects = [];
if ($_SESSION['role'] == 'client') {
    $client_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT p.id, p.title 
                           FROM projects p 
                           JOIN applications a ON a.project_id = p.id 
                           WHERE p.client_id = ? 
                             AND a.freelancer_id = ? 
                             AND a.status = 'accepted' 
                             AND p.status = 'completed' 
                             AND NOT EXISTS (SELECT 1 FROM ratings WHERE project_id = p.id AND freelancer_id = ?)");
    $stmt->execute([$client_id, $freelancer_id, $freelancer_id]);
    $completed_projects = $stmt->fetchAll();
    $can_rate = count($completed_projects) > 0;
}

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rate_freelancer'])) {
    if (!$can_rate) {
        $_SESSION['error'] = "Vous ne pouvez pas noter ce freelancer.";
        redirect("freelancer_profile.php?id=$freelancer_id");
    }
    $project_id = $_POST['project_id'];
    $rating = (int)$_POST['rating'];
    $review = trim($_POST['review']);
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = "Note invalide.";
        redirect("freelancer_profile.php?id=$freelancer_id");
    }
    $stmt = $pdo->prepare("INSERT INTO ratings (project_id, freelancer_id, client_id, rating, review) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$project_id, $freelancer_id, $_SESSION['user_id'], $rating, $review]);
    $_SESSION['success'] = "Merci pour votre évaluation !";
    redirect("freelancer_profile.php?id=$freelancer_id");
}

include 'header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <?php if (!empty($freelancer['avatar']) && file_exists($freelancer['avatar'])): ?>
                            <img src="<?= htmlspecialchars($freelancer['avatar']) ?>" class="rounded-circle img-fluid" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user-circle fa-5x text-muted"></i>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <h3 class="fw-semibold"><?= htmlspecialchars($freelancer['first_name'] . ' ' . $freelancer['last_name']) ?></h3>
                        <p class="text-muted">Freelancer</p>
                        <?php if ($avg_rating > 0): ?>
                            <div class="mb-2">
                                <span class="text-warning me-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= $avg_rating ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                </span>
                                <strong><?= $avg_rating ?></strong> / 5 (<?= count($ratings) ?> avis)
                            </div>
                        <?php else: ?>
                            <div class="text-muted small mb-2">Aucun avis pour l'instant.</div>
                        <?php endif; ?>
                        <p><i class="fas fa-euro-sign me-2 text-success"></i>Tarif horaire: <strong><?= $freelancer['hourly_rate'] ? number_format($freelancer['hourly_rate'],2) . ' €/h' : 'Non défini' ?></strong></p>
                        <?php if (count($skills) > 0): ?>
                            <div class="mt-2">
                                <?php foreach ($skills as $s): ?>
                                    <span class="badge bg-light text-dark me-1"><?= htmlspecialchars($s['skill_name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-2">
                            <a href="followers_list.php?id=<?= $freelancer_id ?>&type=followers" class="text-decoration-none me-3"><i class="fas fa-users"></i> <?= $followers_count ?> abonnés</a>
                            <a href="followers_list.php?id=<?= $freelancer_id ?>&type=following" class="text-decoration-none"><i class="fas fa-user-plus"></i> <?= $following_count ?> abonnements</a>
                        </div>
                        <div class="mt-3">
                            <?php if ($_SESSION['user_id'] != $freelancer_id): ?>
                                <a href="follow.php?id=<?= $freelancer_id ?>" class="btn btn-sm <?= $is_following ? 'btn-outline-danger' : 'btn-primary-custom' ?> me-2">
                                    <?= $is_following ? 'Ne plus suivre' : 'Suivre' ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] == 'client'): ?>
                                <a href="direct_chat.php?user_id=<?= $freelancer_id ?>" class="btn btn-sm btn-primary-custom me-2">Envoyer un message</a>
                                <a href="make_offer.php?freelancer_id=<?= $freelancer_id ?>" class="btn btn-sm btn-outline-secondary">Faire une offre</a>
                            <?php else: ?>
                                <a href="direct_chat.php?user_id=<?= $freelancer_id ?>" class="btn btn-sm btn-primary-custom">Envoyer un message</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bio -->
        <?php if (!empty($freelancer['bio'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold">À propos</h5>
                    <p><?= nl2br(htmlspecialchars($freelancer['bio'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Rating form (if applicable) -->
        <?php if ($can_rate): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold">Évaluer ce freelancer</h5>
                    <p>Vous avez travaillé avec <?= htmlspecialchars($freelancer['first_name']) ?> sur les projets suivants :</p>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Projet</label>
                            <select name="project_id" class="form-select" required>
                                <?php foreach ($completed_projects as $proj): ?>
                                    <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Note (1 à 5)</label>
                            <div class="rating-input">
                                <div class="btn-group" role="group">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <button type="button" class="btn btn-outline-warning star-btn" data-value="<?= $i ?>">
                                            <i class="far fa-star"></i>
                                        </button>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="rating" id="rating-value" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Votre avis (optionnel)</label>
                            <textarea name="review" class="form-control" rows="3" placeholder="Partagez votre expérience..."></textarea>
                        </div>
                        <button type="submit" name="rate_freelancer" class="btn btn-primary-custom">Envoyer l'évaluation</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Existing reviews -->
        <?php if (count($ratings) > 0): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Avis des clients</h5>
                    <?php foreach ($ratings as $r): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= htmlspecialchars($r['client_name']) ?></strong>
                                    <div class="text-warning small">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i <= $r['rating'] ? '' : '-o' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($r['created_at'])) ?></small>
                            </div>
                            <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($r['review'])) ?></p>
                            <small class="text-muted">Projet: <?= htmlspecialchars($r['project_title']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const stars = document.querySelectorAll('.star-btn');
    const ratingInput = document.getElementById('rating-value');
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const value = parseInt(this.dataset.value);
            ratingInput.value = value;
            stars.forEach((s, idx) => {
                const icon = s.querySelector('i');
                if (idx < value) {
                    icon.className = 'fas fa-star';
                    s.classList.add('btn-warning');
                    s.classList.remove('btn-outline-warning');
                } else {
                    icon.className = 'far fa-star';
                    s.classList.remove('btn-warning');
                    s.classList.add('btn-outline-warning');
                }
            });
        });
    });
</script>

<?php include 'footer.php'; ?>