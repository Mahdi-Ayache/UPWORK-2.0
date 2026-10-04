<?php
require_once 'config.php';
include 'header.php';

// Fetch featured projects for display
$stmt = $pdo->prepare("SELECT id, title, budget FROM projects WHERE status = 'open' ORDER BY RAND() LIMIT 6");
$stmt->execute();
$featuredProjects = $stmt->fetchAll();
?>

<!-- Hero Section -->
<div class="row align-items-center py-5">
    <div class="col-lg-6 mb-4 mb-lg-0">
        <h1 class="display-4 fw-bold mb-4">Trouvez les meilleurs talents <span style="color: #14a800;">en toute confiance</span></h1>
        <p class="lead text-muted mb-4">Upwork 2.0 connecte clients et freelances avec une plateforme simple, sécurisée et efficace. Publiez vos projets, postulez aux missions, et travaillez en toute tranquillité.</p>
        <div class="d-flex gap-3">
            <a href="register.php" class="btn btn-primary-custom btn-lg px-4">Commencer gratuitement</a>
            <a href="#features" class="btn btn-outline-custom btn-lg px-4">Découvrir</a>
        </div>
        <div class="mt-4 d-flex gap-4">
            <div><i class="fas fa-check-circle text-success me-1"></i> <span class="small">Paiement sécurisé</span></div>
            <div><i class="fas fa-headset text-success me-1"></i> <span class="small">Support 24/7</span></div>
            <div><i class="fas fa-shield-alt text-success me-1"></i> <span class="small">Données protégées</span></div>
        </div>
    </div>
    <div class="col-lg-6 text-center">
        <img src="https://placehold.co/500x350/eef2ff/14a800?text=Upwork+2.0" alt="Upwork 2.0" class="img-fluid rounded-3 shadow-sm">
    </div>
</div>

<!-- Featured Projects -->
<?php if (count($featuredProjects) > 0): ?>
<div class="row mt-5 pt-4">
    <div class="col-12">
        <h2 class="fw-bold mb-3">Projets en vedette</h2>
        <p class="text-muted mb-4">Découvrez des missions récentes publiées par des clients</p>
        <div class="row g-4">
            <?php foreach ($featuredProjects as $proj): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-semibold"><?= htmlspecialchars($proj['title']) ?></h5>
                                <span class="badge bg-success bg-opacity-10 text-success">Disponible</span>
                            </div>
                            <p class="card-text text-muted small">Budget: <?= number_format($proj['budget'], 0) ?> €</p>
                            <a href="<?= isLoggedIn() ? 'dashboard.php' : 'login.php' ?>" class="btn btn-sm btn-outline-custom mt-2">Voir la mission</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Features -->
<div id="features" class="row text-center py-5 mt-4">
    <div class="col-12 mb-4">
        <h2 class="fw-bold">Pourquoi choisir Upwork 2.0 ?</h2>
        <p class="text-muted">Une plateforme conçue pour faciliter la collaboration</p>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <i class="fas fa-robot fa-3x text-success mb-3"></i>
                <h5 class="fw-semibold">Recommandation IA</h5>
                <p class="text-muted small">Trouvez les meilleurs profils ou missions grâce à notre algorithme intelligent.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <i class="fas fa-lock fa-3x text-success mb-3"></i>
                <h5 class="fw-semibold">Paiement sécurisé</h5>
                <p class="text-muted small">Plusieurs méthodes de paiement, coupons et reçus détaillés.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <i class="fas fa-comments fa-3x text-success mb-3"></i>
                <h5 class="fw-semibold">Communication intégrée</h5>
                <p class="text-muted small">Messagerie instantanée et suivi de projet en temps réel.</p>
            </div>
        </div>
    </div>
</div>

<!-- CTA -->
<div class="row my-5">
    <div class="col-12 text-center">
        <div class="bg-light rounded-4 p-5">
            <h2 class="fw-bold">Prêt à démarrer ?</h2>
            <p class="text-muted mb-4">Rejoignez des milliers de professionnels sur Upwork 2.0</p>
            <a href="register.php" class="btn btn-primary-custom btn-lg px-5">Créer un compte gratuit</a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>