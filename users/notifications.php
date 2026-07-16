<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/db.php";

$user_id = $_SESSION['user_id'];

// Mark all as viewed on page load
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND user_role = 'user' AND is_read = 0");

// Fetch all notifications
$stmt = $conn->prepare(
    "SELECT id, title, message, link, is_read, created_at
     FROM notifications WHERE user_id = ? AND user_role = 'user'
     ORDER BY created_at DESC LIMIT 100"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$pageTitle = "Notifications";
include "../includes/header.php";
?>

<div class="min-h-screen bg-slate-50">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-brand-600">Notifications</h1>
                <p class="text-sm text-gray-500 mt-1">Stay updated on your bookings and events</p>
            </div>
            <button id="clearAllBtn"
                class="text-sm text-brand-600 hover:text-brand-900 transition font-medium cursor-pointer">
                Clear all
            </button>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-400 mb-2">No notifications</h3>
                <p class="text-sm text-gray-400">You're all caught up!</p>
            </div>
        <?php else: ?>
            <div id="notifContainer" class="space-y-2">
                <?php foreach ($notifications as $n): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 hover:shadow-md transition cursor-pointer notif-item"
                        data-id="<?= $n['id'] ?>" data-link="<?= htmlspecialchars($n['link'] ?? '') ?>">
                        <div class="flex items-start gap-3">
                            <div class="mt-1 shrink-0">
                                <?php if ($n['is_read'] == 0): ?>
                                    <span class="block w-2.5 h-2.5 rounded-full bg-brand-600"></span>
                                <?php else: ?>
                                    <span class="block w-2.5 h-2.5 rounded-full bg-gray-200"></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-brand-900"><?= htmlspecialchars($n['title']) ?></p>
                                <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($n['message']) ?></p>
                                <p class="text-xs text-gray-400 mt-2">
                                    <?= date('M j, Y \a\t g:i A', strtotime($n['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Click notification to navigate
        document.querySelectorAll('.notif-item').forEach(el => {
            el.addEventListener('click', function () {
                const link = this.dataset.link;
                if (link) window.location.href = link;
            });
        });

        // Clear all notifications
        const clearBtn = document.getElementById('clearAllBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (confirm('Are you sure you want to clear all notifications?')) {
                    fetch('../api/notifications.php?action=delete_all', { method: 'POST' })
                        .then(response => {
                            if (response.ok) location.reload();
                        })
                        .catch(err => console.error('Clear all failed:', err));
                }
            });
        }
    });
</script>

<?php include "../includes/footer.php"; ?>