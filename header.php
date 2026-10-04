<?php
if(!isset($_SESSION)) session_start();

// Fetch user avatar and unread counts
$avatar = '';
$unreadCount = 0;
$unreadMessages = 0;
$unreadDirectMessages = 0;
$notifications = [];
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $avatar = $stmt->fetchColumn();

        $role = $_SESSION['role'];
        $user_id = $_SESSION['user_id'];
        
        // Unread notifications (all types)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unreadCount = $stmt->fetchColumn();

        // Unread project messages (user is not the sender)
        if ($role == 'client') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages m 
                                   JOIN projects p ON m.project_id = p.id 
                                   WHERE p.client_id = ? AND m.sender_id != ?");
            $stmt->execute([$user_id, $user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages m 
                                   JOIN applications a ON m.project_id = a.project_id 
                                   WHERE a.freelancer_id = ? AND a.status = 'accepted' AND m.sender_id != ?");
            $stmt->execute([$user_id, $user_id]);
        }
        $unreadMessages = $stmt->fetchColumn();

        // Unread direct messages
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM direct_messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unreadDirectMessages = $stmt->fetchColumn();

        // Fetch notifications (including those without payment)
        $stmt = $pdo->prepare("SELECT n.*, 
                                      p.amount, 
                                      pr.title as project_title 
                               FROM notifications n 
                               LEFT JOIN payments p ON n.payment_id = p.id 
                               LEFT JOIN projects pr ON p.project_id = pr.id 
                               WHERE n.user_id = ? 
                               ORDER BY n.created_at DESC 
                               LIMIT 20");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll();
    } catch (Exception $e) {
        // ignore
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upwork 2.0 – Plateforme Freelance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f9fafb; color: #111827; line-height: 1.5; }
        .navbar { background: white; border-bottom: 1px solid #e5e7eb; padding: 0.75rem 0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .navbar-brand { font-weight: 700; font-size: 1.5rem; color: #14a800 !important; }
        .navbar-brand i { color: #14a800; }
        .nav-link { color: #4b5563 !important; font-weight: 500; transition: color 0.2s; }
        .nav-link:hover { color: #14a800 !important; }
        .btn-primary-custom { background: #14a800; border: none; color: white; border-radius: 9999px; padding: 0.5rem 1.25rem; font-weight: 600; transition: background 0.2s; }
        .btn-primary-custom:hover { background: #0f8a00; }
        .btn-outline-custom { border: 1px solid #14a800; color: #14a800; background: transparent; border-radius: 9999px; padding: 0.5rem 1.25rem; font-weight: 500; transition: all 0.2s; }
        .btn-outline-custom:hover { background: #14a800; color: white; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .dropdown-menu { border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-radius: 12px; }
        .dropdown-item { padding: 0.5rem 1rem; font-size: 0.875rem; }
        .dropdown-item:hover { background: #f3f4f6; }
        .badge-notification { background: #ef4444; color: white; border-radius: 9999px; padding: 0.125rem 0.375rem; font-size: 0.7rem; font-weight: 600; margin-left: 0.25rem; }
        .card { border: 1px solid #e5e7eb; border-radius: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: box-shadow 0.2s; }
        .card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .alert { border-radius: 12px; border: none; }
        .offcanvas { border-left: 1px solid #e5e7eb; }
        .notification-item { border-bottom: 1px solid #f3f4f6; padding: 1rem 0; }
        .notification-item:last-child { border-bottom: none; }
        footer { background: white; border-top: 1px solid #e5e7eb; color: #6b7280; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-bolt me-1"></i> Upwork<span style="font-weight: 600;">2.0</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['role'] == 'freelancer'): ?>
                        <li class="nav-item"><a class="nav-link" href="saved_projects.php"><i class="fas fa-bookmark me-1"></i> Sauvegardés</a></li>
                        <li class="nav-item"><a class="nav-link" href="my_offers.php"><i class="fas fa-gift me-1"></i> Mes offres</a></li>
                        <li class="nav-item"><a class="nav-link" href="active_projects.php"><i class="fas fa-tasks me-1"></i> Projets en cours</a></li>
                        <li class="nav-item"><a class="nav-link" href="clients.php"><i class="fas fa-building me-1"></i> Clients</a></li>
                        <li class="nav-item"><a class="nav-link" href="recommendations.php"><i class="fas fa-robot me-1"></i> Recommandations IA</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-chart-line me-1"></i> Tableau de bord</a></li>
                    <?php if($_SESSION['role'] == 'client'): ?>
                        <li class="nav-item"><a class="nav-link" href="freelancers.php"><i class="fas fa-users me-1"></i> Freelancers</a></li>
                    <?php endif; ?>
                    <!-- Messages icon -->
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="conversations.php" id="messagesLink">
                            <i class="fas fa-comment-dots"></i>
                            <?php $totalUnread = $unreadMessages + $unreadDirectMessages; ?>
                            <?php if ($totalUnread > 0): ?>
                                <span class="badge-notification position-absolute top-0 start-100 translate-middle" id="messagesBadge"><?= $totalUnread ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <!-- Notifications bell -->
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="#" data-bs-toggle="offcanvas" data-bs-target="#notificationOffcanvas" id="notifBell">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge-notification position-absolute top-0 start-100 translate-middle" id="notifBadge"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if (!empty($avatar) && file_exists($avatar)): ?>
                                <img src="<?= $avatar ?>" class="user-avatar me-2" alt="avatar">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-lg me-1"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Utilisateur') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Mon profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Déconnexion</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Connexion</a></li>
                    <li class="nav-item"><a class="btn btn-primary-custom" href="register.php">Inscription</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container my-5">

<?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="offcanvas offcanvas-end" tabindex="-1" id="notificationOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Notifications</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <?php if (count($notifications) == 0): ?>
            <p class="text-muted text-center">Aucune notification.</p>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item">
                    <p class="mb-1 small"><?= htmlspecialchars($notif['message']) ?></p>
                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Mark messages as read when we navigate to conversations
    const messagesLink = document.getElementById('messagesLink');
    if (messagesLink) {
        messagesLink.addEventListener('click', function() {
            fetch('mark_messages_read.php', { method: 'POST' })
                .then(() => {
                    const badge = document.getElementById('messagesBadge');
                    if (badge) badge.style.display = 'none';
                });
        });
    }

    // Mark notifications as read when offcanvas is closed
    const offcanvasElement = document.getElementById('notificationOffcanvas');
    if (offcanvasElement) {
        offcanvasElement.addEventListener('hidden.bs.offcanvas', function () {
            fetch('mark_notifications_read.php', { method: 'POST' })
                .then(() => {
                    const badge = document.getElementById('notifBadge');
                    if (badge) badge.style.display = 'none';
                });
        });
    }
</script>