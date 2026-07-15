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

$eventsList = $conn->query("SELECT id, event_name FROM events ORDER BY event_name")->fetch_all(MYSQLI_ASSOC);
$venuesList = $conn->query("SELECT id, name FROM venues ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$servicesList = $conn->query("SELECT id, service_name FROM services ORDER BY service_name")->fetch_all(MYSQLI_ASSOC);

$vpData = [];
$vpRes = $conn->query("SELECT venue_id, package_id, price FROM venue_packages");
if ($vpRes) {
    while ($row = $vpRes->fetch_assoc()) {
        $vpData[$row['package_id']][$row['venue_id']] = $row['price'];
    }
}

$epsData = [];
$epsRes = $conn->query("SELECT event_id, package_id, service_id FROM event_package_services");
if ($epsRes) {
    while ($row = $epsRes->fetch_assoc()) {
        $epsData[$row['package_id']][$row['event_id']][] = $row['service_id'];
    }
}

// Package color configs
$pkgColors = [
    'Silver'  => ['bg' => 'bg-gradient-to-br from-gray-100 to-gray-200', 'border' => 'border-gray-300', 'accent' => 'text-gray-700', 'badge' => 'bg-gray-500', 'icon_bg' => 'bg-gray-400'],
    'Gold'    => ['bg' => 'bg-gradient-to-br from-amber-50 to-orange-100', 'border' => 'border-amber-300', 'accent' => 'text-amber-700', 'badge' => 'bg-amber-500', 'icon_bg' => 'bg-amber-400'],
    'Diamond' => ['bg' => 'bg-gradient-to-br from-blue-50 to-indigo-100', 'border' => 'border-blue-300', 'accent' => 'text-blue-700', 'badge' => 'bg-blue-500', 'icon_bg' => 'bg-blue-400'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Packages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Playfair Display', serif; }
        .bg-sidebar { background-color: #ffffff; }
        .bg-sidebar-active { background-color: #C3B1E1; color: #ffffff; }
        .text-purple-brand { color: #9966cc; }
        .bg-purple-brand { background-color: #C3B1E1; }

        .pkg-card {
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .pkg-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.12);
        }

        .edit-card {
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen overflow-hidden">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>
        <div class="flex-1 flex flex-col lg:ml-64">
            <?php include 'admin_header.php'; ?>
            <main class="flex-1 p-8 overflow-y-auto">

                <!-- Header -->
                <div class="flex flex-wrap justify-between items-center gap-4 mb-8">
                    <div class="relative flex-1 max-w-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="packageSearch" placeholder="Search packages..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-400 bg-white">
                    </div>
                    <a href="packages.php?action=add"
                        class="bg-purple-600 text-white px-5 py-2.5 rounded-xl hover:bg-purple-700 transition flex items-center gap-2 font-medium text-sm shadow-sm">
                        <i class="fa-solid fa-plus text-xs"></i> Add Package
                    </a>
                </div>

                <!-- Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <?php foreach ($packages as $p):
                        $name = $p['name'];
                        $c = $pkgColors[$name] ?? $pkgColors['Silver'];
                        $venueCount = isset($vpData[$p['id']]) ? count($vpData[$p['id']]) : 0;
                        $serviceCount = 0;
                        if (isset($epsData[$p['id']])) {
                            foreach ($epsData[$p['id']] as $svcs) {
                                $serviceCount += count($svcs);
                            }
                        }
                        $minPrice = 0;
                        if (isset($vpData[$p['id']])) {
                            $prices = array_values($vpData[$p['id']]);
                            $minPrice = min($prices);
                        }
                    ?>
                        <div class="pkg-card <?= $c['bg'] ?> border <?= $c['border'] ?> rounded-2xl p-6 relative overflow-hidden">
                            <!-- Top accent bar -->
                            <div class="absolute top-0 left-0 right-0 h-1.5 <?= $c['badge'] ?> rounded-t-2xl"></div>

                            <!-- Icon + Name -->
                            <div class="flex items-center gap-3 mb-4 mt-1">
                                <div class="<?= $c['icon_bg'] ?> w-12 h-12 rounded-xl flex items-center justify-center shadow-sm">
                                    <?php if ($name === 'Silver'): ?>
                                        <i class="fa-solid fa-star text-white text-lg"></i>
                                    <?php elseif ($name === 'Gold'): ?>
                                        <i class="fa-solid fa-crown text-white text-lg"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-gem text-white text-lg"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold <?= $c['accent'] ?>"><?= htmlspecialchars($name) ?></h3>
                                    <span class="text-xs text-gray-500">Package</span>
                                </div>
                            </div>

                            <!-- Description -->
                            <p class="text-sm text-gray-600 mb-5 leading-relaxed min-h-[40px]">
                                <?= htmlspecialchars($p['description'] ?: 'No description set.') ?>
                            </p>

                            <!-- Stats -->
                            <div class="flex gap-3 mb-5">
                                <div class="flex-1 bg-white/70 rounded-xl p-3 text-center border border-white/80">
                                    <div class="text-lg font-bold text-gray-800"><?= $venueCount ?></div>
                                    <div class="text-[10px] text-gray-500 uppercase tracking-wide">Venues</div>
                                </div>
                                <div class="flex-1 bg-white/70 rounded-xl p-3 text-center border border-white/80">
                                    <div class="text-lg font-bold text-gray-800"><?= $serviceCount ?></div>
                                    <div class="text-[10px] text-gray-500 uppercase tracking-wide">Services</div>
                                </div>
                                <div class="flex-1 bg-white/70 rounded-xl p-3 text-center border border-white/80">
                                    <div class="text-lg font-bold <?= $c['accent'] ?>">
                                        <?= $minPrice > 0 ? number_format($minPrice) : '—' ?>
                                    </div>
                                    <div class="text-[10px] text-gray-500 uppercase tracking-wide">Min Price</div>
                                </div>
                            </div>

                            <!-- Actions (show on hover) -->
                            <div class="flex gap-2">
                                <a href="packages.php?action=edit&id=<?= $p['id'] ?>"
                                    class="flex-1 text-center px-3 py-2 text-xs font-semibold rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                </a>
                                <a href="packages.php?action=delete&id=<?= $p['id'] ?>"
                                    onclick="return confirm('Delete this package? This will also remove all venue prices and service assignments.')"
                                    class="flex-1 text-center px-3 py-2 text-xs font-semibold rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
                                    <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($packages)): ?>
                        <div class="col-span-full">
                            <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fa-solid fa-box-open text-purple-400 text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">No Packages Yet</h3>
                                <p class="text-sm text-gray-400 mb-5">Create your first package to get started.</p>
                                <a href="packages.php?action=add"
                                    class="inline-flex items-center gap-2 bg-purple-600 text-white px-5 py-2.5 rounded-xl hover:bg-purple-700 transition font-medium text-sm">
                                    <i class="fa-solid fa-plus text-xs"></i> Add Package
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Edit Card Form (appears when editing) -->
                <?php if ($action === 'edit' && $editPackage): ?>
                    <?php
                        $editName = $editPackage['name'];
                        $ec = $pkgColors[$editName] ?? $pkgColors['Silver'];
                    ?>
                    <div class="edit-card bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="<?= $ec['icon_bg'] ?> w-10 h-10 rounded-xl flex items-center justify-center shadow-sm">
                                    <?php if ($editName === 'Silver'): ?>
                                        <i class="fa-solid fa-star text-white text-sm"></i>
                                    <?php elseif ($editName === 'Gold'): ?>
                                        <i class="fa-solid fa-crown text-white text-sm"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-gem text-white text-sm"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800">Edit Package</h3>
                                    <p class="text-xs text-gray-500">Update <?= htmlspecialchars($editName) ?> package details</p>
                                </div>
                            </div>
                            <a href="packages.php" class="text-gray-400 hover:text-gray-600 text-xl leading-none">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        </div>

                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="id" value="<?= $editPackage['id'] ?>">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Package Name</label>
                                    <select name="name" required
                                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50">
                                        <option value="Silver" <?= $editPackage['name'] === 'Silver' ? 'selected' : '' ?>>Silver</option>
                                        <option value="Gold" <?= $editPackage['name'] === 'Gold' ? 'selected' : '' ?>>Gold</option>
                                        <option value="Diamond" <?= $editPackage['name'] === 'Diamond' ? 'selected' : '' ?>>Diamond</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                                    <textarea name="description" rows="1"
                                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50 resize-none"><?= htmlspecialchars($editPackage['description'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Venue Pricing -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fa-solid fa-location-dot mr-1 text-purple-500"></i> Venue Prices (MMK)
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 p-3 border border-gray-100 rounded-xl bg-gray-50/50">
                                    <?php if (!empty($venuesList)): ?>
                                        <?php foreach ($venuesList as $v): ?>
                                            <?php $existingPrice = isset($vpData[$editPackage['id']][$v['id']]) ? $vpData[$editPackage['id']][$v['id']] : ''; ?>
                                            <div class="flex items-center gap-2 bg-white rounded-lg px-3 py-2 border border-gray-100">
                                                <label class="text-xs text-gray-600 truncate flex-1"><?= htmlspecialchars($v['name']) ?></label>
                                                <input type="number" name="venue_price[<?= $v['id'] ?>]" min="0" step="0.01"
                                                    value="<?= $existingPrice ?>" placeholder="0"
                                                    class="w-24 px-2 py-1.5 rounded-lg border border-gray-200 focus:outline-none focus:border-purple-400 bg-white text-xs text-right">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400 col-span-full">No venues available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Services per Event -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fa-solid fa-concierge-bell mr-1 text-purple-500"></i> Services per Event
                                </label>
                                <div class="space-y-3 p-3 border border-gray-100 rounded-xl bg-gray-50/50 max-h-48 overflow-y-auto">
                                    <?php if (!empty($eventsList) && !empty($servicesList)): ?>
                                        <?php foreach ($eventsList as $ev): ?>
                                            <?php $selectedServices = $epsData[$editPackage['id']][$ev['id']] ?? []; ?>
                                            <div class="bg-white rounded-lg p-3 border border-gray-100">
                                                <div class="text-xs font-semibold text-gray-700 mb-2"><?= htmlspecialchars($ev['event_name']) ?></div>
                                                <div class="flex flex-wrap gap-2">
                                                    <?php foreach ($servicesList as $svc): ?>
                                                        <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                                                            <input type="checkbox" name="event_services[<?= $ev['id'] ?>][]"
                                                                value="<?= $svc['id'] ?>"
                                                                <?= in_array($svc['id'], $selectedServices) ? 'checked' : '' ?>
                                                                class="rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                                                            <?= htmlspecialchars($svc['service_name']) ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400">Create events and services first.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit"
                                    class="bg-purple-600 text-white px-6 py-2.5 rounded-xl font-semibold hover:bg-purple-700 transition text-sm flex items-center gap-2">
                                    <i class="fa-solid fa-save text-xs"></i> Update Package
                                </button>
                                <a href="packages.php"
                                    class="px-6 py-2.5 rounded-xl font-medium text-sm text-gray-600 hover:bg-gray-100 transition">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Add Card Form (appears when adding) -->
                <?php if ($action === 'add'): ?>
                    <div class="edit-card bg-white rounded-2xl border border-purple-200 shadow-sm p-6 mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="bg-purple-500 w-10 h-10 rounded-xl flex items-center justify-center shadow-sm">
                                    <i class="fa-solid fa-plus text-white text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800">Add New Package</h3>
                                    <p class="text-xs text-gray-500">Create a new package tier</p>
                                </div>
                            </div>
                            <a href="packages.php" class="text-gray-400 hover:text-gray-600 text-xl leading-none">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        </div>

                        <form method="POST" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Package Name</label>
                                    <select name="name" required
                                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50">
                                        <option value="">— Select Package —</option>
                                        <option value="Silver">Silver</option>
                                        <option value="Gold">Gold</option>
                                        <option value="Diamond">Diamond</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                                    <textarea name="description" rows="1" placeholder="Enter package description"
                                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50 resize-none"></textarea>
                                </div>
                            </div>

                            <!-- Venue Pricing -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fa-solid fa-location-dot mr-1 text-purple-500"></i> Venue Prices (MMK)
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 p-3 border border-gray-100 rounded-xl bg-gray-50/50">
                                    <?php if (!empty($venuesList)): ?>
                                        <?php foreach ($venuesList as $v): ?>
                                            <div class="flex items-center gap-2 bg-white rounded-lg px-3 py-2 border border-gray-100">
                                                <label class="text-xs text-gray-600 truncate flex-1"><?= htmlspecialchars($v['name']) ?></label>
                                                <input type="number" name="venue_price[<?= $v['id'] ?>]" min="0" step="0.01"
                                                    placeholder="0"
                                                    class="w-24 px-2 py-1.5 rounded-lg border border-gray-200 focus:outline-none focus:border-purple-400 bg-white text-xs text-right">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400 col-span-full">No venues available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Services per Event -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fa-solid fa-concierge-bell mr-1 text-purple-500"></i> Services per Event
                                </label>
                                <div class="space-y-3 p-3 border border-gray-100 rounded-xl bg-gray-50/50 max-h-48 overflow-y-auto">
                                    <?php if (!empty($eventsList) && !empty($servicesList)): ?>
                                        <?php foreach ($eventsList as $ev): ?>
                                            <div class="bg-white rounded-lg p-3 border border-gray-100">
                                                <div class="text-xs font-semibold text-gray-700 mb-2"><?= htmlspecialchars($ev['event_name']) ?></div>
                                                <div class="flex flex-wrap gap-2">
                                                    <?php foreach ($servicesList as $svc): ?>
                                                        <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                                                            <input type="checkbox" name="event_services[<?= $ev['id'] ?>][]"
                                                                value="<?= $svc['id'] ?>"
                                                                class="rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                                                            <?= htmlspecialchars($svc['service_name']) ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400">Create events and services first.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit"
                                    class="bg-purple-600 text-white px-6 py-2.5 rounded-xl font-semibold hover:bg-purple-700 transition text-sm flex items-center gap-2">
                                    <i class="fa-solid fa-plus text-xs"></i> Create Package
                                </button>
                                <a href="packages.php"
                                    class="px-6 py-2.5 rounded-xl font-medium text-sm text-gray-600 hover:bg-gray-100 transition">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <script>
                    function closeModal() { window.location.href = 'packages.php'; }

                    document.getElementById('packageSearch').addEventListener('input', function () {
                        const q = this.value.toLowerCase();
                        document.querySelectorAll('.pkg-card').forEach(card => {
                            const text = card.textContent.toLowerCase();
                            card.style.display = text.includes(q) ? '' : 'none';
                        });
                    });
                </script>
            </main>
        </div>
    </div>
</body>
</html>
