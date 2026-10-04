<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 1. Update personal info
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_personal'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $name = $first_name . ' ' . $last_name;
    $country = $_POST['country'] ?? '';

    if ($role == 'freelancer') {
        $hourly_rate = $_POST['hourly_rate'];
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, name = ?, hourly_rate = ?, country = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $name, $hourly_rate, $country, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, name = ?, country = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $name, $country, $user_id]);
    }

    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $_SESSION['name'] = $name;

    $_SESSION['success'] = "Informations mises à jour.";
    redirect('profile.php');
}

// 2. Update password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $new_password = $_POST['new_password'];
    if (!empty($new_password)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        $_SESSION['success'] = "Mot de passe modifié.";
    } else {
        $_SESSION['error'] = "Veuillez saisir un mot de passe.";
    }
    redirect('profile.php');
}

// 3. Update bio
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_bio'])) {
    $bio = trim($_POST['bio']);
    $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
    $stmt->execute([$bio, $user_id]);
    $_SESSION['success'] = "Bio mise à jour.";
    redirect('profile.php');
}

// 4. Avatar upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['avatar'])) {
    $targetDir = "uploads/avatars/";
    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
    $fileName = time() . '_' . basename($_FILES["avatar"]["name"]);
    $targetFile = $targetDir . $fileName;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($imageFileType, $allowedTypes) && $_FILES["avatar"]["size"] < 5000000) {
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $targetFile)) {
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$targetFile, $user_id]);
            $_SESSION['success'] = "Avatar mis à jour.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'upload.";
        }
    } else {
        $_SESSION['error'] = "Format non supporté ou fichier trop grand (max 5MB).";
    }
    redirect('profile.php');
}

// 5. Delete account
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    $pdo->prepare("DELETE FROM payments WHERE payer_id = ? OR receiver_id = ?")->execute([$user_id, $user_id]);
    $pdo->prepare("DELETE FROM applications WHERE project_id IN (SELECT id FROM projects WHERE client_id = ?)")->execute([$user_id]);
    $pdo->prepare("DELETE FROM applications WHERE freelancer_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM projects WHERE client_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
    session_destroy();
    redirect('register.php');
}

// ---- Fetch user data ----
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_destroy();
    redirect('login.php');
}

// ---- Fetch skills (freelancer only) ----
$skills = [];
if ($role == 'freelancer') {
    $stmt = $pdo->prepare("SELECT * FROM skills WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $skills = $stmt->fetchAll();
}

// ---- Fetch followers & following counts and lists ----
$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$stmt->execute([$user_id]);
$followers_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$stmt->execute([$user_id]);
$following_count = $stmt->fetchColumn();

// First 5 followers
$stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.avatar 
                       FROM follows f 
                       JOIN users u ON f.follower_id = u.id 
                       WHERE f.following_id = ? 
                       LIMIT 5");
$stmt->execute([$user_id]);
$followers = $stmt->fetchAll();

// First 5 following
$stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.avatar 
                       FROM follows f 
                       JOIN users u ON f.following_id = u.id 
                       WHERE f.follower_id = ? 
                       LIMIT 5");
$stmt->execute([$user_id]);
$following = $stmt->fetchAll();

// ---- Fetch data for Work tab ----
if ($role == 'client') {
    // Client projects
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $projects = $stmt->fetchAll();

    // Payments made (for client)
    $stmt = $pdo->prepare("SELECT p.*, pr.title as project_title, u.name as freelancer_name, p.receiver_id
                           FROM payments p
                           JOIN projects pr ON p.project_id = pr.id
                           JOIN users u ON p.receiver_id = u.id
                           WHERE p.payer_id = ?
                           ORDER BY p.created_at DESC");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll();
} else {
    // Freelancer applications (including client_id)
    $stmt = $pdo->prepare("SELECT a.*, p.title as project_title, p.budget, p.status as project_status, p.client_id
                           FROM applications a
                           JOIN projects p ON a.project_id = p.id
                           WHERE a.freelancer_id = ?
                           ORDER BY a.created_at DESC");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll();

    // Payments received (for freelancer)
    $stmt = $pdo->prepare("SELECT p.*, pr.title as project_title, u.name as client_name
                           FROM payments p
                           JOIN projects pr ON p.project_id = pr.id
                           JOIN users u ON p.payer_id = u.id
                           WHERE p.receiver_id = ?
                           ORDER BY p.created_at DESC");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll();
}

include 'header.php';
?>

<div class="row">
    <div class="col-md-12">
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile">Profil</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#settings">Paramètres</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#work">
                    <?= $role == 'client' ? 'Mes projets & paiements' : 'Mes candidatures & paiements' ?>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Profil Tab -->
            <div class="tab-pane fade show active" id="profile">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($user['avatar']) ?>" class="rounded-circle img-fluid" style="width: 120px; height: 120px; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-5x text-muted"></i>
                                <?php endif; ?>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="file" name="avatar" accept="image/*" class="form-control form-control-sm mb-2">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Changer avatar</button>
                                </form>
                            </div>
                            <div class="col-md-9">
                                <h3 class="fw-semibold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                                <p class="text-muted"><?= $role == 'client' ? 'Client' : 'Freelancer' ?></p>
                                <?php if ($role == 'freelancer'): ?>
                                    <p><i class="fas fa-euro-sign me-2 text-success"></i>Tarif horaire: <strong><?= $user['hourly_rate'] ? number_format($user['hourly_rate'],2) . ' €/h' : 'Non défini' ?></strong></p>
                                <?php endif; ?>
                                <?php if (!empty($user['country'])): ?>
                                    <p><i class="fas fa-map-marker-alt me-2 text-muted"></i>Pays: <?= htmlspecialchars($user['country']) ?></p>
                                <?php endif; ?>

                                <!-- Skills (freelancer) -->
                                <?php if ($role == 'freelancer'): ?>
                                    <div class="mt-3">
                                        <h6 class="fw-semibold">Compétences</h6>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <?php if (count($skills) > 0): ?>
                                                <?php foreach ($skills as $s): ?>
                                                    <span class="badge bg-light text-dark border">
                                                        <?= htmlspecialchars($s['skill_name']) ?>
                                                        <a href="manage_skills.php?remove_skill=<?= $s['id'] ?>" class="text-danger ms-1"><i class="fas fa-times-circle"></i></a>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Aucune compétence</span>
                                            <?php endif; ?>
                                        </div>
                                        <form method="POST" action="manage_skills.php" class="mt-2">
                                            <div class="input-group" style="max-width: 280px;">
                                                <input type="text" name="skill" class="form-control form-control-sm" placeholder="Nouvelle compétence" required>
                                                <button type="submit" name="add_skill" class="btn btn-sm btn-primary-custom">Ajouter</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <!-- Bio -->
                                <div class="mt-3">
                                    <h6 class="fw-semibold">Bio</h6>
                                    <form method="POST">
                                        <textarea name="bio" class="form-control mb-2" rows="3" placeholder="Présentez-vous..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                                        <button type="submit" name="update_bio" class="btn btn-sm btn-primary-custom">Mettre à jour la bio</button>
                                    </form>
                                </div>

                                <!-- Followers & Following Section (visible for own profile) -->
                                <div class="card border-0 shadow-sm mt-4">
                                    <div class="card-body p-3">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="fw-semibold"><i class="fas fa-users me-2"></i>Abonnés (<?= $followers_count ?>)</h6>
                                                <?php if (count($followers) == 0): ?>
                                                    <p class="text-muted small">Aucun abonné pour le moment.</p>
                                                <?php else: ?>
                                                    <div class="d-flex flex-wrap gap-3">
                                                        <?php foreach ($followers as $f): ?>
                                                            <div class="text-center">
                                                                <?php if (!empty($f['avatar']) && file_exists($f['avatar'])): ?>
                                                                    <img src="<?= htmlspecialchars($f['avatar']) ?>" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                                                <?php else: ?>
                                                                    <i class="fas fa-user-circle fa-2x text-muted"></i>
                                                                <?php endif; ?>
                                                                <div class="small"><?= htmlspecialchars($f['first_name']) ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php if ($followers_count > 5): ?>
                                                        <a href="followers_list.php?id=<?= $user_id ?>&type=followers" class="btn btn-sm btn-link mt-2">Voir tous les abonnés</a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-semibold"><i class="fas fa-user-plus me-2"></i>Abonnements (<?= $following_count ?>)</h6>
                                                <?php if (count($following) == 0): ?>
                                                    <p class="text-muted small">Aucun abonnement.</p>
                                                <?php else: ?>
                                                    <div class="d-flex flex-wrap gap-3">
                                                        <?php foreach ($following as $f): ?>
                                                            <div class="text-center">
                                                                <?php if (!empty($f['avatar']) && file_exists($f['avatar'])): ?>
                                                                    <img src="<?= htmlspecialchars($f['avatar']) ?>" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                                                <?php else: ?>
                                                                    <i class="fas fa-user-circle fa-2x text-muted"></i>
                                                                <?php endif; ?>
                                                                <div class="small"><?= htmlspecialchars($f['first_name']) ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php if ($following_count > 5): ?>
                                                        <a href="followers_list.php?id=<?= $user_id ?>&type=following" class="btn btn-sm btn-link mt-2">Voir tous les abonnements</a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($role == 'freelancer'): ?>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT r.*, u.name as client_name, p.title as project_title 
                                                           FROM ratings r 
                                                           JOIN users u ON r.client_id = u.id 
                                                           JOIN projects p ON r.project_id = p.id 
                                                           WHERE r.freelancer_id = ? 
                                                           ORDER BY r.created_at DESC");
                                    $stmt->execute([$user_id]);
                                    $my_ratings = $stmt->fetchAll();
                                    $avg_rating = 0;
                                    if (count($my_ratings) > 0) {
                                        $total = array_sum(array_column($my_ratings, 'rating'));
                                        $avg_rating = round($total / count($my_ratings), 1);
                                    }
                                    ?>
                                    <div class="mt-4">
                                        <h5 class="fw-semibold">Mes évaluations</h5>
                                        <?php if (count($my_ratings) == 0): ?>
                                            <p class="text-muted">Aucune évaluation pour l'instant.</p>
                                        <?php else: ?>
                                            <div class="mb-2">
                                                <span class="text-warning">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i <= $avg_rating ? '' : '-o' ?>"></i>
                                                    <?php endfor; ?>
                                                </span>
                                                <strong><?= $avg_rating ?></strong> / 5 (<?= count($my_ratings) ?> avis)
                                            </div>
                                            <div class="list-group mt-3">
                                                <?php foreach ($my_ratings as $r): ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex justify-content-between">
                                                            <strong><?= htmlspecialchars($r['client_name']) ?></strong>
                                                            <small class="text-muted"><?= date('d/m/Y', strtotime($r['created_at'])) ?></small>
                                                        </div>
                                                        <div class="text-warning small mb-1">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="fas fa-star<?= $i <= $r['rating'] ? '' : '-o' ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <p class="mb-1"><?= nl2br(htmlspecialchars($r['review'])) ?></p>
                                                        <small class="text-muted">Projet: <?= htmlspecialchars($r['project_title']) ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paramètres Tab -->
            <div class="tab-pane fade" id="settings">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-semibold mb-3">Informations personnelles</h5>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small">Prénom</label>
                                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small">Nom</label>
                                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Email</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            </div>
                            <?php if ($role == 'freelancer'): ?>
                                <div class="mb-3">
                                    <label class="form-label small">Tarif horaire (€/h)</label>
                                    <input type="number" step="0.01" name="hourly_rate" class="form-control" value="<?= $user['hourly_rate'] ?? '' ?>">
                                </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label small">Pays</label>
                                <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($user['country'] ?? '') ?>">
                            </div>
                            <button type="submit" name="update_personal" class="btn btn-primary-custom">Mettre à jour</button>
                        </form>

                        <hr class="my-4">

                        <h5 class="fw-semibold mb-3">Changer le mot de passe</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <input type="password" name="new_password" class="form-control" placeholder="Nouveau mot de passe" minlength="6" required>
                            </div>
                            <button type="submit" name="update_password" class="btn btn-primary-custom">Modifier</button>
                        </form>

                        <hr class="my-4">

                        <h5 class="fw-semibold text-danger mb-3">Supprimer le compte</h5>
                        <p class="small text-muted">Cette action est irréversible. Toutes vos données seront effacées.</p>
                        <form method="POST" onsubmit="return confirm('Supprimer définitivement votre compte ?');">
                            <button type="submit" name="delete_account" class="btn btn-danger">Supprimer mon compte</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Work Tab -->
            <div class="tab-pane fade" id="work">
                <?php if ($role == 'client'): ?>
                    <!-- Client: My projects -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="fw-semibold mb-3">Mes projets publiés</h5>
                            <?php if (count($projects) == 0): ?>
                                <p class="text-muted">Aucun projet publié.</p>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($projects as $proj): ?>
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between">
                                                        <h6 class="fw-semibold"><?= htmlspecialchars($proj['title']) ?></h6>
                                                        <span class="badge <?= $proj['status'] == 'open' ? 'bg-success' : ($proj['status'] == 'in_progress' ? 'bg-warning' : 'bg-secondary') ?>">
                                                            <?= $proj['status'] == 'open' ? 'Ouvert' : ($proj['status'] == 'in_progress' ? 'En cours' : 'Terminé') ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-muted small">Budget: <?= number_format($proj['budget'],2) ?> €</p>
                                                    <p class="small"><?= htmlspecialchars(substr($proj['description'], 0, 100)) ?>...</p>
                                                    <?php if ($proj['status'] == 'in_progress'): ?>
                                                        <form method="POST" action="update_project_status.php">
                                                            <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                                                            <button type="submit" name="complete" class="btn btn-sm btn-success">Marquer terminé</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Client: Payments made -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="fw-semibold mb-3">Paiements effectués</h5>
                            <?php if (count($payments) == 0): ?>
                                <p class="text-muted">Aucun paiement effectué.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Projet</th><th>Freelance</th><th>Montant</th><th>Date</th><th>Profil</th><th>Reçu</th><th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $pay): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($pay['project_title']) ?></td>
                                                    <td><?= htmlspecialchars($pay['freelancer_name']) ?></td>
                                                    <td><?= number_format($pay['amount'],2) ?> €</td>
                                                    <td><?= date('d/m/Y', strtotime($pay['created_at'])) ?></td>
                                                    <td><a href="freelancer_profile.php?id=<?= $pay['receiver_id'] ?>" class="btn btn-sm btn-outline-secondary">Profil</a></td>
                                                    <td><a href="view_receipt.php?id=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-primary">Voir</a></td>
                                                    <td><a href="delete_payment.php?id=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ce paiement ?');"><i class="fas fa-trash"></i></a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Freelancer: My applications -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="fw-semibold mb-3">Mes candidatures</h5>
                            <?php if (count($applications) == 0): ?>
                                <p class="text-muted">Aucune candidature.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Projet</th><th>Budget</th><th>Statut</th><th>Client</th><th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($applications as $app): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($app['project_title']) ?></td>
                                                    <td><?= number_format($app['budget'],2) ?> €</td>
                                                    <td>
                                                        <span class="badge <?= $app['status'] == 'pending' ? 'bg-warning' : ($app['status'] == 'accepted' ? 'bg-success' : 'bg-danger') ?>">
                                                            <?= $app['status'] == 'pending' ? 'En attente' : ($app['status'] == 'accepted' ? 'Accepté' : 'Refusé') ?>
                                                        </span>
                                                    </td>
                                                    <td><a href="client_profile.php?id=<?= $app['client_id'] ?>" class="btn btn-sm btn-outline-secondary">Voir profil</a></td>
                                                    <td><?= date('d/m/Y', strtotime($app['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Freelancer: Payments received -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="fw-semibold mb-3">Paiements reçus</h5>
                            <?php if (count($payments) == 0): ?>
                                <p class="text-muted">Aucun paiement reçu.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Projet</th><th>Client</th><th>Montant</th><th>Date</th><th>Reçu</th><th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $pay): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($pay['project_title']) ?></td>
                                                    <td><?= htmlspecialchars($pay['client_name']) ?></td>
                                                    <td><?= number_format($pay['amount'],2) ?> €</td>
                                                    <td><?= date('d/m/Y', strtotime($pay['created_at'])) ?></td>
                                                    <td><a href="view_receipt.php?id=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-primary">Voir</a></td>
                                                    <td><a href="delete_payment.php?id=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ce paiement ?');"><i class="fas fa-trash"></i></a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>