<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$action = $_GET['action'] ?? 'list';
$editPackage = null;

// DELETE package
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM packages WHERE id=$id");
    $conn->query("DELETE FROM venue_packages WHERE package_id=$id");
    $conn->query("DELETE FROM event_package_services WHERE package_id=$id");
    header("Location: packages.php");
    exit();
}

// EDIT - fetch package data
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM packages WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editPackage = $result->fetch_assoc();
    $stmt->close();
    if (!$editPackage) {
        header("Location: packages.php");
        exit();
    }
}

// POST - create or update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $editId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($editId > 0) {
        $stmt = $conn->prepare("UPDATE packages SET name=?, description=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $description, $editId);
        $stmt->execute();
        $stmt->close();

        // Update venue_packages prices
        if (isset($_POST['venue_price'])) {
            $conn->query("DELETE FROM venue_packages WHERE package_id=$editId");
            $stmt = $conn->prepare("INSERT INTO venue_packages (venue_id, package_id, price) VALUES (?, ?, ?)");
            foreach ($_POST['venue_price'] as $venueId => $price) {
                $price = (float)$price;
                if ($price > 0) {
                    $stmt->bind_param("iid", $venueId, $editId, $price);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        // Update event_package_services
        if (isset($_POST['event_services'])) {
            $conn->query("DELETE FROM event_package_services WHERE package_id=$editId");
            $stmt = $conn->prepare("INSERT INTO event_package_services (event_id, package_id, service_id) VALUES (?, ?, ?)");
            foreach ($_POST['event_services'] as $eventId => $serviceIds) {
                foreach ($serviceIds as $serviceId) {
                    $stmt->bind_param("iii", $eventId, $editId, $serviceId);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO packages (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        if (isset($_POST['venue_price'])) {
            $stmt = $conn->prepare("INSERT INTO venue_packages (venue_id, package_id, price) VALUES (?, ?, ?)");
            foreach ($_POST['venue_price'] as $venueId => $price) {
                $price = (float)$price;
                if ($price > 0) {
                    $stmt->bind_param("iid", $venueId, $newId, $price);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        if (isset($_POST['event_services'])) {
            $stmt = $conn->prepare("INSERT INTO event_package_services (event_id, package_id, service_id) VALUES (?, ?, ?)");
            foreach ($_POST['event_services'] as $eventId => $serviceIds) {
                foreach ($serviceIds as $serviceId) {
                    $stmt->bind_param("iii", $eventId, $newId, $serviceId);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
    }

    header("Location: packages.php");
    exit();
}

// Fetch all packages with associated data
$packages = $conn->query("SELECT * FROM packages ORDER BY FIELD(name, 'Silver', 'Gold', 'Diamond')")->fetch_all(MYSQLI_ASSOC);

// Fetch all events, venues, services for form dropdowns
$eventsList = $conn->query("SELECT id, event_name FROM events ORDER BY event_name")->fetch_all(MYSQLI_ASSOC);
$venuesList = $conn->query("SELECT id, name FROM venues ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$servicesList = $conn->query("SELECT id, service_name FROM services ORDER BY service_name")->fetch_all(MYSQLI_ASSOC);

// Fetch existing venue_packages data
$vpData = [];
$vpRes = $conn->query("SELECT venue_id, package_id, price FROM venue_packages");
if ($vpRes) {
    while ($row = $vpRes->fetch_assoc()) {
        $vpData[$row['package_id']][$row['venue_id']] = $row['price'];
    }
}

// Fetch existing event_package_services data
$epsData = [];
$epsRes = $conn->query("SELECT event_id, package_id, service_id FROM event_package_services");
if ($epsRes) {
    while ($row = $epsRes->fetch_assoc()) {
        $epsData[$row['package_id']][$row['event_id']][] = $row['service_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Packages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
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
            background: rgba(0,0,0,0.5);
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
            max-width: 640px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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
                    <h2 class="text-2xl font-bold text-gray-800">Packages</h2>
                    <div class="flex gap-3">
                        <a href="packages.php?action=add"
                            class="bg-purple-600 text-white px-5 py-2 rounded-xl hover:bg-purple-700">+ Add Package</a>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <!-- <th class="text-left px-6 py-4 font-semibold text-gray-600">#</th> -->
                                <th class="text-left px-6 py-4 font-semibold text-gray-600">Package Name</th>
                                <th class="text-left px-6 py-4 font-semibold text-gray-600">Description</th>
                                <th class="text-center px-6 py-4 font-semibold text-gray-600">Venues</th>
                                <th class="text-center px-6 py-4 font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($packages as $p): ?>
                                <?php $venueCount = isset($vpData[$p['id']]) ? count($vpData[$p['id']]) : 0; ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <!-- <td class="px-6 py-4 text-gray-500"><?= $p['id'] ?></td> -->
                                    <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($p['name']) ?></td>
                                    <td class="px-6 py-4 text-gray-500 max-w-xs truncate"><?= htmlspecialchars($p['description'] ?? '—') ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium <?= $venueCount > 0 ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-500' ?>">
                                            <i class="fa-solid fa-location-dot"></i> <?= $venueCount ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="packages.php?action=edit&id=<?= $p['id'] ?>"
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition">
                                                <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                            </a>
                                            <a href="packages.php?action=delete&id=<?= $p['id'] ?>"
                                                onclick="return confirm('Delete this package? This will also remove all venue prices and service assignments.')"
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
                                                <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($packages)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-400">No packages found. Click "+ Add Package" to create one.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add/Edit Modal -->
                <div id="packageModal" class="modal-overlay <?= ($action === 'add' || $action === 'edit') ? '' : 'hidden' ?>">
                    <div class="modal-content">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800"><?= $action === 'add' ? 'Add Package' : 'Edit Package' ?></h2>
                                <p class="text-sm text-gray-500 mt-0.5"><?= $action === 'add' ? 'Create a new package tier' : 'Update package details' ?></p>
                            </div>
                            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                        </div>

                        <form method="POST" class="space-y-4">
                            <?php if ($action === 'edit' && $editPackage): ?>
                                <input type="hidden" name="id" value="<?= $editPackage['id'] ?>">
                            <?php endif; ?>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Package Name</label>
                                <select name="name" required
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50">
                                    <option value="">— Select Package —</option>
                                    <option value="Silver" <?= $action === 'edit' && $editPackage && $editPackage['name'] === 'Silver' ? 'selected' : '' ?>>Silver</option>
                                    <option value="Gold" <?= $action === 'edit' && $editPackage && $editPackage['name'] === 'Gold' ? 'selected' : '' ?>>Gold</option>
                                    <option value="Diamond" <?= $action === 'edit' && $editPackage && $editPackage['name'] === 'Diamond' ? 'selected' : '' ?>>Diamond</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                                <textarea name="description" rows="3"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50 resize-none"><?= $action === 'edit' && $editPackage ? htmlspecialchars($editPackage['description'] ?? '') : '' ?></textarea>
                            </div>

                            <!-- Venue Pricing -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Venue Prices (MMK)</label>
                                <p class="text-xs text-gray-400 mb-3">Set the price for this package at each venue.</p>
                                <div class="space-y-2 max-h-48 overflow-y-auto p-3 border border-gray-100 rounded-xl bg-gray-50/50">
                                    <?php if (!empty($venuesList)): ?>
                                        <?php foreach ($venuesList as $v): ?>
                                            <?php
                                                $pkgId = $action === 'edit' && $editPackage ? $editPackage['id'] : 0;
                                                $existingPrice = isset($vpData[$pkgId][$v['id']]) ? $vpData[$pkgId][$v['id']] : '';
                                            ?>
                                            <div class="flex items-center gap-3">
                                                <label class="text-sm text-gray-600 w-1/2 truncate"><?= htmlspecialchars($v['name']) ?></label>
                                                <input type="number" name="venue_price[<?= $v['id'] ?>]" min="0" step="0.01"
                                                    value="<?= $existingPrice ?>"
                                                    placeholder="0.00"
                                                    class="w-1/2 px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:border-purple-400 bg-white text-sm">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400">No venues available. Create venues first.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Service Assignment per Event -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Services per Event</label>
                                <p class="text-xs text-gray-400 mb-3">Select which services are included in this package for each event.</p>
                                <div class="space-y-4 max-h-64 overflow-y-auto p-3 border border-gray-100 rounded-xl bg-gray-50/50">
                                    <?php if (!empty($eventsList) && !empty($servicesList)): ?>
                                        <?php foreach ($eventsList as $ev): ?>
                                            <?php
                                                $pkgId = $action === 'edit' && $editPackage ? $editPackage['id'] : 0;
                                                $selectedServices = $epsData[$pkgId][$ev['id']] ?? [];
                                            ?>
                                            <fieldset>
                                                <legend class="text-sm font-semibold text-gray-700 mb-1"><?= htmlspecialchars($ev['event_name']) ?></legend>
                                                <div class="flex flex-wrap gap-2">
                                                    <?php foreach ($servicesList as $svc): ?>
                                                        <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                                                            <input type="checkbox" name="event_services[<?= $ev['id'] ?>][]"
                                                                value="<?= $svc['id'] ?>"
                                                                <?= in_array($svc['id'], $selectedServices) ? 'checked' : '' ?>
                                                                class="rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                                                            <?= htmlspecialchars($svc['service_name']) ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </fieldset>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400">Create events and services first before assigning.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <button type="submit"
                                    class="bg-purple-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-purple-700 transition">
                                    <i class="fa-solid <?= $action === 'add' ? 'fa-plus' : 'fa-save' ?> mr-2"></i>
                                    <?= $action === 'add' ? 'Create Package' : 'Update Package' ?>
                                </button>
                                <button type="button" onclick="closeModal()"
                                    class="text-gray-500 hover:text-gray-700 font-medium text-sm">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function closeModal() { window.location.href = 'packages.php'; }

                    <?php if ($action === 'add' || $action === 'edit'): ?>
                    document.addEventListener('DOMContentLoaded', function () {
                        const m = document.getElementById('packageModal');
                        if (m) m.classList.remove('hidden');
                    });
                    <?php endif; ?>
                </script>
            </main>
        </div>
    </div>
</body>
</html>
