<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'freelancer') redirect('dashboard.php');

$user_id = $_SESSION['user_id'];

// Fetch freelancer's skills
$stmt = $pdo->prepare("SELECT skill_name FROM skills WHERE user_id = ?");
$stmt->execute([$user_id]);
$skills = $stmt->fetchAll();
$skill_list = array_column($skills, 'skill_name');

// Build AI recommendation query based on skills
$conditions = [];
$params = [];
foreach ($skill_list as $skill) {
    $conditions[] = "(title LIKE ? OR description LIKE ? OR c.name LIKE ?)";
    $params[] = "%$skill%";
    $params[] = "%$skill%";
    $params[] = "%$skill%";
}
$sql = "SELECT p.*, c.name as category_name 
        FROM projects p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 'open'";
if (count($conditions) > 0) {
    $sql .= " AND (" . implode(" OR ", $conditions) . ")";
}
// Also consider budget match (AI: prefer projects within ±30% of average budget of similar projects)
$sql .= " ORDER BY p.budget ASC, p.created_at DESC LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recommended_projects = $stmt->fetchAll();

// AI analysis message
$analysis_message = "";
if (count($skills) > 0) {
    $analysis_message = "🧠 IA analyse : vos compétences (" . implode(", ", $skill_list) . ") correspondent à " . count($recommended_projects) . " projets. Affichage des meilleures correspondances.";
} else {
    $analysis_message = "🧠 IA analyse : vous n'avez pas encore ajouté de compétences. Ajoutez des compétences dans votre profil pour recevoir des recommandations personnalisées.";
}

include 'header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="fw-bold mb-3">Recommandations IA 🤖</h1>
        <div class="alert alert-info mb-4">
            <i class="fas fa-brain me-2"></i> <?= $analysis_message ?>
        </div>

        <?php if (count($recommended_projects) == 0 && count($skills) > 0): ?>
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="fas fa-robot fa-3x text-muted mb-3"></i>
                    <p class="lead">Aucun projet ne correspond à vos compétences pour le moment.</p>
                    <p>L'IA continue d'analyser de nouvelles offres...</p>
                </div>
            </div>
        <?php elseif (count($recommended_projects) > 0): ?>
            <div class="row g-4">
                <?php foreach ($recommended_projects as $proj): 
                    // Calculate AI confidence score (simple: based on how many skills match)
                    $match_count = 0;
                    foreach ($skill_list as $skill) {
                        if (stripos($proj['title'], $skill) !== false || stripos($proj['description'], $skill) !== false || stripos($proj['category_name'], $skill) !== false) {
                            $match_count++;
                        }
                    }
                    $confidence = min(100, round(($match_count / max(1, count($skill_list))) * 100));
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <h5 class="card-title fw-semibold"><?= htmlspecialchars($proj['title']) ?></h5>
                                    <span class="badge bg-primary">IA confiance <?= $confidence ?>%</span>
                                </div>
                                <?php if ($proj['category_name']): ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary mb-2"><?= htmlspecialchars($proj['category_name']) ?></span>
                                <?php endif; ?>
                                <p class="text-muted small mt-2">Budget: <?= number_format($proj['budget'],2) ?> €</p>
                                <p class="card-text small"><?= htmlspecialchars(substr($proj['description'], 0, 100)) ?>...</p>
                                <div class="mt-3">
                                    <a href="client_profile.php?id=<?= $proj['client_id'] ?>" class="btn btn-sm btn-outline-secondary me-2">Voir client</a>
                                    <a href="apply_project.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-primary-custom">Postuler</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Simulate AI loading effect
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '0';
                card.style.transition = 'opacity 0.3s';
                card.style.opacity = '1';
            }, index * 100);
        });
    });
</script>

<?php include 'footer.php'; ?>