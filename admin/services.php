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

// DELETE
if ($action === 'delete' && $serviceId > 0) {
    $stmt = $conn->prepare("DELETE FROM services WHERE id=?");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $stmt->close();
    header("Location: services.php");
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

    header("Location: services.php");
    exit();
}

// Fetch all services
$services = $conn->query("SELECT * FROM services ORDER BY id")->fetch_all(MYSQLI_ASSOC);
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

        <div class="flex-1 flex flex-col lg:ml-64">

            <?php include 'admin_header.php'; ?>

            <main class="flex-1 p-6 overflow-y-auto">

                <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
                    <div class="relative flex-1 max-w-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="serviceSearch" placeholder="Search services..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-400 bg-white">
                    </div>
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
                                <!-- <th class="text-left px-6 py-4 font-semibold text-gray-600">#</th> -->
                                <th class="text-left px-6 py-4 font-semibold text-gray-600">Service Name</th>
                                <th class="text-center px-6 py-4 font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody" class="divide-y divide-gray-100">
                            <?php foreach ($services as $s): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <!-- <td class="px-6 py-4 text-gray-500"><?= $s['id'] ?></td> -->
                                    <td class="px-6 py-4 font-medium text-gray-800">
                                        <?= htmlspecialchars($s['service_name']) ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="services.php?action=edit&id=<?= $s['id'] ?>"
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
                            <tr class="no-results hidden">
                                <td colspan="2" class="px-6 py-10 text-center text-gray-400 text-sm">No services found matching your search.</td>
                            </tr>
                        </tbody>
                    </table>
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
                    function closeModal() { window.location.href = 'services.php'; }

                    <?php if ($action === 'add' || $action === 'edit'): ?>
                        document.addEventListener('DOMContentLoaded', function () {
                            const m = document.getElementById('serviceModal');
                            if (m) m.classList.remove('hidden');
                        });
                    <?php endif; ?>

                    document.getElementById('serviceSearch').addEventListener('input', function () {
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

            </main>

        </div>

    </div>
</body>

</html>