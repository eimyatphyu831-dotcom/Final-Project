<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$action = $_GET['action'] ?? 'list';
$venueId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editVenue = null;
$search = $_GET['search'] ?? '';
$filterEventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

$queryParams = [];
if ($filterEventId > 0) $queryParams['event_id'] = $filterEventId;
if ($search !== '') $queryParams['search'] = $search;
$redirectQuery = $queryParams ? '?' . http_build_query($queryParams) : '';

// DELETE
if ($action === 'delete' && $venueId > 0) {
    $stmt = $conn->prepare("SELECT image_path FROM venues WHERE id=?");
    $stmt->bind_param("i", $venueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $del = $result->fetch_assoc();
    $stmt->close();

    if ($del && $del['image_path']) {
        $fp = __DIR__ . '/../' . ltrim($del['image_path'], './');
        if (file_exists($fp))
            unlink($fp);
    }

    $stmt = $conn->prepare("DELETE FROM venues WHERE id=?");
    $stmt->bind_param("i", $venueId);
    $stmt->execute();
    $stmt->close();

    header("Location: venues.php$redirectQuery");
    exit();
}

// EDIT - fetch venue data
if ($action === 'edit' && $venueId > 0) {
    $stmt = $conn->prepare("SELECT * FROM venues WHERE id=?");
    $stmt->bind_param("i", $venueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editVenue = $result->fetch_assoc();
    $stmt->close();
    if (!$editVenue) {
        header("Location: venues.php");
        exit();
    }
}

// Fetch events for dropdown
$eventsResult = $conn->query("SELECT id, event_name FROM events ORDER BY event_name");
$eventsList = $eventsResult ? $eventsResult->fetch_all(MYSQLI_ASSOC) : [];

// POST - create or update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $capacity = (int) $_POST['capacity'];
    // $price = (float) $_POST['price'];
    $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $editId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    $imagePath = $_POST['existing_image'] ?? null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/images/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'venue_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename);
        $imagePath = '../assets/images/' . $filename;
    }

    if ($editId > 0) {
        if ($imagePath) {
            $stmt = $conn->prepare("UPDATE venues SET name=?, address=?, capacity=?, image_path=?, event_id=? WHERE id=?");
            $stmt->bind_param("ssisii", $name, $address, $capacity, $imagePath, $eventId, $editId);
        } else {
            $stmt = $conn->prepare("UPDATE venues SET name=?, address=?, capacity=?, event_id=? WHERE id=?");
            $stmt->bind_param("ssiii", $name, $address, $capacity, $price, $eventId, $editId);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO venues (name, address, capacity,  image_path, event_id) VALUES ( ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisi", $name, $address, $capacity,  $imagePath, $eventId);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: venues.php$redirectQuery");
    exit();
}

// Fetch venues with event name (optionally filtered by event)
$eventFilter = $filterEventId > 0 ? "WHERE v.event_id = $filterEventId" : "";
$result = $conn->query("SELECT v.*, COALESCE(e.event_name, '—') AS event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id $eventFilter ORDER BY v.id");
$venues = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Fallback sample data when DB is empty
// if (empty($venues)) {
//     $venues = [
//         ['id' => 1, 'name' => 'Grand Ballroom', 'address' => '123 Main St, Makati City', 'capacity' => 500, 'price' => 150000.00, 'image_path' => '../assets/images/venue1.png'],
//         ['id' => 2, 'name' => 'Garden Pavilion', 'address' => '45 Park Ave, Quezon City', 'capacity' => 250, 'price' => 85000.00, 'image_path' => '../assets/images/venue2.png'],
//         ['id' => 3, 'name' => 'Skyline Rooftop', 'address' => '88 Ayala Ave, BGC, Taguig', 'capacity' => 180, 'price' => 120000.00, 'image_path' => '../assets/images/venue3.png'],
//         ['id' => 4, 'name' => 'Beachfront Resort', 'address' => 'Coastal Rd, Batangas', 'capacity' => 400, 'price' => 200000.00, 'image_path' => '../assets/images/venue4.png'],
//         ['id' => 5, 'name' => 'Intimate Chapel', 'address' => '22 Church St, San Juan', 'capacity' => 80, 'price' => 55000.00, 'image_path' => '../assets/images/venue5.png'],
//     ];
// }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Venues</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        h1, h2, h3, h4, h5, h6 {
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

        <div class="flex-1 flex flex-col ml-64">

            <?php include 'admin_header.php'; ?>

            <main class="flex-1 p-6 overflow-y-auto">

                <div class="flex justify-between items-center mb-4">
                    <!-- <h2 class="text-2xl font-bold text-gray-800">Venues</h2> -->
                     <div class="flex">
                    <div class="flex gap-3">
                        <a href="venues.php?action=add<?= $filterEventId ? "&event_id=$filterEventId" : "" ?>"
                            class="bg-purple-600 text-white px-5 py-2 rounded-xl hover:bg-purple-700">+ Add Venue</a>
                    </div>
                </div>

                <form method="GET" class="mb-6 flex gap-4 items-end">
                    <!-- <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Filter by Event</label>
                        <select name="event_id" onchange="this.form.submit()"
                            class="px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-white min-w-[220px]">
                            <option value="0">All Events</option>
                            <?php foreach ($eventsList as $ev): ?>
                                <option value="<?= $ev['id'] ?>" <?= $filterEventId === (int)$ev['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ev['event_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div> -->
                    <div>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search venue..." class="px-4 py-2.5 border rounded-xl w-64 mt-4" onchange="this.form.submit()">
                    </div>
                    <?php if ($filterEventId > 0): ?>
                        <a href="venues.php" class="text-sm text-purple-600 hover:underline self-center">Clear filter</a>
                    <?php endif; ?>
                </form>
                </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-6 py-4 font-semibold text-gray-600">Image</th>
                            <th class="text-left px-6 py-4 font-semibold text-gray-600">Name</th>
                            <th class="text-left px-6 py-4 font-semibold text-gray-600">Event</th>
                            <th class="text-left px-6 py-4 font-semibold text-gray-600">Address</th>
                            <th class="text-left px-6 py-4 font-semibold text-gray-600">Capacity</th>
                            <!-- <th class="text-left px-6 py-4 font-semibold text-gray-600">Price</th> -->
                            <th class="text-center px-6 py-4 font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($venues as $v): ?>
                            <?php if ($search && stripos($v['name'] . $v['address'], $search) === false)
                                continue; ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <img src="<?= $v['image_path'] ?: '../assets/images/venue1.png' ?>"
                                        class="w-14 h-14 rounded-lg object-cover">
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-800"><?= $v['name'] ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-700">
                                        <?= htmlspecialchars($v['event_name'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500"><?= $v['address'] ?></td>
                                <td class="px-6 py-4 text-gray-500">
                                    <div class="inline-flex items-center gap-1">
                                     <span><?= $v['capacity'] ?></span>
                                    <span>Guests</span>
                                    </div>
                                </td>
                                <!-- <td class="px-6 py-4 text-gray-500"><?= number_format($v['price'], 2) ?> MMK</td> -->
                                <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-2">

                                 <a href="venues.php?action=edit&id=<?= $v['id'] ?><?= $filterEventId ? "&event_id=$filterEventId" : "" ?>"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                            <span>Edit</span>
                                        </a>
                                
                                        <a href="venues.php?action=delete&id=<?= $v['id'] ?><?= $filterEventId ? "&event_id=$filterEventId" : "" ?>"
                                            onclick="return confirm('Delete this venue?')"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
                                            <i class="fa-solid fa-trash-can"></i>
                                            <span>Delete</span>
                                        </a>
                                
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- View Modal -->
            <div id="viewModal" class="modal-overlay <?= $action === 'view' ? '' : 'hidden' ?>">
                <div class="modal-content">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Venue Details</h2>
                        <button onclick="window.location.href='venues.php<?= $redirectQuery ?>'"
                            class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                    </div>
                    <?php
                    $viewVenue = null;
                    if ($action === 'view' && $venueId > 0) {
                        foreach ($venues as $v) {
                            if ($v['id'] == $venueId) {
                                $viewVenue = $v;
                                break;
                            }
                        }
                    }
                    ?>
                    <?php if ($viewVenue): ?>
                        <div class="space-y-4">
                            <div class="flex justify-center">
                                <img src="<?= $viewVenue['image_path'] ?: '../assets/images/venue1.png' ?>"
                                    class="w-48 h-48 rounded-xl object-cover shadow">
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div><span class="font-semibold text-gray-600">Name</span>
                                    <p class="text-gray-800"><?= $viewVenue['name'] ?></p>
                                </div>
                                <div><span class="font-semibold text-gray-600">Event</span>
                                    <p class="text-gray-800"><?= htmlspecialchars($viewVenue['event_name'] ?? '—') ?></p>
                                </div>
                                <div><span class="font-semibold text-gray-600">Address</span>
                                    <p class="text-gray-800"><?= $viewVenue['address'] ?></p>
                                </div>
                                <div><span class="font-semibold text-gray-600">Capacity</span>
                                    <p class="text-gray-800"><?= $viewVenue['capacity'] ?> Guests</p>
                                </div>
                                <div><span class="font-semibold text-gray-600">Price</span>
                                    <p class="text-gray-800">MMK<?= number_format($viewVenue['price'], 2) ?></p>
                                </div>
                            </div>
                            <div class="text-center pt-2">
                                <button onclick="window.location.href='venues.php<?= $redirectQuery ?>'"
                                    class="px-6 py-2 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition">Close</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add/Edit Modal -->
            <div id="venueModal" class="modal-overlay <?= ($action === 'add' || $action === 'edit') ? '' : 'hidden' ?>">
                <div class="modal-content">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">
                                <?= $action === 'add' ? 'Add Venue' : 'Edit Venue' ?></h2>
                            <p class="text-sm text-gray-500 mt-0.5">
                                <?= $action === 'add' ? 'Create a new venue' : 'Update venue details' ?></p>
                        </div>
                        <button onclick="closeModal()"
                            class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <?php if ($action === 'edit' && $editVenue): ?>
                            <input type="hidden" name="id" value="<?= $editVenue['id'] ?>">
                            <input type="hidden" name="existing_image" value="<?= $editVenue['image_path'] ?>">
                        <?php endif; ?>
                        <?php if ($filterEventId > 0 && $action !== 'edit'): ?>
                            <input type="hidden" name="event_id" value="<?= $filterEventId ?>">
                        <?php endif; ?>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Venue Name</label>
                            <input type="text" name="name" required
                                value="<?= $action === 'edit' && $editVenue ? htmlspecialchars($editVenue['name']) : '' ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Event</label>
                            <?php if ($filterEventId > 0 && $action !== 'edit'): ?>
                                <?php foreach ($eventsList as $ev): ?>
                                    <?php if ($ev['id'] == $filterEventId): ?>
                                        <div class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600"><?= htmlspecialchars($ev['event_name']) ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <select name="event_id" required
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50">
                                <option value="">— Select Event —</option>
                                <?php foreach ($eventsList as $ev): ?>
                                    <option value="<?= $ev['id'] ?>"
                                    <?= ($action === 'edit' && $editVenue && $editVenue['event_id'] == $ev['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ev['event_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                            <input type="text" name="address" required
                                value="<?= $action === 'edit' && $editVenue ? htmlspecialchars($editVenue['address']) : '' ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Capacity (guests)</label>
                                <input type="number" name="capacity" required min="1"
                                    value="<?= $action === 'edit' && $editVenue ? $editVenue['capacity'] : '' ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50">
                            </div>
                            <!-- <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Price (MMK)</label>
                                <input type="number" name="price" required min="0" step="0.01"
                                    value="<?= $action === 'edit' && $editVenue ? $editVenue['price'] : '' ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50">
                            </div> -->
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Venue Image</label>
                            <?php if ($action === 'edit' && $editVenue && $editVenue['image_path']): ?>
                                <img src="<?= $editVenue['image_path'] ?>" class="w-24 h-24 rounded-lg object-cover mb-2">
                            <?php endif; ?>
                            <input type="file" name="image" accept="image/*"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-purple-50 file:text-purple-700 file:font-semibold file:text-sm hover:file:bg-purple-100">
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <button type="submit"
                                class="bg-purple-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-purple-700 transition">
                                <i class="fa-solid <?= $action === 'add' ? 'fa-plus' : 'fa-save' ?> mr-2"></i>
                                <?= $action === 'add' ? 'Create Venue' : 'Update Venue' ?>
                            </button>
                            <button type="button" onclick="closeModal()"
                                class="text-gray-500 hover:text-gray-700 font-medium text-sm">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function closeModal() { window.location.href = 'venues.php<?= $redirectQuery ?>'; }

                <?php if ($action === 'add' || $action === 'edit' || $action === 'view'): ?>
                    document.addEventListener('DOMContentLoaded', function () {
                        const m = document.getElementById('venueModal') || document.getElementById('viewModal');
                        if (m) m.classList.remove('hidden');
                    });
                <?php endif; ?>
            </script>

        </main>

    </div>

</div>
</body>

</html>