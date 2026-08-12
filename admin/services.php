<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$action = $_GET['action'] ?? 'list';
$serviceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editService = null;
$search = $_GET['search'] ?? '';

$queryParams = [];
if ($search !== '') $queryParams['search'] = $search;
$redirectQuery = $queryParams ? '?' . http_build_query($queryParams) : '';

// DELETE
if ($action === 'delete' && $serviceId > 0) {
    $stmt = $conn->prepare("DELETE FROM services WHERE id=?");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $stmt->close();
    header("Location: services.php$redirectQuery");
    exit();
}

// EDIT - fetch service data
if ($action === 'edit' && $serviceId > 0) {
    $stmt = $conn->prepare("SELECT * FROM services WHERE id=?");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editService = $result->fetch_assoc();
    $stmt->close();
    if (!$editService) {
        header("Location: services.php");
        exit();
    }
}

// POST - create or update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceName = $_POST['service_name'];
    $editId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($editId > 0) {
        $stmt = $conn->prepare("UPDATE services SET service_name=? WHERE id=?");
        $stmt->bind_param("si", $serviceName, $editId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO services (service_name) VALUES (?)");
        $stmt->bind_param("s", $serviceName);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: services.php$redirectQuery");
    exit();
}

// Fetch all services
$searchFilter = $search !== '' ? "WHERE service_name LIKE '%" . $conn->real_escape_string($search) . "%'" : "";
$services = $conn->query("SELECT * FROM services $searchFilter ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Pagination
$sTotal = count($services);
if ($search !== '') {
    $sTotalPages = 1;
    $sOffset = 0;
    $paginatedServices = $services;
} else {
    $sPage = isset($_GET['s_page']) ? max(1, (int)$_GET['s_page']) : 1;
    $sPerPage = 8;
    $sTotalPages = ceil($sTotal / $sPerPage);
    $sOffset = ($sPage - 1) * $sPerPage;
    $paginatedServices = array_slice($services, $sOffset, $sPerPage);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Services</title>
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

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }

        .modal-content {
            background: #fff;
            border-radius: 1rem;
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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
                        <input type="text" id="serviceSearch" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search services..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-400 bg-white">
                    </form>
                    <div class="flex gap-3">
                        <a href="services.php?action=add"
                            class="bg-purple-600 text-white px-5 py-2.5 rounded-xl hover:bg-purple-700 transition flex items-center gap-2 font-medium text-sm shadow-sm">
                            <i class="fa-solid fa-plus text-xs"></i> Add Service
                        </a>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-center px-6 py-4 font-semibold text-gray-600 w-12">No.</th>
                                <th class="text-left px-6 py-4 font-semibold text-gray-600">Service Name</th>
                                <th class="text-center px-6 py-4 font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody" class="divide-y divide-gray-100">
                            <?php $sIndex = $sOffset; ?>
                            <?php foreach ($paginatedServices as $s): $sIndex++; ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 text-center text-gray-500"><?= $sIndex ?></td>
                                    <td class="px-6 py-4 font-medium text-gray-800">
                                        <?= htmlspecialchars($s['service_name']) ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="#" onclick="openServiceEdit(<?= $s['id'] ?>); return false;"
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition">
                                                <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                            </a>
                                            <a href="services.php?action=delete&id=<?= $s['id'] ?>"
                                                onclick="return confirm('Delete this service?')"
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
                                                <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="no-results <?= empty($services) ? '' : 'hidden' ?>">
                                <td colspan="3" class="px-6 py-10 text-center text-gray-400 text-sm">No services found matching your search.</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="px-6 py-3 text-sm text-gray-500 border-t border-gray-100">
                        Total: <span class="font-semibold text-gray-700" id="totalCount"><?= $sTotal ?></span> services
                    </div>

                    <?php if ($sTotalPages > 1): ?>
                    <div id="pagination" class="flex justify-center items-center gap-2 px-6 py-3 border-t border-gray-100">
                    <?php $sQueryStr = $search !== '' ? '&search=' . urlencode($search) : ''; ?>
                    <a href="?s_page=1<?= $sQueryStr ?>"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $sPage <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                        <i class="fa-solid fa-angles-left mr-1"></i> First
                    </a>
                    <a href="?s_page=<?= max(1, $sPage-1) ?><?= $sQueryStr ?>"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $sPage <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                    <span class="text-xs text-gray-500 font-medium">Page <?= $sPage ?> of <?= $sTotalPages ?></span>
                    <a href="?s_page=<?= min($sTotalPages, $sPage+1) ?><?= $sQueryStr ?>"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $sPage >= $sTotalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                    <a href="?s_page=<?= $sTotalPages ?><?= $sQueryStr ?>"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $sPage >= $sTotalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                        Last <i class="fa-solid fa-angles-right ml-1"></i>
                    </a>
                    <form method="GET" class="flex items-center gap-1 ml-2">
                        <label class="text-xs text-gray-500 font-medium">Page:</label>
                        <input type="number" name="s_page" min="1" max="<?= $sTotalPages ?>" value="<?= $sPage ?>"
                            class="w-14 px-2 py-1 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-purple-500">
                        <?php if ($search !== ''): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                    </form>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Add/Edit Modal -->
                <div id="serviceModal"
                    class="modal-overlay <?= ($action === 'add' || $action === 'edit') ? '' : 'hidden' ?>">
                    <div class="modal-content">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">
                                    <?= $action === 'add' ? 'Add Service' : 'Edit Service' ?>
                                </h2>
                                <p class="text-sm text-gray-500 mt-0.5">
                                    <?= $action === 'add' ? 'Create a new service' : 'Update service details' ?>
                                </p>
                            </div>
                            <button onclick="closeModal()"
                                class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                        </div>

                        <form method="POST" class="space-y-4">
                            <?php if ($action === 'edit' && $editService): ?>
                                <input type="hidden" name="id" value="<?= $editService['id'] ?>">
                            <?php endif; ?>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Service Name</label>
                                <input type="text" name="service_name" required
                                    value="<?= $action === 'edit' && $editService ? htmlspecialchars($editService['service_name']) : '' ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50"
                                    placeholder="e.g. Catering, Photography">
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <button type="submit"
                                    class="bg-purple-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-purple-700 transition">
                                    <i class="fa-solid <?= $action === 'add' ? 'fa-plus' : 'fa-save' ?> mr-2"></i>
                                    <?= $action === 'add' ? 'Create Service' : 'Update Service' ?>
                                </button>
                                <button type="button" onclick="closeModal()"
                                    class="text-gray-500 hover:text-gray-700 font-medium text-sm">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function closeModal() { window.location.href = 'services.php<?= $redirectQuery ?>'; }

                    function openServiceEdit(id) {
                        fetch('services.php?action=edit&id=' + id)
                            .then(r => r.text())
                            .then(html => {
                                const doc = new DOMParser().parseFromString(html, 'text/html');
                                const modal = doc.getElementById('serviceModal');
                                const target = document.getElementById('serviceModal');
                                if (modal && target) {
                                    target.outerHTML = modal.outerHTML;
                                }
                                const fresh = document.getElementById('serviceModal');
                                if (fresh) fresh.classList.remove('hidden');
                            })
                            .catch(() => { });
                    }

                    <?php if ($action === 'add' || $action === 'edit'): ?>
                        document.addEventListener('DOMContentLoaded', function () {
                            const m = document.getElementById('serviceModal');
                            if (m) m.classList.remove('hidden');
                        });
                    <?php endif; ?>
                </script>

                <script>
                    (function () {
                        const form = document.getElementById('searchForm');
                        const input = document.getElementById('serviceSearch');
                        const tbody = document.getElementById('tableBody');
                        const totalEl = document.getElementById('totalCount');
                        const pagination = document.getElementById('pagination');
                        if (!form || !input || !tbody) return;

                        let timer;

                        function doSearch() {
                            const q = input.value.trim();

                            fetch('services.php' + (q ? '?search=' + encodeURIComponent(q) : ''))
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

            </main>

        </div>

    </div>
</body>

</html>