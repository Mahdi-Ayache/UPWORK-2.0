<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'freelancer') redirect('dashboard.php');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT u.* FROM users u WHERE u.role = 'client'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.country LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold">Clients</h1>
</div>

<!-- Search Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control" placeholder="Rechercher par nom, email ou pays..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary-custom w-100">Rechercher</button>
                <a href="clients.php" class="btn btn-outline-secondary w-100 mt-2">Réinitialiser</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php if (count($clients) == 0): ?>
        <div class="col-12">
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                    <p class="lead">Aucun client trouvé.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($clients as $c): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <?php if (!empty($c['avatar']) && file_exists($c['avatar'])): ?>
                                <img src="<?= htmlspecialchars($c['avatar']) ?>" class="rounded-circle" width="48" height="48" style="object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-3x text-muted"></i>
                            <?php endif; ?>
                            <div>
                                <h5 class="card-title fw-semibold"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></h5>
                                <?php if (!empty($c['country'])): ?>
                                    <p class="text-muted small mb-1"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($c['country']) ?></p>
                                <?php endif; ?>
                                <p class="text-muted small mb-0">Email: <?= htmlspecialchars($c['email']) ?></p>
                                <a href="client_profile.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary-custom mt-3">Voir profil</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>