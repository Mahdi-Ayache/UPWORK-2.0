<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');
include 'header.php';

$role = $_SESSION['role'];
$user_name = $_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Utilisateur';

function getFreelancerId($pdo, $project_id) {
    $stmt = $pdo->prepare("SELECT freelancer_id FROM applications WHERE project_id = ? AND status = 'accepted' LIMIT 1");
    $stmt->execute([$project_id]);
    $row = $stmt->fetch();
    return $row ? $row['freelancer_id'] : 0;
}

if ($role == 'client') {
    // Client dashboard
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $myProjects = $stmt->fetchAll();
    
    $appCount = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN projects p ON a.project_id = p.id WHERE p.client_id = ? AND a.status = 'pending'");
    $appCount->execute([$_SESSION['user_id']]);
    $pendingApps = $appCount->fetchColumn();
?>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="fw-bold mb-1">Bonjour, <?= htmlspecialchars($user_name) ?> 👋</h1>
            <p class="text-muted">Gérez vos projets et trouvez les meilleurs freelances.</p>
        </div>
        <a href="create_project.php" class="btn btn-primary-custom"><i class="fas fa-plus-circle me-2"></i>Publier un projet</a>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex justify-content-between"><div><h6 class="text-muted mb-1">Projets publiés</h6><h2 class="fw-bold mb-0"><?= count($myProjects) ?></h2></div><i class="fas fa-briefcase fa-2x text-success opacity-50"></i></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex justify-content-between"><div><h6 class="text-muted mb-1">Candidatures en attente</h6><h2 class="fw-bold mb-0"><?= $pendingApps ?></h2></div><i class="fas fa-users fa-2x text-success opacity-50"></i></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex justify-content-between"><div><h6 class="text-muted mb-1">Projets en cours</h6><h2 class="fw-bold mb-0"><?= count(array_filter($myProjects, fn($p) => $p['status'] == 'in_progress')) ?></h2></div><i class="fas fa-chart-line fa-2x text-success opacity-50"></i></div></div></div></div>
    </div>

    <h3 class="fw-semibold mb-3">Mes offres</h3>
    <?php if(count($myProjects) == 0): ?>
        <div class="card text-center py-5"><div class="card-body"><i class="fas fa-folder-open fa-3x text-muted mb-3"></i><p class="lead">Vous n'avez pas encore publié de projet.</p><a href="create_project.php" class="btn btn-primary-custom">Publier mon premier projet</a></div></div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach($myProjects as $proj): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title fw-semibold"><?= htmlspecialchars($proj['title']) ?></h5>
                            <span class="badge <?= $proj['status'] == 'open' ? 'bg-success' : ($proj['status'] == 'in_progress' ? 'bg-warning' : 'bg-secondary') ?>">
                                <?= $proj['status'] == 'open' ? 'Ouvert' : ($proj['status'] == 'in_progress' ? 'En cours' : 'Terminé') ?>
                            </span>
                        </div>
                        <p class="text-muted small">Budget: <?= number_format($proj['budget'],2) ?> €</p>
                        <p class="card-text small"><?= htmlspecialchars(substr($proj['description'], 0, 100)) ?>...</p>
                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <a href="applications.php?project_id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-primary">Candidatures</a>
                            <a href="edit_project.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-secondary">Modifier</a>
                            <a href="delete_project.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cette offre ?')">Supprimer</a>
                            <a href="recommended_freelancers.php?project_id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-primary">Recommandations IA</a>
                            <?php if ($proj['status'] == 'in_progress' && $proj['freelancer_ready'] == 1):
                                $freelancer_id = getFreelancerId($pdo, $proj['id']);
                                if ($freelancer_id):
                                    $paid = $pdo->prepare("SELECT id FROM payments WHERE project_id = ?");
                                    $paid->execute([$proj['id']]);
                                    if ($paid->rowCount() == 0):
                            ?>
                                <a href="make_payment.php?project_id=<?= $proj['id'] ?>&freelancer_id=<?= $freelancer_id ?>" class="btn btn-sm btn-success">Payer le freelancer</a>
                            <?php 
                                    endif;
                                endif;
                            endif; 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php } else { // Freelancer dashboard ?>

    <div class="mb-4">
        <h1 class="fw-bold mb-1">Bonjour, <?= htmlspecialchars($user_name) ?> 🚀</h1>
        <p class="text-muted">Trouvez des missions qui correspondent à vos compétences.</p>
    </div>

    <?php
    $filter_min = isset($_GET['min_budget']) ? (int)$_GET['min_budget'] : 0;
    $filter_max = isset($_GET['max_budget']) ? (int)$_GET['max_budget'] : 0;
    $filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

    $maxBudgetStmt = $pdo->query("SELECT MAX(budget) FROM projects WHERE status='open'");
    $maxBudget = $maxBudgetStmt->fetchColumn();
    if (!$maxBudget) $maxBudget = 5000;

    $sql = "SELECT p.*, c.name as category_name, p.client_id FROM projects p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status='open'";
    $params = [];
    if ($filter_min > 0) { $sql .= " AND p.budget >= ?"; $params[] = $filter_min; }
    if ($filter_max > 0 && $filter_max < $maxBudget) { $sql .= " AND p.budget <= ?"; $params[] = $filter_max; }
    if ($filter_category > 0) { $sql .= " AND p.category_id = ?"; $params[] = $filter_category; }
    $sql .= " ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();

    $stmtCat = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmtCat->fetchAll();
    ?>

    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="GET">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Budget (€)</label>
                    <div class="row g-2">
                        <div class="col-6"><input type="number" name="min_budget" class="form-control" placeholder="Min" value="<?= $filter_min ?>"></div>
                        <div class="col-6"><input type="number" name="max_budget" class="form-control" placeholder="Max" value="<?= $filter_max ?>"></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Catégorie</label>
                    <select name="category" class="form-select">
                        <option value="0">Toutes catégories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary-custom w-100">Filtrer</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary w-100 mt-2">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <h3 class="fw-semibold mb-3">Missions disponibles</h3>
    <?php if(count($projects) == 0): ?>
        <div class="card text-center py-5"><div class="card-body"><i class="fas fa-search fa-3x text-muted mb-3"></i><p class="lead">Aucune mission ne correspond à vos critères.</p></div></div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach($projects as $proj): 
                $savedStmt = $pdo->prepare("SELECT id FROM saved_projects WHERE user_id = ? AND project_id = ?");
                $savedStmt->execute([$_SESSION['user_id'], $proj['id']]);
                $isSaved = $savedStmt->rowCount() > 0;
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title fw-semibold"><?= htmlspecialchars($proj['title']) ?></h5>
                            <a href="save_project.php?id=<?= $proj['id'] ?>" class="text-<?= $isSaved ? 'danger' : 'secondary' ?>"><i class="fas fa-bookmark"></i></a>
                        </div>
                        <?php if ($proj['category_name']): ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary mb-2"><?= htmlspecialchars($proj['category_name']) ?></span>
                        <?php endif; ?>
                        <p class="text-muted small mt-2">Budget: <?= number_format($proj['budget'],2) ?> €</p>
                        <p class="card-text small"><?= htmlspecialchars(substr($proj['description'], 0, 100)) ?>...</p>
                        <div class="mt-3">
                            <a href="client_profile.php?id=<?= $proj['client_id'] ?>" class="btn btn-sm btn-outline-secondary me-2">Profil client</a>
                            <a href="apply_project.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-primary-custom">Postuler</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php } ?>

<?php include 'footer.php'; ?>