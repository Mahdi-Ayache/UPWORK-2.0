<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $country = $_POST['country'] ?? '';
    $name = $first_name . ' ' . $last_name;
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, name, email, password, role, country) VALUES (?,?,?,?,?,?,?)");
    try {
        $stmt->execute([$first_name, $last_name, $name, $email, $password, $role, $country]);
        $_SESSION['success'] = "Compte créé ! Connectez-vous.";
        redirect('login.php');
    } catch(PDOException $e) {
        $error = "Cet email est déjà utilisé.";
    }
}
include 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-5">
                <h2 class="text-center mb-4">Inscription</h2>
                <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prénom</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pays (optionnel)</label>
                        <input type="text" name="country" class="form-control" placeholder="Votre pays">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Je suis</label>
                        <select name="role" class="form-select" required>
                            <option value="client">Client</option>
                            <option value="freelancer">Freelancer</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary-custom w-100 py-2">S'inscrire</button>
                </form>
                <p class="mt-3 text-center small">Déjà un compte ? <a href="login.php">Connectez-vous</a></p>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>