<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'client') redirect('dashboard.php');

$stmtCat = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmtCat->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $category_id = $_POST['category_id'] ?: null;
    $desc = $_POST['description'];
    $budget = $_POST['budget'];
    
    // Insert project
    $stmt = $pdo->prepare("INSERT INTO projects (client_id, title, category_id, description, budget, status) VALUES (?,?,?,?,?,'open')");
    $stmt->execute([$_SESSION['user_id'], $title, $category_id, $desc, $budget]);
    
    // Notify ONLY freelancers who follow this client
    $stmt = $pdo->prepare("SELECT u.id FROM follows f JOIN users u ON f.follower_id = u.id WHERE f.following_id = ? AND u.role = 'freelancer'");
    $stmt->execute([$_SESSION['user_id']]);
    $followers = $stmt->fetchAll();
    $message = "Un nouveau projet '{$title}' a été publié par " . $_SESSION['first_name'] . " " . $_SESSION['last_name'];
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, payment_id, is_read) VALUES (?, ?, 'new_project', NULL, 0)");
    foreach ($followers as $follower) {
        $stmt->execute([$follower['id'], $message]);
    }
    
    $_SESSION['success'] = "Votre projet a été publié avec succès !";
    redirect('dashboard.php');
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="fw-semibold mb-4"><i class="fas fa-plus-circle me-2 text-success"></i>Publier une mission</h2>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Titre du projet</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catégorie</label>
                        <select name="category_id" class="form-select">
                            <option value="">-- Sélectionner une catégorie --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="5" class="form-control" required></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Budget (€)</label>
                        <input type="number" step="0.01" name="budget" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary-custom w-100 py-2">Publier <i class="fas fa-globe ms-2"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>