<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id=? AND client_id=?");
$stmt->execute([$id, $_SESSION['user_id']]);
$proj = $stmt->fetch();
if (!$proj) redirect('dashboard.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $budget = $_POST['budget'];
    $upd = $pdo->prepare("UPDATE projects SET title=?, description=?, budget=? WHERE id=?");
    $upd->execute([$title, $desc, $budget, $id]);
    $_SESSION['success'] = "Projet modifié avec succès.";
    redirect('dashboard.php');
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="glass-card p-5">
            <h2 class="fw-bold mb-4"><i class="fas fa-edit me-2 text-primary"></i>Modifier le projet</h2>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Titre</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($proj['title']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="5" class="form-control" required><?= htmlspecialchars($proj['description']) ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label">Budget (€)</label>
                    <input type="number" step="0.01" name="budget" class="form-control" value="<?= $proj['budget'] ?>" required>
                </div>
                <button type="submit" class="btn btn-primary-glow">Enregistrer les modifications</button>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>