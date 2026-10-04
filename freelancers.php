<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_hourly_min = isset($_GET['hourly_min']) ? (int)$_GET['hourly_min'] : 0;
$filter_hourly_max = isset($_GET['hourly_max']) ? (int)$_GET['hourly_max'] : 0;
$filter_skill = isset($_GET['skill']) ? trim($_GET['skill']) : '';
$filter_country = isset($_GET['country']) ? trim($_GET['country']) : '';

// Build query
$sql = "SELECT u.* FROM users u WHERE u.role = 'freelancer'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.country LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}
if ($filter_hourly_min > 0) {
    $sql .= " AND u.hourly_rate >= ?";
    $params[] = $filter_hourly_min;
}
if ($filter_hourly_max > 0) {
    $sql .= " AND u.hourly_rate <= ?";
    $params[] = $filter_hourly_max;
}
if (!empty($filter_skill)) {
    $sql .= " AND u.id IN (SELECT user_id FROM skills WHERE skill_name LIKE ?)";
    $params[] = "%$filter_skill%";
}
if (!empty($filter_country)) {
    $sql .= " AND u.country LIKE ?";
    $params[] = "%$filter_country%";
}

$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$freelancers = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold">Freelancers</h1>
</div>

<!-- Search + Filter Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-12">
                <label class="form-label small">Recherche générale</label>
                <input type="text" name="search" class="form-control" placeholder="Nom, email, pays..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Tarif horaire (min)</label>
                <input type="number" name="hourly_min" class="form-control" placeholder="€" value="<?= $filter_hourly_min ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Tarif horaire (max)</label>
                <input type="number" name="hourly_max" class="form-control" placeholder="€" value="<?= $filter_hourly_max ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Compétence</label>
                <input type="text" name="skill" class="form-control" placeholder="ex: PHP" value="<?= htmlspecialchars($filter_skill) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Pays</label>
                <input type="text" name="country" class="form-control" placeholder="Pays" value="<?= htmlspecialchars($filter_country) ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary-custom">Filtrer</button>
                <a href="freelancers.php" class="btn btn-outline-secondary ms-2">Réinitialiser</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php if (count($freelancers) == 0): ?>
        <div class="col-12">
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                    <p class="lead">Aucun freelancer trouvé.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($freelancers as $f): 
            $skill_stmt = $pdo->prepare("SELECT skill_name FROM skills WHERE user_id = ?");
            $skill_stmt->execute([$f['id']]);
            $skills = $skill_stmt->fetchAll();
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
                                <p class="text-muted small mb-1">Tarif horaire: <?= $f['hourly_rate'] ? number_format($f['hourly_rate'],2) . ' €/h' : 'Non défini' ?></p>
                                <?php if (count($skills) > 0): ?>
                                    <div class="mt-2">
                                        <?php foreach ($skills as $s): ?>
                                            <span class="badge bg-light text-dark me-1"><?= htmlspecialchars($s['skill_name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <a href="freelancer_profile.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-secondary me-2">Voir profil</a>
                                    <a href="make_offer.php?freelancer_id=<?= $f['id'] ?>" class="btn btn-sm btn-primary-custom">Faire une offre</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>