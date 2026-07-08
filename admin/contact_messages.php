<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$message = '';
$error = '';
$search = $_GET['search'] ?? '';

// --- MARK AS READ ---
if (isset($_GET['read'])) {
    $id = (int) $_GET['read'];
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: contact_messages.php" . ($search ? "?search=$search" : ''));
    exit();
}

// --- MARK AS UNREAD ---
if (isset($_GET['unread'])) {
    $id = (int) $_GET['unread'];
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE contact_messages SET is_read=0 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: contact_messages.php" . ($search ? "?search=$search" : ''));
    exit();
}

// --- DELETE ---
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: contact_messages.php" . ($search ? "?search=$search" : ''));
    exit();
}

// --- VIEW ---
$viewMessage = null;
$viewId = $_GET['view'] ?? 0;
if ($viewId) {
    $stmt = $conn->prepare("SELECT id, name, email, event_type, message, is_read, created_at FROM contact_messages WHERE id=?");
    $stmt->bind_param("i", $viewId);
    $stmt->execute();
    $viewMessage = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// --- FETCH MESSAGES ---
if ($search) {
    $like = "%$search%";
    $stmt = $conn->prepare("SELECT id, name, email, event_type, message, is_read, created_at FROM contact_messages WHERE name LIKE ? OR email LIKE ? OR message LIKE ? ORDER BY created_at DESC");
    $stmt->bind_param("sss", $like, $like, $like);
} else {
    $stmt = $conn->prepare("SELECT id, name, email, event_type, message, is_read, created_at FROM contact_messages ORDER BY created_at DESC");
}

$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Contact Messages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .text-purple-brand {
            color: #9966cc;
        }

        .bg-purple-brand {
            background-color: #C3B1E1;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen overflow-hidden">

    <div class="flex h-screen">

        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col ml-64">

            <?php include 'admin_header.php'; ?>

            <main class="flex-1 p-8 overflow-y-auto">

                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Contact Messages</h2>

                    <form method="GET" class="flex gap-2">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search name, email or message..." class="px-4 py-2 border rounded-xl w-64">

                        <button class="bg-purple-600 text-white px-4 py-2 rounded-xl">
                            Search
                        </button>
                    </form>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-100 text-green-700 px-4 py-3 rounded-xl text-sm mb-4 border border-green-200">
                        <?= $message ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-100 text-red-700 px-4 py-3 rounded-xl text-sm mb-4 border border-red-200">
                        <?= $error ?></div>
                <?php endif; ?>

                <?php if ($viewMessage): ?>
                    <div id="viewModal"
                        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 py-10">
                        <div
                            class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 w-full max-w-2xl mx-4 relative">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Message Details</h3>
                                <a href="contact_messages.php<?= $search ? "?search=$search" : '' ?>"
                                    class="text-gray-400 hover:text-gray-600 text-xl"><i class="fa-solid fa-xmark"></i></a>
                            </div>

                            <div class="flex items-center gap-4 pb-4 border-b border-gray-100 mb-4">
                                <div class="w-14 h-14 rounded-full bg-purple-100 flex items-center justify-center">
                                    <i class="fa-solid fa-user text-purple-500 text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800">
                                        <?= htmlspecialchars($viewMessage['name']) ?></h4>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($viewMessage['email']) ?></p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <?= date('F j, Y \a\t g:i A', strtotime($viewMessage['created_at'])) ?></p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <?php if ($viewMessage['event_type']): ?>
                                    <div class="mb-3">
                                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Event
                                            Type</span>
                                        <p class="text-sm text-gray-800 mt-1">
                                            <?= htmlspecialchars($viewMessage['event_type']) ?></p>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Message</span>
                                    <p class="text-sm text-gray-800 mt-2 bg-gray-50 p-4 rounded-xl leading-relaxed">
                                        <?= nl2br(htmlspecialchars($viewMessage['message'])) ?></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 pt-4 border-t border-gray-100">
                                <?php if (!$viewMessage['is_read']): ?>
                                    <a href="contact_messages.php?read=<?= $viewMessage['id'] ?><?= $search ? "&search=$search" : '' ?>"
                                        class="px-4 py-2 bg-green-500 text-white rounded-xl text-sm hover:bg-green-600 transition-all">
                                        <i class="fa-solid fa-check mr-1"></i> Mark as Read
                                    </a>
                                <?php else: ?>
                                    <a href="contact_messages.php?unread=<?= $viewMessage['id'] ?><?= $search ? "&search=$search" : '' ?>"
                                        class="px-4 py-2 bg-yellow-500 text-white rounded-xl text-sm hover:bg-yellow-600 transition-all">
                                        <i class="fa-solid fa-envelope mr-1"></i> Mark as Unread
                                    </a>
                                <?php endif; ?>
                                <a href="contact_messages.php<?= $search ? "?search=$search" : '' ?>"
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl text-sm hover:bg-gray-300 transition-all ml-auto">Close</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-200">

                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="p-3 text-left">From</th>
                                <th class="p-3 text-left">Event Type</th>
                                <th class="p-3 text-left">Message</th>
                                <th class="p-3 text-left">Date</th>
                                <th class="p-3 text-left">Status</th>
                                <th class="p-3 text-left">Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($messages)): ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-gray-400">No messages yet.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($messages as $m): ?>
                                <tr class="border-t hover:bg-gray-50 <?= !$m['is_read'] ? 'bg-purple-50/50' : '' ?>">

                                    <td class="p-3">
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($m['name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($m['email']) ?></div>
                                    </td>

                                    <td class="p-3 text-gray-700">
                                        <?= htmlspecialchars($m['event_type'] ?? '—') ?>
                                    </td>

                                    <td class="p-3 text-gray-600 max-w-xs truncate">
                                        <?= htmlspecialchars(substr($m['message'], 0, 80)) ?>    <?= strlen($m['message']) > 80 ? '...' : '' ?>
                                    </td>

                                    <td class="p-3 text-gray-700 whitespace-nowrap">
                                        <?= date('M j, Y', strtotime($m['created_at'])) ?>
                                    </td>

                                    <td class="p-3">
                                        <?php if ($m['is_read']): ?>
                                            <span class="px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-600">Read</span>
                                        <?php else: ?>
                                            <span
                                                class="px-3 py-1 text-xs rounded-full bg-purple-100 text-purple-700 font-medium">New</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="p-3 flex gap-2">
                                        <a href="contact_messages.php?view=<?= $m['id'] ?><?= $search ? "&search=$search" : '' ?>"
                                            class="px-3 py-1 bg-blue-500 text-white rounded-lg text-xs">View</a>
                                        <?php if (!$m['is_read']): ?>
                                            <a href="contact_messages.php?read=<?= $m['id'] ?><?= $search ? "&search=$search" : '' ?>"
                                                class="px-3 py-1 bg-green-500 text-white rounded-lg text-xs">Read</a>
                                        <?php endif; ?>
                                        <a href="contact_messages.php?delete=<?= $m['id'] ?><?= $search ? "&search=$search" : '' ?>"
                                            onclick="return confirm('Delete this message?')"
                                            class="px-3 py-1 bg-red-800 text-white rounded-lg text-xs">Delete</a>
                                    </td>

                                </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

            </main>

        </div>

    </div>

</body>

</html>