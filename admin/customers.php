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
$viewStats = null;
$viewReviews = null;
$viewBookings = [];
$viewId = $_GET['view'] ?? 0;
if ($viewId) {
    $stmt = $conn->prepare("SELECT id, name, email, phone, image, created_at FROM users WHERE id=?");
    $stmt->bind_param("i", $viewId);
    $stmt->execute();
    $viewUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($viewUser) {
        $stmt = $conn->prepare("SELECT
            COUNT(*) AS total_bookings,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_events,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_bookings,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_bookings,
            COALESCE(SUM(total_cost), 0) AS total_spending
        FROM bookings WHERE user_id=?");
        $stmt->bind_param("i", $viewId);
        $stmt->execute();
        $viewStats = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT b.id, b.event_date, b.total_cost, b.status,
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

        $stmt = $conn->prepare("SELECT COUNT(*) AS review_count FROM reviews WHERE user_id=?");
        $stmt->bind_param("i", $viewId);
        $stmt->execute();
        $viewReviews = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// --- FETCH CUSTOMERS ---
if ($search) {
    $like = "%$search%";
    $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.phone, u.created_at, COUNT(b.id) AS total_bookings FROM users u LEFT JOIN bookings b ON b.user_id = u.id WHERE u.name LIKE ? OR u.email LIKE ? GROUP BY u.id");
    $stmt->bind_param("ss", $like, $like);
} else {
    $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.phone, u.created_at, COUNT(b.id) AS total_bookings FROM users u LEFT JOIN bookings b ON b.user_id = u.id GROUP BY u.id");
}

$stmt->execute();
$result = $stmt->get_result();
$customers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pagination
$cTotal = count($customers);
if ($search) {
    $cTotalPages = 1;
    $cOffset = 0;
    $paginatedCustomers = $customers;
} else {
    $cPage = isset($_GET['c_page']) ? max(1, (int)$_GET['c_page']) : 1;
    $cPerPage = 8;
    $cTotalPages = ceil($cTotal / $cPerPage);
    $cOffset = ($cPage - 1) * $cPerPage;
    $paginatedCustomers = array_slice($customers, $cOffset, $cPerPage);
}
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

        <div class="flex-1 flex flex-col lg:ml-56">

            <?php include 'admin_header.php'; ?>

            <main class="flex-1 p-6 overflow-y-auto">

                <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
                    <form method="GET" class="relative flex-1 max-w-sm" id="searchForm">
                        <button type="submit" aria-label="Search"
                            class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-purple-500">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </button>
                        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search customers..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-400 bg-white">
                    </form>
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

                <div id="viewModalWrapper">
                <?php if ($viewUser): ?>
                    <div id="viewModal"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                        <div
                            class="bg-white rounded-2xl shadow-xl border border-gray-200 p-4 w-full max-w-xl mx-4 relative">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-gray-800">Customer Details</h3>
                                <a href="#" onclick="closeCustomerView(); return false;"
                                    class="text-gray-400 hover:text-gray-600 text-lg"><i class="fa-solid fa-xmark"></i></a>
                            </div>

                            <div class="flex items-center gap-3 pb-2 border-b border-gray-100 mb-2">
                                <?php
                                    $profileImg = $viewUser['image'] ? '../uploads/profiles/' . $viewUser['image'] : null;
                                    $initials = strtoupper(substr($viewUser['name'], 0, 2));
                                ?>
                                <?php if ($profileImg): ?>
                                    <img src="<?= htmlspecialchars($profileImg) ?>" alt="<?= htmlspecialchars($viewUser['name']) ?>"
                                        class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-indigo-500 items-center justify-center text-white text-sm font-bold shrink-0" style="display:none">
                                        <?= $initials ?>
                                    </div>
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-indigo-500 flex items-center justify-center text-white text-sm font-bold shrink-0">
                                        <?= $initials ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-800">
                                        <?= htmlspecialchars($viewUser['name']) ?>
                                    </h4>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($viewUser['email']) ?></p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div class="bg-gray-50 rounded-lg p-2 border border-gray-100">
                                    <label class="block text-[9px] font-medium text-gray-400 uppercase tracking-wide">Phone</label>
                                    <p class="text-xs text-gray-800 mt-0.5"><?= htmlspecialchars($viewUser['phone'] ?? '—') ?></p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-2 border border-gray-100">
                                    <label class="block text-[9px] font-medium text-gray-400 uppercase tracking-wide">Member Since</label>
                                    <p class="text-xs text-gray-800 mt-0.5"><?= date('F j, Y', strtotime($viewUser['created_at'])) ?></p>
                                </div>
                            </div>

                            <?php if ($viewStats): ?>
                                <div class="grid grid-cols-3 gap-2 mb-3">
                                    <div class="bg-blue-50 rounded-lg p-2 border border-blue-100">
                                        <label class="block text-[9px] font-medium text-blue-500 uppercase tracking-wide">Total Bookings</label>
                                        <p class="text-base font-bold text-blue-700"><?= $viewStats['total_bookings'] ?></p>
                                    </div>
                                    <div class="bg-green-50 rounded-lg p-2 border border-green-100">
                                        <label class="block text-[9px] font-medium text-green-500 uppercase tracking-wide">Completed</label>
                                        <p class="text-base font-bold text-green-700"><?= $viewStats['completed_events'] ?></p>
                                    </div>
                                    <div class="bg-amber-50 rounded-lg p-2 border border-amber-100">
                                        <label class="block text-[9px] font-medium text-amber-500 uppercase tracking-wide">Total Spending</label>
                                        <p class="text-base font-bold text-amber-700">$<?= number_format($viewStats['total_spending'], 2) ?></p>
                                    </div>
                                </div>

                                <h5 class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Booking Details</h5>
                                <div class="overflow-y-auto max-h-64 mb-4">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50 text-gray-600">
                                            <tr>
                                                <th class="p-1.5 text-center w-8">No.</th>
                                                <th class="p-1.5 text-left">Event</th>
                                                <th class="p-1.5 text-left">Venue</th>
                                                <th class="p-1.5 text-left">Package</th>
                                                <th class="p-1.5 text-left">Date</th>
                                                <th class="p-1.5 text-left">Cost</th>
                                                <th class="p-1.5 text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $vbIndex = 0; ?>
                                            <?php foreach ($viewBookings as $bk): $vbIndex++; ?>
                                                <tr class="border-t hover:bg-gray-50">
                                                    <td class="p-1.5 text-center text-gray-500"><?= $vbIndex ?></td>
                                                    <td class="p-1.5 font-medium text-gray-800"><?= htmlspecialchars($bk['event_name']) ?></td>
                                                    <td class="p-1.5 text-gray-600"><?= htmlspecialchars($bk['venue_name']) ?></td>
                                                    <td class="p-1.5 text-gray-600"><?= htmlspecialchars($bk['package_name']) ?></td>
                                                    <td class="p-1.5 text-gray-600"><?= date('M j, Y', strtotime($bk['event_date'])) ?></td>
                                                    <td class="p-1.5 text-gray-800">$<?= number_format($bk['total_cost'], 2) ?></td>
                                                    <td class="p-1.5 text-center">
                                                        <?php
                                                        $badge = match ($bk['status']) {
                                                            'Confirmed' => 'bg-green-100 text-green-700',
                                                            'Completed' => 'bg-blue-100 text-blue-700',
                                                            'Cancelled' => 'bg-red-100 text-red-700',
                                                            default => 'bg-yellow-100 text-yellow-700'
                                                        };
                                                        ?>
                                                        <span class="px-1.5 py-0.5 rounded-full <?= $badge ?>"><?= $bk['status'] ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 py-3 text-center">No data found for this customer.</p>
                            <?php endif; ?>

                            <div class="flex justify-end pt-2 mt-3 border-t border-gray-100">
                                <a href="#" onclick="closeCustomerView(); return false;"
                                    class="px-3 py-1 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-all text-xs">Close</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                    <div class="overflow-x-auto">

                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="p-3 text-center w-10">No.</th>
                                <th class="p-3 text-left">Customer</th>
                                <th class="p-3 text-left">Phone</th>
                                <th class="p-3 text-left">Joined</th>
                                <th class="p-3 text-center">Total Bookings</th>
                                <th class="p-3 text-center">Actions</th>
                            </tr>
                        </thead>

                        <tbody id="tableBody">

                            <?php $cIndex = $cOffset; ?>
                            <?php foreach ($paginatedCustomers as $c): $cIndex++; ?>
                                <tr class="border-t hover:bg-gray-50">

                                    <td class="p-3 text-center text-gray-500"><?= $cIndex ?></td>
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

                                    <td class="p-3 text-center font-semibold text-gray-700">
                                        <?= $c['total_bookings'] ?>
                                    </td>

                                    <td class="p-3">
                                        <div class="flex justify-center items-center gap-2">

                                            <a href="#" onclick="openCustomerView(<?= $c['id'] ?>); return false;"
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
                            <tr class="no-results <?= empty($customers) ? '' : 'hidden' ?>">
                                <td colspan="6" class="p-6 text-center text-gray-400 text-sm">No customers found
                                    matching your search.</td>
                            </tr>

                        </tbody>
                    </table>
                    </div>

                    <div class="px-6 py-3 text-sm text-gray-500 border-t border-gray-100">
                        Total: <span class="font-semibold text-gray-700" id="totalCount"><?= $cTotal ?></span> customers
                    </div>

                    <?php if ($cTotalPages > 1): ?>
                    <div id="pagination" class="flex justify-center items-center gap-2 px-6 py-4 border-t border-gray-100">
                        <?php $cQueryStr = $search ? '&search=' . urlencode($search) : ''; ?>
                        <a href="?c_page=1<?= $cQueryStr ?>"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $cPage <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                            <i class="fa-solid fa-angles-left mr-1"></i> First
                        </a>
                        <a href="?c_page=<?= max(1, $cPage-1) ?><?= $cQueryStr ?>"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $cPage <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                        <span class="text-xs text-gray-500 font-medium">Page <?= $cPage ?> of <?= $cTotalPages ?></span>
                        <a href="?c_page=<?= min($cTotalPages, $cPage+1) ?><?= $cQueryStr ?>"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $cPage >= $cTotalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                        <a href="?c_page=<?= $cTotalPages ?><?= $cQueryStr ?>"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $cPage >= $cTotalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                            Last <i class="fa-solid fa-angles-right ml-1"></i>
                        </a>
                        <form method="GET" class="flex items-center gap-1 ml-2">
                            <label class="text-xs text-gray-500 font-medium">Page:</label>
                            <input type="number" name="c_page" min="1" max="<?= $cTotalPages ?>" value="<?= $cPage ?>"
                                class="w-14 px-2 py-1 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-purple-500">
                            <?php if ($search): ?>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

            </main>

        </div>

    </div>

<script>
    function openCustomerView(id) {
        fetch('customers.php?view=' + id)
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const wrapper = doc.getElementById('viewModalWrapper');
                const target = document.getElementById('viewModalWrapper');
                if (wrapper && target) target.innerHTML = wrapper.innerHTML;
            })
            .catch(() => {});
    }

    function closeCustomerView() {
        const wrapper = document.getElementById('viewModalWrapper');
        if (wrapper) wrapper.innerHTML = '';
    }

    (function () {
        const form = document.getElementById('searchForm');
        const input = document.getElementById('searchInput');
        const tbody = document.getElementById('tableBody');
        const totalEl = document.getElementById('totalCount');
        const pagination = document.getElementById('pagination');
        if (!form || !input || !tbody) return;

        let timer;

        function doSearch() {
            const q = input.value.trim();

            fetch('customers.php' + (q ? '?search=' + encodeURIComponent(q) : ''))
                .then(r => r.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const nt = doc.getElementById('tableBody');
                    const ntc = doc.getElementById('totalCount');
                    const np = doc.getElementById('pagination');
                    if (nt) tbody.innerHTML = nt.innerHTML;
                    if (ntc && totalEl) totalEl.textContent = ntc.textContent;
                    if (np) { if (pagination) { pagination.innerHTML = np.innerHTML; pagination.style.display = ''; } }
                    else if (pagination) pagination.style.display = 'none';
                })
                .catch(() => {});
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(doSearch, 400);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            doSearch();
        });
    })();
</script>
</body>

</html>