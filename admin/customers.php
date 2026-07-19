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
$viewReviews = [];
$viewId = $_GET['view'] ?? 0;
if ($viewId) {
    $stmt = $conn->prepare("SELECT id, name, email, phone, image, created_at FROM users WHERE id=?");
    $stmt->bind_param("i", $viewId);
    $stmt->execute();
    $viewUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($viewUser) {
        $stmt = $conn->prepare("SELECT b.id, b.event_date, b.total_cost, b.status, b.created_at,
                                       e.event_name, v.name AS venue_name, p.name AS package_name,
                                       ts.start_time, ts.end_time
                                FROM bookings b
                                JOIN events e ON b.event_id = e.id
                                JOIN venues v ON b.venue_id = v.id
                                JOIN packages p ON b.package_id = p.id
                                LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
                                WHERE b.user_id=?
                                ORDER BY b.created_at DESC");
        $stmt->bind_param("i", $viewId);
        $stmt->execute();
        $viewBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare("SELECT r.id, r.rating, r.review_text, r.created_at, e.event_name
                                FROM reviews r
                                JOIN events e ON r.event_id = e.id
                                WHERE r.user_id=?
                                ORDER BY r.created_at DESC");
        $stmt->bind_param("i", $viewId);
        $stmt->execute();
        $viewReviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        <div class="flex-1 flex flex-col lg:ml-64">

            <?php include 'admin_header.php'; ?>

            <main class="flex-1 p-6 overflow-y-auto">

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
                                <?php
                                    $profileImg = $viewUser['image'] ? '../uploads/profiles/' . $viewUser['image'] : null;
                                    $initials = strtoupper(substr($viewUser['name'], 0, 2));
                                ?>
                                <?php if ($profileImg): ?>
                                    <img src="<?= htmlspecialchars($profileImg) ?>" alt="<?= htmlspecialchars($viewUser['name']) ?>"
                                        class="w-16 h-16 rounded-full object-cover border-2 border-white shadow-sm"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-500 to-indigo-500 items-center justify-center text-white text-xl font-bold shrink-0" style="display:none">
                                        <?= $initials ?>
                                    </div>
                                <?php else: ?>
                                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-500 to-indigo-500 flex items-center justify-center text-white text-xl font-bold shrink-0">
                                        <?= $initials ?>
                                    </div>
                                <?php endif; ?>
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
                                                <th class="p-2 text-left">Time</th>
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
                                                    <td class="p-2 text-gray-600"><?= date('g:i A', strtotime($bk['start_time'])) ?> – <?= date('g:i A', strtotime($bk['end_time'])) ?></td>
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

                            <h4 class="text-md font-semibold text-gray-800 mb-3 mt-6">Reviews (<?= count($viewReviews) ?>)</h4>
                            <?php if (empty($viewReviews)): ?>
                                <p class="text-sm text-gray-400 py-4 text-center">No reviews from this customer.</p>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($viewReviews as $rv): ?>
                                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($rv['event_name']) ?></span>
                                                <span class="text-xs text-gray-400"><?= date('M j, Y', strtotime($rv['created_at'])) ?></span>
                                            </div>
                                            <div class="flex gap-0.5 mb-1">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <svg class="w-4 h-4 <?= $i <= $rv['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                <?php endfor; ?>
                                            </div>
                                            <p class="text-xs text-gray-600">&ldquo;<?= htmlspecialchars($rv['review_text']) ?>&rdquo;</p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="flex justify-end pt-4 mt-4 border-t border-gray-100">
                                <a href="customers.php<?= $search ? "?search=$search" : '' ?>"
                                    class="px-6 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-all">Close</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                    <div class="overflow-x-auto">

                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="p-3 text-left">Customer</th>
                                <th class="p-3 text-left">Phone</th>
                                <th class="p-3 text-left">Joined</th>
                                <th class="p-3 text-left">Status</th>
                                <th class="p-3 text-center">Actions</th>
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

                                    <td class="p-3">
                                        <div class="flex justify-center items-center gap-2">

                                            <a href="customers.php?view=<?= $c['id'] ?><?= $search ? "&search=$search" : '' ?>"
                                                class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-600 rounded-lg text-xs hover:bg-blue-400 transition">
                                                <i class="fa-solid fa-eye"></i>
                                                View
                                            </a>

                                            <a href="customers.php?delete=<?= $c['id'] ?><?= $search ? "&search=$search" : '' ?>"
                                                onclick="return confirm('Delete this customer?')"
                                                class="inline-flex items-center gap-1 px-3 py-1 bg-red-100 text-red-600 rounded-lg text-xs hover:bg-red-400 transition">
                                                <i class="fa-solid fa-trash-can"></i>
                                                Delete
                                            </a>

                                        </div>
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