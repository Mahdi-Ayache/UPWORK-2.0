<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!$project_id) redirect('dashboard.php');

// Fetch project details
$stmt = $pdo->prepare("SELECT title, description, budget, category_id FROM projects WHERE id = ? AND client_id = ?");
$stmt->execute([$project_id, $_SESSION['user_id']]);
$project = $stmt->fetch();
if (!$project) {
    $_SESSION['error'] = "Projet introuvable.";
    redirect('dashboard.php');
}

// Fetch all freelancers with their skills
$stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.avatar, u.hourly_rate, 
                              (SELECT AVG(rating) FROM ratings WHERE freelancer_id = u.id) as avg_rating,
                              (SELECT GROUP_CONCAT(skill_name) FROM skills WHERE user_id = u.id) as skills
                       FROM users u
                       WHERE u.role = 'freelancer'");
$stmt->execute();
$freelancers = $stmt->fetchAll();

// AI scoring: match project title/description with freelancer skills
$scored = [];
foreach ($freelancers as $f) {
    $score = 0;
    $skill_text = $f['skills'] ? strtolower($f['skills']) : '';
    $project_text = strtolower($project['title'] . ' ' . $project['description']);
    $words = explode(' ', $project_text);
    foreach ($words as $word) {
        if (strlen($word) > 2 && strpos($skill_text, $word) !== false) {
            $score += 10;
        }
    }
    // Budget match: prefer freelancers with hourly rate within 30% of project budget/estimated hours
    $hourly = $f['hourly_rate'] ?: 0;
    if ($hourly > 0 && $project['budget'] > 0) {
        $ratio = $hourly / ($project['budget'] / 40); // assume 40h project
        if ($ratio <= 1.3 && $ratio >= 0.7) $score += 20;
    }
    // Rating boost
    $avg_rating = $f['avg_rating'] ?: 0;
    $score += $avg_rating * 5;
    
    if ($score > 0) {
        $scored[] = ['freelancer' => $f, 'score' => $score];
    }
}
usort($scored, fn($a, $b) => $b['score'] - $a['score']);
$top_freelancers = array_slice($scored, 0, 10);

$analysis_message = "🧠 IA analyse : recommandation de freelancers pour le projet '{$project['title']}'. Basé sur les compétences, tarifs et évaluations.";

include 'header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="fw-bold mb-3">Recommandations IA 🤖 – <?= htmlspecialchars($project['title']) ?></h1>
        <div class="alert alert-info mb-4">
            <i class="fas fa-brain me-2"></i> <?= $analysis_message ?>
        </div>

        <?php if (count($top_freelancers) == 0): ?>
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="fas fa-robot fa-3x text-muted mb-3"></i>
                    <p class="lead">Aucun freelancer recommandé pour ce projet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($top_freelancers as $item): 
                    $f = $item['freelancer'];
                    $score = $item['score'];
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start gap-3">
                                    <?php if (!empty($f['avatar']) && file_exists($f['avatar'])): ?>
                                        <img src="<?= htmlspecialchars($f['avatar']) ?>" class="rounded-circle" width="48" height="48" style="object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-3x text-muted"></i>
                                    <?php endif; ?>
                                    <div>
                                        <h5 class="card-title fw-semibold"><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></h5>
                                        <p class="text-muted small">Tarif horaire: <?= $f['hourly_rate'] ? number_format($f['hourly_rate'],2) . ' €/h' : 'Non défini' ?></p>
                                        <?php if ($f['avg_rating']): ?>
                                            <div class="text-warning small">
                                                <?php $avg = round($f['avg_rating'], 1); ?>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?= $i <= $avg ? '' : '-o' ?>"></i>
                                                <?php endfor; ?>
                                                (<?= $avg ?>)
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <span class="badge bg-primary">Score IA: <?= round($score) ?></span>
                                        </div>
                                        <div class="mt-3">
                                            <a href="freelancer_profile.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-secondary">Voir profil</a>
                                            <a href="make_offer.php?freelancer_id=<?= $f['id'] ?>" class="btn btn-sm btn-primary-custom">Faire une offre</a>
                                        </div>
                                    </div>
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