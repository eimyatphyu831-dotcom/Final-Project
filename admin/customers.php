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

// --- DELETE ---
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: customers.php" . ($search ? "?search=$search" : ''));
    exit();
}

// --- VIEW ---
$viewUser = null;
$viewBookings = [];
$viewId = $_GET['view'] ?? 0;
if ($viewId) {
    $stmt = $conn->prepare("SELECT id, name, email, phone, created_at FROM users WHERE id=?");
    $stmt->bind_param("i", $viewId);
    $stmt->execute();
    $viewUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($viewUser) {
        $stmt = $conn->prepare("SELECT b.id, b.event_date, b.start_time, b.end_time, b.total_cost, b.status, b.created_at,
                                       e.event_name, v.name AS venue_name, p.name AS package_name
                                FROM bookings b
                                JOIN events e ON b.event_id = e.id
                                JOIN venues v ON b.venue_id = v.id
                                JOIN packages p ON b.package_id = p.id
                                WHERE b.user_id=?
                                ORDER BY b.created_at DESC");
        $stmt->bind_param("i", $viewId);
        $stmt->execute();
        $viewBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// --- FETCH CUSTOMERS ---
if ($search) {
    $like = "%$search%";
    $stmt = $conn->prepare("SELECT id, name, email, phone, created_at FROM users WHERE name LIKE ? OR email LIKE ?");
    $stmt->bind_param("ss", $like, $like);
} else {
    $stmt = $conn->prepare("SELECT id, name, email, phone, created_at FROM users");
}

$stmt->execute();
$result = $stmt->get_result();
$customers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Customers</title>
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

                <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
                    <div class="relative flex-1 max-w-sm">
                        <i
                            class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="searchInput" placeholder="Search customers..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-400 bg-white">
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-100 text-green-700 px-4 py-3 rounded-xl text-sm mb-4 border border-green-200">
                        <?= $message ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-100 text-red-700 px-4 py-3 rounded-xl text-sm mb-4 border border-red-200">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <?php if ($viewUser): ?>
                    <div id="viewModal"
                        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 py-10">
                        <div
                            class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 w-full max-w-3xl mx-4 relative">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Customer Details</h3>
                                <a href="customers.php<?= $search ? "?search=$search" : '' ?>"
                                    class="text-gray-400 hover:text-gray-600 text-xl"><i class="fa-solid fa-xmark"></i></a>
                            </div>

                            <div class="flex items-center gap-4 pb-4 border-b border-gray-100 mb-4">
                                <div class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center">
                                    <i class="fa-solid fa-user text-purple-500 text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800">
                                        <?= htmlspecialchars($viewUser['name']) ?>
                                    </h4>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($viewUser['email']) ?></p>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-4 mb-6">
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-400 uppercase tracking-wide">Phone</label>
                                    <p class="text-sm text-gray-800 mt-1"><?= htmlspecialchars($viewUser['phone'] ?? '—') ?>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wide">Member
                                        Since</label>
                                    <p class="text-sm text-gray-800 mt-1">
                                        <?= date('F j, Y', strtotime($viewUser['created_at'])) ?>
                                    </p>
                                </div>
                            </div>

                            <h4 class="text-md font-semibold text-gray-800 mb-3">Bookings (<?= count($viewBookings) ?>)</h4>
                            <?php if (empty($viewBookings)): ?>
                                <p class="text-sm text-gray-400 py-4 text-center">No bookings found for this customer.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 text-gray-600">
                                            <tr>
                                                <th class="p-2 text-left">Event</th>
                                                <th class="p-2 text-left">Venue</th>
                                                <th class="p-2 text-left">Package</th>
                                                <th class="p-2 text-left">Date</th>
                                                <th class="p-2 text-left">Cost</th>
                                                <th class="p-2 text-left">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tableBody">
                                            <?php foreach ($viewBookings as $bk): ?>
                                                <tr class="border-t hover:bg-gray-50">
                                                    <td class="p-2 font-medium text-gray-800">
                                                        <?= htmlspecialchars($bk['event_name']) ?>
                                                    </td>
                                                    <td class="p-2 text-gray-600"><?= htmlspecialchars($bk['venue_name']) ?></td>
                                                    <td class="p-2 text-gray-600"><?= htmlspecialchars($bk['package_name']) ?></td>
                                                    <td class="p-2 text-gray-600">
                                                        <?= date('M j, Y', strtotime($bk['event_date'])) ?>
                                                    </td>
                                                    <td class="p-2 text-gray-800">$<?= number_format($bk['total_cost'], 2) ?></td>
                                                    <td class="p-2">
                                                        <?php
                                                        $badge = match ($bk['status']) {
                                                            'Confirmed' => 'bg-green-100 text-green-700',
                                                            'Cancelled' => 'bg-red-100 text-red-700',
                                                            default => 'bg-yellow-100 text-yellow-700'
                                                        };
                                                        ?>
                                                        <span
                                                            class="px-2 py-0.5 text-xs rounded-full <?= $badge ?>"><?= $bk['status'] ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <div class="flex justify-end pt-4">
                                <a href="customers.php<?= $search ? "?search=$search" : '' ?>"
                                    class="px-6 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-all">Close</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-200">

                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="p-3 text-left">Customer</th>
                                <th class="p-3 text-left">Phone</th>
                                <th class="p-3 text-left">Joined</th>
                                <th class="p-3 text-left">Status</th>
                                <th class="p-3 text-left">Actions</th>
                            </tr>
                        </thead>

                        <tbody id="tableBody">

                            <?php foreach ($customers as $c): ?>
                                <tr class="border-t hover:bg-gray-50">

                                    <td class="p-3">
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($c['name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($c['email']) ?></div>
                                    </td>

                                    <td class="p-3 text-gray-700">
                                        <?= htmlspecialchars($c['phone'] ?? '—') ?>
                                    </td>

                                    <td class="p-3 text-gray-700">
                                        <?= date('Y-m-d', strtotime($c['created_at'])) ?>
                                    </td>

                                    <td class="p-3">
                                        <span
                                            class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-700">Active</span>
                                    </td>

                                    <td class="p-3 flex gap-2">
                                        <a href="customers.php?view=<?= $c['id'] ?><?= $search ? "&search=$search" : '' ?>"
                                            class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-600 rounded-lg text-xs hover:bg-blue-400 transition">
                                            <i class="fa-solid fa-eye"></i>
                                            View
                                        </a>

                                        <a href="customers.php?delete=<?= $c['id'] ?><?= $search ? "&search=$search" : '' ?>"
                                            onclick="return confirm('Delete this customer?')"
                                            class="inline-flex items-center gap-1 px-3 py-1 bg-red-100 text-red-600 rounded-lg text-xs hover:bg-red-400 transition">
                                            <i class="fa-solid fa-trash-can mr-1"></i>
                                            Delete
                                        </a>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                            <tr class="no-results hidden">
                                <td colspan="5" class="p-6 text-center text-gray-400 text-sm">No customers found
                                    matching your search.</td>
                            </tr>

                        </tbody>
                    </table>

                </div>

            </main>

        </div>

    </div>

    <script>
        document.getElementById('searchInput')?.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            let visible = 0;
            document.querySelectorAll('#tableBody tr').forEach(row => {
                if (row.classList.contains('no-results')) return;
                const match = row.textContent.toLowerCase().includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.querySelector('.no-results')?.classList.toggle('hidden', visible > 0);
        });
    </script>
</body>

</html>