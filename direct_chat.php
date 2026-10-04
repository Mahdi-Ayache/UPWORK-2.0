<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$other_user_id) redirect('dashboard.php');

$user_id = $_SESSION['user_id'];

// Handle message deletion
if (isset($_GET['delete_msg'])) {
    $msg_id = (int)$_GET['delete_msg'];
    $stmt = $pdo->prepare("DELETE FROM direct_messages WHERE id = ? AND sender_id = ?");
    $stmt->execute([$msg_id, $user_id]);
    redirect("direct_chat.php?user_id=$other_user_id");
}

// Fetch other user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$other_user_id]);
$other_user = $stmt->fetch();
if (!$other_user) {
    $_SESSION['error'] = "Utilisateur introuvable.";
    redirect('dashboard.php');
}

// Send message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO direct_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $other_user_id, $message]);
    }
    redirect("direct_chat.php?user_id=$other_user_id");
}

// Mark messages as read
$pdo->prepare("UPDATE direct_messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?")->execute([$user_id, $other_user_id]);

// Fetch messages
$stmt = $pdo->prepare("SELECT m.*, u.name as sender_name, u.avatar 
                       FROM direct_messages m 
                       JOIN users u ON m.sender_id = u.id 
                       WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                       ORDER BY m.created_at ASC");
$stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);
$messages = $stmt->fetchAll();

include 'header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($other_user['avatar']) && file_exists($other_user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($other_user['avatar']) ?>" class="rounded-circle" width="48" height="48" style="object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-user-circle fa-3x text-muted"></i>
                    <?php endif; ?>
                    <h3 class="fw-semibold">Discussion avec <?= htmlspecialchars($other_user['first_name'] . ' ' . $other_user['last_name']) ?></h3>
                </div>
                <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#videoCallModal">
                    <i class="fas fa-video me-1"></i> Appel vidéo
                </button>
            </div>
            <div class="card-body">
                <div id="messages-container" style="height: 400px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 16px; padding: 15px; background: #f9fafb;">
                    <?php foreach ($messages as $msg): ?>
                        <?php $is_mine = ($msg['sender_id'] == $user_id); ?>
                        <div class="mb-3 <?= $is_mine ? 'text-end' : 'text-start' ?>">
                            <div class="d-inline-block p-2 rounded-3 <?= $is_mine ? 'bg-primary text-white' : 'bg-white border' ?>" style="max-width: 80%;">
                                <small class="d-block fw-semibold <?= $is_mine ? 'text-white' : 'text-muted' ?>">
                                    <?= htmlspecialchars($msg['sender_name']) ?>
                                </small>
                                <p class="mb-0 small"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="<?= $is_mine ? 'text-white-50' : 'text-muted' ?>" style="font-size: 0.7rem;">
                                        <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                                    </small>
                                    <?php if ($is_mine): ?>
                                        <a href="direct_chat.php?user_id=<?= $other_user_id ?>&delete_msg=<?= $msg['id'] ?>" class="text-danger ms-2" style="font-size: 0.7rem;" onclick="return confirm('Supprimer ce message ?')"><i class="fas fa-trash-alt"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" class="mt-3">
                    <div class="input-group">
                        <input type="text" name="message" class="form-control" placeholder="Écrivez votre message..." required>
                        <button type="submit" class="btn btn-primary-custom">Envoyer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Video Call Modal with Profile Picture -->
<div class="modal fade" id="videoCallModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appel vidéo avec <?= htmlspecialchars($other_user['first_name'] . ' ' . $other_user['last_name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Remote user area (profile picture) -->
                    <div class="col-md-8" style="background: #1a1a2e; position: relative;">
                        <div id="remoteContainer" style="height: 400px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #0f0f1a;">
                            <div id="remoteAvatar" class="text-center text-white">
                                <?php if (!empty($other_user['avatar']) && file_exists($other_user['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($other_user['avatar']) ?>" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #14a800;">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-6x text-muted"></i>
                                <?php endif; ?>
                                <h4 class="mt-3"><?= htmlspecialchars($other_user['first_name'] . ' ' . $other_user['last_name']) ?></h4>
                                <div id="remoteStatus" class="mt-2">
                                    <span class="badge bg-secondary">🟡 Connexion...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Local video area (self-view) -->
                    <div class="col-md-4" style="background: #0a0a14; padding: 15px;">
                        <div class="position-relative">
                            <video id="localVideo" autoplay muted playsinline style="width: 100%; border-radius: 16px; background: #000;"></video>
                            <div class="position-absolute bottom-0 start-0 w-100 text-center text-white small p-1 bg-dark bg-opacity-50 rounded-bottom">Vous</div>
                        </div>
                        <!-- Call controls -->
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <button id="toggleMicBtn" class="btn btn-secondary rounded-circle" style="width: 48px; height: 48px;"><i class="fas fa-microphone"></i></button>
                            <button id="toggleCamBtn" class="btn btn-secondary rounded-circle" style="width: 48px; height: 48px;"><i class="fas fa-video"></i></button>
                            <button id="flipCamBtn" class="btn btn-secondary rounded-circle" style="width: 48px; height: 48px;"><i class="fas fa-sync-alt"></i></button>
                            <button id="shareScreenBtn" class="btn btn-secondary rounded-circle" style="width: 48px; height: 48px;"><i class="fas fa-desktop"></i></button>
                            <button id="endCallBtn" class="btn btn-danger rounded-circle" style="width: 48px; height: 48px;"><i class="fas fa-phone-slash"></i></button>
                        </div>
                        <div class="text-center text-white mt-3">
                            <span id="callTimer">00:00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-scroll messages
    const container = document.getElementById('messages-container');
    function scrollToBottom() { container.scrollTop = container.scrollHeight; }
    scrollToBottom();
    setInterval(() => {
        fetch(window.location.href).then(r => r.text()).then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContainer = doc.getElementById('messages-container');
            if (newContainer) container.innerHTML = newContainer.innerHTML;
            scrollToBottom();
        });
    }, 5000);

    // Video call logic
    let localStream = null;
    let currentFacingMode = 'user';
    let callTimerInterval = null;
    let callSeconds = 0;
    let isMicMuted = false;
    let isCamOff = false;
    let isScreenSharing = false;
    let originalTracks = [];

    const localVideo = document.getElementById('localVideo');
    const remoteStatusSpan = document.getElementById('remoteStatus');
    const toggleMicBtn = document.getElementById('toggleMicBtn');
    const toggleCamBtn = document.getElementById('toggleCamBtn');
    const flipCamBtn = document.getElementById('flipCamBtn');
    const shareScreenBtn = document.getElementById('shareScreenBtn');
    const endCallBtn = document.getElementById('endCallBtn');
    const callTimerSpan = document.getElementById('callTimer');

    async function startVideoCall() {
        try {
            remoteStatusSpan.innerHTML = '<span class="badge bg-secondary">🟡 Connexion...</span>';
            callSeconds = 0;
            updateTimerDisplay();
            if (callTimerInterval) clearInterval(callTimerInterval);
            callTimerInterval = setInterval(() => { callSeconds++; updateTimerDisplay(); }, 1000);

            const constraints = { video: { facingMode: currentFacingMode }, audio: true };
            localStream = await navigator.mediaDevices.getUserMedia(constraints);
            localVideo.srcObject = localStream;

            setTimeout(() => {
                remoteStatusSpan.innerHTML = '<span class="badge bg-success">🟢 En ligne</span>';
            }, 3000);
        } catch (err) {
            console.error(err);
            remoteStatusSpan.innerHTML = '<span class="badge bg-danger">🔴 Erreur caméra</span>';
        }
    }

    function updateTimerDisplay() {
        const mins = Math.floor(callSeconds / 60);
        const secs = callSeconds % 60;
        callTimerSpan.innerText = `${mins.toString().padStart(2,'0')}:${secs.toString().padStart(2,'0')}`;
    }

    function endVideoCall() {
        if (callTimerInterval) clearInterval(callTimerInterval);
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
            localStream = null;
        }
        localVideo.srcObject = null;
        const modal = bootstrap.Modal.getInstance(document.getElementById('videoCallModal'));
        if (modal) modal.hide();
    }

    async function toggleMic() {
        if (!localStream) return;
        const audioTracks = localStream.getAudioTracks();
        if (audioTracks.length) {
            audioTracks[0].enabled = !audioTracks[0].enabled;
            isMicMuted = !audioTracks[0].enabled;
            toggleMicBtn.innerHTML = isMicMuted ? '<i class="fas fa-microphone-slash"></i>' : '<i class="fas fa-microphone"></i>';
            toggleMicBtn.classList.toggle('btn-secondary', !isMicMuted);
            toggleMicBtn.classList.toggle('btn-warning', isMicMuted);
        }
    }

    async function toggleCam() {
        if (!localStream) return;
        const videoTracks = localStream.getVideoTracks();
        if (videoTracks.length) {
            videoTracks[0].enabled = !videoTracks[0].enabled;
            isCamOff = !videoTracks[0].enabled;
            toggleCamBtn.innerHTML = isCamOff ? '<i class="fas fa-video-slash"></i>' : '<i class="fas fa-video"></i>';
            toggleCamBtn.classList.toggle('btn-secondary', !isCamOff);
            toggleCamBtn.classList.toggle('btn-warning', isCamOff);
        }
    }

    async function flipCamera() {
        if (!localStream) return;
        localStream.getTracks().forEach(track => track.stop());
        currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
        const constraints = { video: { facingMode: { exact: currentFacingMode } }, audio: true };
        try {
            localStream = await navigator.mediaDevices.getUserMedia(constraints);
            localVideo.srcObject = localStream;
        } catch (err) {
            const fallbackConstraints = { video: true, audio: true };
            localStream = await navigator.mediaDevices.getUserMedia(fallbackConstraints);
            localVideo.srcObject = localStream;
        }
    }

    async function shareScreen() {
        if (isScreenSharing) {
            if (originalTracks.length) {
                const newStream = new MediaStream();
                originalTracks.forEach(t => newStream.addTrack(t));
                localStream = newStream;
                localVideo.srcObject = localStream;
                originalTracks = [];
                shareScreenBtn.classList.remove('btn-success');
                shareScreenBtn.classList.add('btn-secondary');
                isScreenSharing = false;
            }
            return;
        }
        try {
            const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
            const videoTrack = screenStream.getVideoTracks()[0];
            if (videoTrack) {
                const originalVideoTrack = localStream.getVideoTracks()[0];
                originalTracks = [originalVideoTrack];
                localStream.removeTrack(originalVideoTrack);
                localStream.addTrack(videoTrack);
                localVideo.srcObject = localStream;
                shareScreenBtn.classList.remove('btn-secondary');
                shareScreenBtn.classList.add('btn-success');
                isScreenSharing = true;
                videoTrack.onended = () => shareScreen();
            }
        } catch (err) {}
    }

    document.getElementById('videoCallModal').addEventListener('show.bs.modal', startVideoCall);
    toggleMicBtn.addEventListener('click', toggleMic);
    toggleCamBtn.addEventListener('click', toggleCam);
    flipCamBtn.addEventListener('click', flipCamera);
    shareScreenBtn.addEventListener('click', shareScreen);
    endCallBtn.addEventListener('click', endVideoCall);
</script>

<?php include 'footer.php'; ?>