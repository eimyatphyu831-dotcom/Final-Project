<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/notification_helper.php';
require_once '../includes/auto_complete_bookings.php';
$statusFilter = $_GET['status'] ?? 'all';
$message = '';

// AJAX handler for confirm/cancel (no page reload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        exit;
    }
    if ($action === 'approve') {
        $conn->query("UPDATE bookings SET status='Confirmed' WHERE id=$id");
        if ($conn->affected_rows > 0) {
            $bk = $conn->query("SELECT b.user_id, e.event_name, b.event_date FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.id = $id")->fetch_assoc();
            if ($bk) {
                $dateStr = date('M j, Y', strtotime($bk['event_date']));
                createNotification($conn, $bk['user_id'], 'Booking Confirmed', "Your booking for {$bk['event_name']} on {$dateStr} has been confirmed.", '../users/my_bookings.php', 'user');
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No rows affected']);
        }
    } elseif ($action === 'cancel') {
        $reason = trim($_POST['reason'] ?? '');
        $reasonText = $reason ?: 'No reason provided';
        $conn->query("UPDATE bookings SET status='Cancelled' WHERE id=$id");
        if ($conn->affected_rows > 0) {
            $bk = $conn->query("SELECT b.user_id, e.event_name, b.event_date FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.id = $id")->fetch_assoc();
            if ($bk) {
                $dateStr = date('M j, Y', strtotime($bk['event_date']));
                createNotification($conn, $bk['user_id'], 'Booking Cancelled', "Your booking for {$bk['event_name']} on {$dateStr} has been cancelled. Reason: {$reasonText}", '../users/my_bookings.php', 'user');
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No rows affected']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Cancel with reason via POST (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $id = (int) $_POST['booking_id'];
    $reason = trim($_POST['cancel_reason'] ?? '');
    $reasonText = $reason ?: 'No reason provided';
    $conn->query("UPDATE bookings SET status='Cancelled' WHERE id=$id");
    $bk = $conn->query("SELECT b.user_id, e.event_name, b.event_date, u.name AS customer_name FROM bookings b JOIN events e ON b.event_id = e.id JOIN users u ON b.user_id = u.id WHERE b.id = $id")->fetch_assoc();
    if ($bk) {
        $dateStr = date('M j, Y', strtotime($bk['event_date']));
        createNotification($conn, $bk['user_id'], 'Booking Cancelled', "Your booking for {$bk['event_name']} on {$dateStr} has been cancelled. Reason: {$reasonText}", '../users/my_bookings.php', 'user');
    }
    $message = "Booking cancelled with reason.";
    header("Location: bookings.php?status=" . urlencode($statusFilter));
    exit();
}

// Approve / Cancel / Delete actions via GET (fallback for non-JS)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    if ($_GET['action'] === 'approve') {
        $conn->query("UPDATE bookings SET status='Confirmed' WHERE id=$id");
        $bk = $conn->query("SELECT b.user_id, e.event_name, b.event_date, u.name AS customer_name FROM bookings b JOIN events e ON b.event_id = e.id JOIN users u ON b.user_id = u.id WHERE b.id = $id")->fetch_assoc();
        if ($bk) {
            $dateStr = date('M j, Y', strtotime($bk['event_date']));
            createNotification($conn, $bk['user_id'], 'Booking Confirmed', "Your booking for {$bk['event_name']} on {$dateStr} has been confirmed.", '../users/my_bookings.php', 'user');
        }
        $message = "Booking confirmed!";
    } elseif ($_GET['action'] === 'cancel') {
        $conn->query("UPDATE bookings SET status='Cancelled' WHERE id=$id");
        $bk = $conn->query("SELECT b.user_id, e.event_name, b.event_date, u.name AS customer_name FROM bookings b JOIN events e ON b.event_id = e.id JOIN users u ON b.user_id = u.id WHERE b.id = $id")->fetch_assoc();
        if ($bk) {
            $dateStr = date('M j, Y', strtotime($bk['event_date']));
            createNotification($conn, $bk['user_id'], 'Booking Cancelled', "Your booking for {$bk['event_name']} on {$dateStr} has been cancelled.", '../users/my_bookings.php', 'user');
        }
        $message = "Booking cancelled.";
    } elseif ($_GET['action'] === 'delete') {
        $conn->query("DELETE FROM bookings WHERE id=$id");
        $message = "Booking  deleted.";
    }
}

// Fetch from DB
$bookings = [];
$query = "SELECT b.id, b.event_date, b.total_cost, b.status, b.created_at, b.paymentmethods_id, b.receipt_image,
                  b.time_slot_id, ts.slot_name AS time_slot_name,
                  u.name AS customer_name, u.email,
                  e.event_name,
                  p.name AS package_name,
                  v.name AS venue_name,
                  pm.payment_name,
                  t.name AS team_name
           FROM bookings b
           JOIN users u ON b.user_id = u.id
           JOIN events e ON b.event_id = e.id
           JOIN packages p ON b.package_id = p.id
           JOIN venues v ON b.venue_id = v.id
           LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
           LEFT JOIN payment_methods pm ON b.paymentmethods_id = pm.id
           LEFT JOIN teams t ON b.team_id = t.id
           ORDER BY b.created_at DESC";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
}

// Fallback if no data
if (empty($bookings)) {
    $bookings = [
        ["id" => 0, "customer_name" => "—", "email" => "", "event_name" => "—", "package_name" => "—", "venue_name" => "—", "event_date" => "—", "total_cost" => "0", "status" => "", "created_at" => "", "payment_name" => "—", "time_slot_name" => "", "team_name" => ""]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Bookings</title>
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
                        <input type="text" id="bookingSearch" placeholder="Search bookings..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-400 bg-white">
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if ($message): ?>
                            <span
                                class="text-sm text-green-600 font-semibold bg-green-50 px-4 py-2 rounded-xl border border-green-200"><?= $message ?></span>
                        <?php endif; ?>
                        <form method="GET">
                            <select name="status" onchange="this.form.submit()"
                                class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-400 bg-white">
                                <option value="all">All Status</option>
                                <option value="Pending" <?= $statusFilter == 'Pending' ? 'selected' : '' ?>>Pending
                                </option>
                                <option value="Confirmed" <?= $statusFilter == 'Confirmed' ? 'selected' : '' ?>>Confirmed
                                </option>
                                <option value="Cancelled" <?= $statusFilter == 'Cancelled' ? 'selected' : '' ?>>Cancelled
                                </option>
                                <option value="Completed" <?= $statusFilter == 'Completed' ? 'selected' : '' ?>>
                                    Completed</option>
                            </select>
                        </form>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                    <div class="overflow-x-auto">

                        <table class="w-full text-sm rounded-2xl">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="p-3 text-left">Customer</th>
                                    <th class="p-3 text-left">Event</th>
                                    <th class="p-3 text-left">Package</th>
                                    <th class="p-3 text-left">Time</th>
                                    <th class="p-3 text-left">Date</th>
                                    <th class="p-3 text-left">Payment</th>
                                    <th class="p-3 text-left">Receipt</th>
                                    <th class="p-3 text-center">Status</th>
                                    <th class="p-3 text-center">Actions</th>
                                </tr>
                            </thead>

                            <tbody id="tableBody">

                                <?php foreach ($bookings as $b): ?>

                                    <?php if ($b['id'] === 0 || ($statusFilter != 'all' && $b['status'] != $statusFilter))
                                        continue; ?>

                                    <tr class="border-t hover:bg-gray-50">

                                        <td class="p-3">
                                            <div class="font-semibold"><?= htmlspecialchars($b['customer_name']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($b['email']) ?></div>
                                        </td>

                                        <td class="p-3"><?= htmlspecialchars($b['event_name']) ?></td>
                                        <td class="p-3">
                                            <span
                                                class="px-2 py-0.5 text-xs font-bold rounded-full 
                                    <?= $b['package_name'] === 'Silver' ? 'bg-gray-200 text-gray-700' : ($b['package_name'] === 'Gold' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700') ?>">
                                                <?= htmlspecialchars($b['package_name']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <?php if (!empty($b['time_slot_name'])): ?>
                                                <span
                                                    class="px-2 py-0.5 text-xs font-bold rounded-full bg-indigo-100 text-indigo-700">
                                                    <?= htmlspecialchars($b['time_slot_name']) ?>
                                                </span>
                                                <?php if (!empty($b['team_name'])): ?>
                                                    <div class="text-[10px] text-gray-400 mt-0.5">
                                                        <?= htmlspecialchars($b['team_name']) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3"><?= htmlspecialchars($b['event_date']) ?></td>
                                        <td class="p-3">
                                            <?php if (!empty($b['payment_name'])): ?>
                                                <span
                                                    class="px-2 py-0.5 text-xs font-bold rounded-full
                                        <?= $b['payment_name'] === 'KBZPay' ? 'bg-green-100 text-green-700' : ($b['payment_name'] === 'WavePay' ? 'bg-blue-100 text-blue-700' : ($b['payment_name'] === 'CBPay' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700')) ?>">
                                                    <?= htmlspecialchars($b['payment_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="p-3">
                                            <?php if (!empty($b['receipt_image'])): ?>
                                                <a href="javascript:void(0)"
                                                    onclick="openReceiptModal('../<?= htmlspecialchars($b['receipt_image']) ?>')"
                                                    class="text-purple-600 hover:text-purple-800 underline text-xs">View</a>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="p-3">
                                            <?php if ($b['status'] === 'Pending'): ?>
                                                <span
                                                    class="status-badge px-3 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700">Pending</span>
                                            <?php elseif ($b['status'] === 'Confirmed'): ?>
                                                <span
                                                    class="status-badge px-3 py-1 text-xs rounded-full bg-green-100 text-green-700">Confirmed</span>
                                            <?php elseif ($b['status'] === 'Completed'): ?>
                                                <span
                                                    class="status-badge px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-700">Completed</span>
                                            <?php elseif ($b['status'] === 'Cancelled'): ?>
                                                <span
                                                    class="status-badge px-3 py-1 text-xs rounded-full bg-red-100 text-red-700">Cancelled</span>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="p-3">
                                            <div class="flex justify-center items-center gap-2">
                                                <?php $isCompleted = $b['status'] === 'Completed'; ?>
                                                <button type="button"
                                                    onclick="confirmBooking(<?= $b['id'] ?>, this)"
                                                    class="confirm-btn inline-flex items-center gap-1 px-1.5 py-1 bg-green-100 text-green-600 rounded-lg text-xs transition <?= $isCompleted ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'hover:bg-green-300' ?>"
                                                    <?= $isCompleted ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-circle-check"></i>
                                                    Confirm
                                                </button>
                                                <button type="button"
                                                    onclick="openCancelModal(<?= $b['id'] ?>)"
                                                    data-id="<?= $b['id'] ?>"
                                                    class="cancel-btn inline-flex items-center gap-1 px-1.5 py-1 bg-red-100 text-red-600 rounded-lg text-xs transition <?= $isCompleted ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'hover:bg-red-300' ?>"
                                                    <?= $isCompleted ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-circle-xmark"></i>
                                                    Cancel
                                                </button>
                                            </div>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>
                                <tr class="no-results hidden">
                                    <td colspan="9" class="p-6 text-center text-gray-400 text-sm">No bookings found
                                        matching
                                        your search.</td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                </div>

            </main>

        </div>

    </div>

    <!-- Cancel Reason Modal -->
    <div id="cancelModal" class="fixed inset-0 z-50 hidden items-center justify-center"
        style="background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4 shadow-2xl">
            <h3 class="text-lg font-bold text-gray-800 mb-2">Cancel Booking</h3>
            <p class="text-sm text-gray-500 mb-4">Please provide a reason for cancelling this booking.</p>
            <form method="POST">
                <input type="hidden" name="booking_id" id="cancelBookingId" value="">
                <textarea name="cancel_reason" rows="3"
                    class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-red-400 resize-none"
                    placeholder="Enter cancellation reason..."></textarea>
                <div class="flex gap-2 mt-4">
                    <button type="button" onclick="closeCancelModal()"
                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 rounded-xl text-sm transition">Keep</button>
                    <button type="submit" name="cancel_booking"
                        class="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-2 rounded-xl text-sm transition">Cancel Booking</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Receipt Image Modal -->
    <div id="receiptModal" class="fixed inset-0 z-50 hidden items-center justify-center"
        style="background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);">
        <div class="relative">
            <button onclick="closeReceiptModal()"
                class="absolute -top-3 -right-3 bg-white text-gray-700 hover:text-red-500 hover:bg-red-50 rounded-full w-9 h-9 flex items-center justify-center shadow-lg transition-colors duration-200"
                title="Close">
                <i class="fas fa-times text-lg"></i>
            </button>
            <img id="receiptModalImg" src="" alt="Receipt"
                class="max-w-[90vw] max-h-[85vh] rounded-xl shadow-2xl border-4 border-white" />
        </div>
    </div>

    <script>
        document.getElementById('bookingSearch').addEventListener('input', function () {
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

        function openCancelModal(id) {
            document.getElementById('cancelBookingId').value = id;
            document.getElementById('cancelModal').classList.remove('hidden');
            document.getElementById('cancelModal').classList.add('flex');
        }
        function closeCancelModal() {
            document.getElementById('cancelModal').classList.add('hidden');
            document.getElementById('cancelModal').classList.remove('flex');
        }
        document.getElementById('cancelModal')?.addEventListener('click', function (e) {
            if (e.target === this) closeCancelModal();
        });

        function openReceiptModal(src) {
            const modal = document.getElementById('receiptModal');
            document.getElementById('receiptModalImg').src = src;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeReceiptModal() {
            const modal = document.getElementById('receiptModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.getElementById('receiptModalImg').src = '';
        }
        document.getElementById('receiptModal').addEventListener('click', function (e) {
            if (e.target === this) closeReceiptModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeReceiptModal(); closeCancelModal(); }
        });

        function disableActions(row) {
            row.querySelectorAll('.confirm-btn, .cancel-btn').forEach(btn => {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                btn.classList.remove('hover:bg-green-300', 'hover:bg-red-300');
            });
        }

        function updateStatusBadge(row, status) {
            let badge = row.querySelector('.status-badge');
            if (!badge) {
                const td = row.cells[7];
                badge = td.querySelector('span');
            }
            if (status === 'Confirmed') {
                badge.className = 'px-3 py-1 text-xs rounded-full bg-green-100 text-green-700';
                badge.textContent = 'Confirmed';
            } else if (status === 'Cancelled') {
                badge.className = 'px-3 py-1 text-xs rounded-full bg-red-100 text-red-700';
                badge.textContent = 'Cancelled';
            } else if (status === 'Completed') {
                badge.className = 'px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-700';
                badge.textContent = 'Completed';
            }
        }

        function confirmBooking(id, btn) {
            const row = btn.closest('tr');
            disableActions(row);
            fetch('bookings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_action=approve&id=' + id
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    updateStatusBadge(row, 'Confirmed');
                } else {
                    location.reload();
                }
            })
            .catch(() => location.reload());
        }

        function cancelBookingAJAX(id, reason) {
            const row = document.querySelector('.cancel-btn[data-id="' + id + '"]')?.closest('tr');
            if (row) disableActions(row);
            fetch('bookings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_action=cancel&id=' + id + '&reason=' + encodeURIComponent(reason)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (row) updateStatusBadge(row, 'Cancelled');
                    closeCancelModal();
                } else {
                    location.reload();
                }
            })
            .catch(() => location.reload());
        }

        document.querySelector('#cancelModal form').addEventListener('submit', function (e) {
            e.preventDefault();
            const id = document.getElementById('cancelBookingId').value;
            const reason = this.querySelector('textarea').value;
            cancelBookingAJAX(id, reason);
        });
    </script>

</body>

</html>