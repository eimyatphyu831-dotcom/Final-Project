<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';

$admin_id = $_SESSION['user_id'];

// Mark all as read on page load
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $admin_id AND is_read = 0");

// Fetch all notifications
$stmt = $conn->prepare(
    "SELECT id, title, message, link, is_read, created_at
     FROM notifications WHERE user_id = ?
     ORDER BY created_at DESC LIMIT 100"
);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Notifications</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Playfair Display', serif;
        }

        .bg-sidebar {
            background-color: #ffffff;
        }

        .bg-sidebar-active {
            background-color: #C3B1E1;
            color: #ffffff;
        }

        .bg-purple-brand {
            background-color: #C3B1E1;
        }

        .text-purple-brand {
            color: #9966cc;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen overflow-hidden">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>
        <div class="flex-1 flex flex-col ml-64">
            <?php include 'admin_header.php'; ?>
            <main class="flex-1 p-8 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <!-- <h2 class="text-2xl font-bold text-gray-800">Notifications</h2> -->
                    <button id="clearAllBtn"
                        class="text-sm text-purple-600 hover:text-purple-800 font-medium cursor-pointer">
                        Clear all
                    </button>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
                        <i class="fa-regular fa-bell text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-400 mb-2">No notifications</h3>
                        <p class="text-sm text-gray-400">You're all caught up!</p>
                    </div>
                <?php else: ?>
                    <div id="notifContainer" class="space-y-2">
                        <?php foreach ($notifications as $n): ?>
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition cursor-pointer notif-item"
                                data-id="<?= $n['id'] ?>" data-link="<?= htmlspecialchars($n['link'] ?? '') ?>">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full <?= $n['is_read'] == 0 ? 'bg-purple-100 text-purple-600' : 'bg-gray-100 text-gray-400' ?> flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <i class="fa-solid fa-bell text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($n['title']) ?>
                                            </p>
                                            <?php if ($n['is_read'] == 0): ?>
                                                <span class="w-2 h-2 rounded-full bg-purple-brand"></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($n['message']) ?></p>
                                        <p class="text-xs text-gray-400 mt-2">
                                            <?= date('M j, Y \a\t g:i A', strtotime($n['created_at'])) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.notif-item').forEach(el => {
                el.addEventListener('click', function () {
                    const link = this.dataset.link;
                    if (link) window.location.href = link;
                });
            });

            const clearBtn = document.getElementById('clearAllBtn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (confirm('Are you sure you want to clear all notifications?')) {
                        fetch('../api/admin_notifications.php?action=delete_all', { method: 'POST' })
                            .then(response => {
                                if (response.ok) location.reload();
                            })
                            .catch(err => console.error('Clear all failed:', err));
                    }
                });
            }
        });
    </script>
</body>

</html>