<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$action = $_GET['action'] ?? 'list';
$editEvent = null;
$editGallery = [];
$searchEvent = $_GET['search'] ?? '';

$queryParams = [];
if ($searchEvent !== '')
    $queryParams['search'] = $searchEvent;
$redirectQuery = $queryParams ? '?' . http_build_query($queryParams) : '';

// DELETE
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM events WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: events.php$redirectQuery");
    exit();
}

// DELETE GALLERY IMAGE
if (isset($_GET['delete_gallery'])) {
    $galleryId = (int) $_GET['delete_gallery'];
    if ($galleryId > 0) {
        $stmt = $conn->prepare("SELECT image_path FROM event_gallery WHERE id=?");
        $stmt->bind_param("i", $galleryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $galleryImage = $result->fetch_assoc();
        $stmt->close();

        if ($galleryImage) {
            $filePath = __DIR__ . '/../' . ltrim($galleryImage['image_path'], './');
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $stmt = $conn->prepare("DELETE FROM event_gallery WHERE id=?");
            $stmt->bind_param("i", $galleryId);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: events.php$redirectQuery");
    exit();
}

// EDIT - fetch event data
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editEvent = $result->fetch_assoc();
    $stmt->close();
    if (!$editEvent) {
        header("Location: events.php");
        exit();
    }

    // Fetch gallery images for edit modal
    $stmt = $conn->prepare("SELECT * FROM event_gallery WHERE event_id=? ORDER BY id ASC");
    $stmt->bind_param("i", $editEvent['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $editGallery = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// POST - create or update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $type = $_POST['type'];
    $description = $_POST['description'];
    $venueId = isset($_POST['venue_id']) ? (int) $_POST['venue_id'] : 0;
    $eventId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    // Dynamic venue creation
    $dynamicVenueName = $_POST['dynamic_venue_name'] ?? '';
    if ($venueId === 0 && $dynamicVenueName !== '') {
        $vAddr = $_POST['dynamic_venue_address'] ?? '';
        $vCap = (int) ($_POST['dynamic_venue_capacity'] ?? 0);
        $vPrice = (float) ($_POST['dynamic_venue_price'] ?? 0);
        $vImg = null;
        if (isset($_FILES['dynamic_venue_image']) && $_FILES['dynamic_venue_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/images/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);
            $ext = pathinfo($_FILES['dynamic_venue_image']['name'], PATHINFO_EXTENSION);
            $filename = 'venue_dyn_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['dynamic_venue_image']['tmp_name'], $uploadDir . $filename);
            $vImg = '../assets/images/' . $filename;
        }
        $stmt = $conn->prepare("INSERT INTO venues (name, address, capacity, price, image_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssids", $dynamicVenueName, $vAddr, $vCap, $vPrice, $vImg);
        $stmt->execute();
        $venueId = $stmt->insert_id;
        $stmt->close();
    }

    $imagePath = $_POST['existing_image'] ?? '../assets/images/slide1.png';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'event_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename);
        $imagePath = '../assets/images/' . $filename;
    }

    if ($eventId > 0) {
        $stmt = $conn->prepare("UPDATE events SET event_name=?, description=?, image=? WHERE id=?");
        $stmt->bind_param("sssi", $title, $description, $imagePath, $eventId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO events (event_name, description, image) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $title, $description, $imagePath);
        $stmt->execute();
        $eventId = $conn->insert_id;
        $stmt->close();
    }

    // Handle gallery image uploads
    if (isset($_FILES['gallery_images'])) {
        $uploadDir = '../assets/images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $files = $_FILES['gallery_images'];
        $fileCount = count($files['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $filename = 'gallery_' . $eventId . '_' . time() . '_' . $i . '.' . $ext;
                move_uploaded_file($files['tmp_name'][$i], $uploadDir . $filename);

                $stmt = $conn->prepare("INSERT INTO event_gallery (event_id, image_path) VALUES (?, ?)");
                $filePath = '../assets/images/' . $filename;
                $stmt->bind_param("is", $eventId, $filePath);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    header("Location: events.php$redirectQuery");
    exit();
}

// Fetch events for listing (JOIN with venues)
$searchFilter = $searchEvent !== '' ? "WHERE e.event_name LIKE '%" . $conn->real_escape_string($searchEvent) . "%'" : "";
$result = $conn->query("
SELECT 
    e.*,
    GROUP_CONCAT(v.name SEPARATOR ', ') AS venue_name,
    GROUP_CONCAT(v.address SEPARATOR ', ') AS venue_address,
    GROUP_CONCAT(v.capacity SEPARATOR ', ') AS venue_capacity
FROM events e
LEFT JOIN venues v 
    ON v.event_id = e.id
$searchFilter
GROUP BY e.id
ORDER BY e.id
");
$events = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Pagination
$ePage = isset($_GET['e_page']) ? max(1, (int)$_GET['e_page']) : 1;
$ePerPage = 8;
$eTotal = count($events);
$eTotalPages = ceil($eTotal / $ePerPage);
$eOffset = ($ePage - 1) * $ePerPage;
$paginatedEvents = array_slice($events, $eOffset, $ePerPage);

// Fetch gallery counts for table
$galleryCounts = [];
$gResult = $conn->query("SELECT event_id, COUNT(*) as count FROM event_gallery GROUP BY event_id");
if ($gResult) {
    while ($row = $gResult->fetch_assoc()) {
        $galleryCounts[$row['event_id']] = $row['count'];
    }
}

// Fetch package counts per event from event_package_services
$pkgCounts = [];
$pResult = $conn->query("SELECT event_id, COUNT(DISTINCT package_id) as count FROM event_package_services GROUP BY event_id");
if ($pResult) {
    while ($row = $pResult->fetch_assoc()) {
        $pkgCounts[$row['event_id']] = $row['count'];
    }
}

// Fetch all venues for the form dropdown
$allVenues = [];
$vResult = $conn->query("SELECT id, name FROM venues ORDER BY name ASC");
if ($vResult)
    $allVenues = $vResult->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Admin</title>
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
            padding: 1.5rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="searchInput" placeholder="Search events..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-400 bg-white">
                    </div>
                    <div class="flex gap-3">
                        <a href="events.php?action=add"
                            class="bg-purple-600 text-white px-5 py-2.5 rounded-xl hover:bg-purple-700 transition flex items-center gap-2 font-medium text-sm shadow-sm">
                            <i class="fa-solid fa-plus text-xs"></i> Add Event
                        </a>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-center px-6 py-4 font-semibold text-gray-600 w-12">No.</th>
                                <th class="text-left px-6 py-4 font-semibold text-gray-600">Image</th>
                                <th class="text-left px-6 py-4 font-semibold text-gray-600">Name</th>
                                <th class="text-left px-6 py-4 font-semibold text-gray-600">Description</th>
                                <th class="text-center px-6 py-4 font-semibold text-gray-600">Gallery</th>
                                <th class="text-center px-6 py-4 font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody" class="divide-y divide-gray-100">
                            <?php $eIndex = $eOffset; ?>
                            <?php foreach ($paginatedEvents as $event): $eIndex++; ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 text-center text-gray-500"><?= $eIndex ?></td>
                                    <td class="px-6 py-4">
                                        <img src="<?= $event['image'] ?>" class="w-14 h-14 rounded-lg object-cover">
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-800"><?= $event['event_name'] ?></td>
                                    <td class="px-6 py-4 text-gray-500 max-w-xs truncate">
                                        <?= htmlspecialchars($event['description']) ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <?php $gc = $galleryCounts[$event['id']] ?? 0; ?>
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium <?= $gc > 0 ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-500' ?>">
                                            <i class="fa-solid fa-image"></i> <?= $gc ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="events.php?action=edit&id=<?= $event['id'] ?>"
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition">
                                                <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                            </a>
                                            <a href="events.php?delete=<?= $event['id'] ?><?= $searchEvent ? '&search=' . urlencode($searchEvent) : '' ?>"
                                                onclick="return confirm('Delete this event?')"
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
                                                <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="no-results hidden">
                                <td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">No events found matching your search.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3 text-sm text-gray-500 border-t border-gray-100">
                    Total: <span class="font-semibold text-gray-700"><?= $eTotal ?></span> events
                </div>

                <?php if ($eTotalPages > 1): ?>
                <div class="flex justify-center items-center gap-2 px-6 py-4 border-t border-gray-100">
                    <a href="?e_page=<?= max(1, $ePage-1) ?><?= $searchEvent ? '&search='.urlencode($searchEvent) : '' ?>"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $ePage <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                        <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                    </a>
                    <?php for ($i = 1; $i <= $eTotalPages; $i++): ?>
                    <a href="?e_page=<?= $i ?><?= $searchEvent ? '&search='.urlencode($searchEvent) : '' ?>"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $i == $ePage ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    <a href="?e_page=<?= min($eTotalPages, $ePage+1) ?><?= $searchEvent ? '&search='.urlencode($searchEvent) : '' ?>"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $ePage >= $eTotalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                        Next <i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Modal overlay -->
                <div id="eventModal"
                    class="modal-overlay <?= ($action === 'add' || $action === 'edit') ? '' : 'hidden' ?>">
                    <div class="modal-content relative">
                        <div class="text-center mb-4">
                            <h2 class="text-lg font-bold text-gray-800">
                                <?= $action === 'add' ? 'Add Event' : 'Edit Event' ?></h2>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <?= $action === 'add' ? 'Create a new event listing' : 'Update event details' ?></p>
                            <button onclick="closeModal()"
                                class="text-gray-400 hover:text-gray-600 text-lg leading-none absolute top-3 right-3">&times;</button>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="space-y-2">
                            <?php if ($action === 'edit' && $editEvent): ?>
                                <input type="hidden" name="id" value="<?= $editEvent['id'] ?>">
                                <input type="hidden" name="existing_image" value="<?= $editEvent['image'] ?>">
                            <?php endif; ?>
                            <input type="hidden" name="type" value="general">

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Event Name</label>
                                <input type="text" name="title" required
                                    value="<?= $action === 'edit' && $editEvent ? htmlspecialchars($editEvent['event_name']) : '' ?>"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50 text-sm">
                            </div>



                            <!-- <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Venue</label>
                                <select name="venue_id" id="venueSelect" onchange="toggleDynamicVenue()"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50">
                                    <option value="">— Select Venue —</option>
                                    <?php if (!empty($allVenues)): ?>
                                        <?php foreach ($allVenues as $v): ?>
                                            <option value="<?= $v['id'] ?>" <?= $action === 'edit' && $editEvent && $editEvent['venue_id'] == $v['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($v['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <option value="new">+ Add New Venue</option>
                                </select>
                            </div> -->

                            <div id="dynamicVenueFields"
                                class="hidden p-3 border border-dashed border-purple-300 rounded-xl bg-purple-50/50 space-y-2">
                                <p class="text-xs font-semibold text-purple-600">New Venue Details</p>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Venue Name</label>
                                    <input type="text" name="dynamic_venue_name" id="dynamicVenueName"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:border-purple-400 bg-white text-sm">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Address</label>
                                        <input type="text" name="dynamic_venue_address"
                                            class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:border-purple-400 bg-white text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Capacity</label>
                                        <input type="number" name="dynamic_venue_capacity" min="1"
                                            class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:border-purple-400 bg-white text-sm">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Price (MMK)</label>
                                        <input type="number" name="dynamic_venue_price" min="0" step="0.01"
                                            class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:border-purple-400 bg-white text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Image</label>
                                        <input type="file" name="dynamic_venue_image" accept="image/*"
                                            class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-purple-100 file:text-purple-700 file:font-semibold file:text-xs hover:file:bg-purple-200">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Description</label>
                                <textarea name="description" rows="2" required
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50 resize-none text-sm"><?= $action === 'edit' && $editEvent ? htmlspecialchars($editEvent['description']) : '' ?></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Event Image</label>
                                <?php if ($action === 'edit' && $editEvent && $editEvent['image']): ?>
                                    <img src="<?= $editEvent['image'] ?>" class="w-8 h-8 rounded object-cover mb-1 inline-block">
                                <?php endif; ?>
                                <input type="file" name="image" accept="image/*"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50 text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:bg-purple-50 file:text-purple-700 file:font-semibold file:text-xs hover:file:bg-purple-100">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Images</label>
                                <?php if ($action === 'edit' && !empty($editGallery)): ?>
                                    <div class="flex flex-wrap gap-2 mb-2">
                                        <?php foreach ($editGallery as $photo): ?>
                                            <div class="relative group">
                                                <img src="<?= $photo['image_path'] ?>"
                                                    class="w-8 h-8 rounded-lg object-cover border">
                                                <a href="events.php?delete_gallery=<?= $photo['id'] ?>"
                                                    onclick="return confirm('Delete this gallery image?')"
                                                    class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[10px] opacity-0 group-hover:opacity-100 transition">
                                                    &times;
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="gallery_images[]" multiple accept="image/*"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:border-purple-400 bg-gray-50/50 text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:bg-purple-50 file:text-purple-700 file:font-semibold file:text-xs hover:file:bg-purple-100">
                                <p class="text-[11px] text-gray-400 mt-0.5">Upload multiple gallery images</p>
                            </div>

                            <div class="flex items-center justify-center gap-3 pt-1">
                                <button type="submit"
                                    class="bg-purple-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-purple-700 transition text-sm">
                                    <i class="fa-solid <?= $action === 'add' ? 'fa-plus' : 'fa-save' ?> mr-1"></i>
                                    <?= $action === 'add' ? 'Create Event' : 'Update Event' ?>
                                </button>
                                <button type="button" onclick="closeModal()"
                                    class="text-gray-500 hover:text-gray-700 font-medium text-xs">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function toggleDynamicVenue() {
                        const sel = document.getElementById('venueSelect');
                        const fields = document.getElementById('dynamicVenueFields');
                        if (sel && fields) {
                            fields.classList.toggle('hidden', sel.value !== 'new');
                            if (sel.value === 'new') sel.removeAttribute('required');
                            else sel.setAttribute('required', 'required');
                        }
                    }
                    document.addEventListener('DOMContentLoaded', toggleDynamicVenue);

                    function closeModal() {
                        window.location.href = 'events.php<?= $redirectQuery ?>';
                    }

                    <?php if ($action === 'add' || $action === 'edit'): ?>
                        document.addEventListener('DOMContentLoaded', function () {
                            const modal = document.getElementById('eventModal');
                            if (modal) modal.classList.remove('hidden');
                        });
                    <?php endif; ?>
                </script>

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