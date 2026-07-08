<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/notification_helper.php';
$statusFilter = $_GET['status'] ?? 'all';
$message = '';

// Insert sample data

// Approve / Cancel / Delete actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    if ($_GET['action'] === 'approve') {
        $conn->query("UPDATE bookings SET status='Confirmed' WHERE id=$id");
        $bk = $conn->query("SELECT b.user_id, e.event_name, b.event_date FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.id = $id")->fetch_assoc();
        if ($bk) {
            $dateStr = date('M j, Y', strtotime($bk['event_date']));
            createNotification($conn, $bk['user_id'], 'Booking Confirmed', "Your booking for {$bk['event_name']} on {$dateStr} has been confirmed.", '../users/my_bookings.php');
        }
        $message = "Booking confirmed!";
    } elseif ($_GET['action'] === 'cancel') {
        $conn->query("UPDATE bookings SET status='Cancelled' WHERE id=$id");
        $bk = $conn->query("SELECT b.user_id, e.event_name, b.event_date FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.id = $id")->fetch_assoc();
        if ($bk) {
            $dateStr = date('M j, Y', strtotime($bk['event_date']));
            createNotification($conn, $bk['user_id'], 'Booking Cancelled', "Your booking for {$bk['event_name']} on {$dateStr} has been cancelled.", '../users/my_bookings.php');
        }
        $message = "Booking  cancelled.";
    } elseif ($_GET['action'] === 'delete') {
        $conn->query("DELETE FROM bookings WHERE id=$id");
        $message = "Booking  deleted.";
    }
}

// Fetch from DB
$bookings = [];
    $query = "SELECT b.id, b.event_date, b.total_cost, b.status, b.created_at, b.paymentmethods_id, b.receipt_image,
                  u.name AS customer_name, u.email,
                  e.event_name,
                  p.name AS package_name,
                  v.name AS venue_name,
                  pm.payment_name
           FROM bookings b
           JOIN users u ON b.user_id = u.id
           JOIN events e ON b.event_id = e.id
           JOIN packages p ON b.package_id = p.id
           JOIN venues v ON b.venue_id = v.id
           LEFT JOIN payment_methods pm ON b.paymentmethods_id = pm.id
           ORDER BY b.created_at DESC";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
}

// Fallback if no data
if (empty($bookings)) {
    $bookings = [
        ["id" => 0, "customer_name" => "—", "email" => "", "event_name" => "—", "package_name" => "—", "venue_name" => "—", "event_date" => "—", "total_cost" => "0", "status" => "—", "created_at" => "", "payment_name" => "—"]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Bookings</title>
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
     </style>

</head>

<body class="bg-gray-100 min-h-screen overflow-hidden">

<div class="flex h-screen">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col ml-64">

        <?php include 'admin_header.php'; ?>

        <main class="flex-1 p-8 overflow-y-auto">

            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Bookings</h2>

                <div class="flex items-center gap-3">
                    <?php if ($message): ?>
                        <span class="text-sm text-green-600 font-semibold bg-green-50 px-4 py-2 rounded-xl border border-green-200"><?= $message ?></span>
                    <?php endif; ?>
                    <form method="GET">
                        <select name="status" onchange="this.form.submit()"
                                class="px-4 py-2 rounded-xl border">
                            <option value="all">All</option>
                            <option value="Pending" <?= $statusFilter=='Pending'?'selected':'' ?>>Pending</option>
                            <option value="Confirmed" <?= $statusFilter=='Confirmed'?'selected':'' ?>>Confirmed</option>
                            <option value="Cancelled" <?= $statusFilter=='Cancelled'?'selected':'' ?>>Cancelled</option>
                        </select>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-200">

                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="p-3 text-left">Customer</th>
                            <th class="p-3 text-left">Event</th>
                            <th class="p-3 text-left">Package</th>
                            <th class="p-3 text-left">Date</th>
                            <th class="p-3 text-left">Payment</th>
                            <th class="p-3 text-left">Receipt</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($bookings as $b): ?>

                        <?php if ($b['id'] === 0 || ($statusFilter != 'all' && $b['status'] != $statusFilter)) continue; ?>

                        <tr class="border-t hover:bg-gray-50">

                            <td class="p-3">
                                <div class="font-semibold"><?= htmlspecialchars($b['customer_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($b['email']) ?></div>
                            </td>

                            <td class="p-3"><?= htmlspecialchars($b['event_name']) ?></td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-xs font-bold rounded-full 
                                    <?= $b['package_name'] === 'Silver' ? 'bg-gray-200 text-gray-700' : ($b['package_name'] === 'Gold' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700') ?>">
                                    <?= htmlspecialchars($b['package_name']) ?>
                                </span>
                            </td>
                            <td class="p-3"><?= htmlspecialchars($b['event_date']) ?></td>
                            <td class="p-3">
                                <?php if (!empty($b['payment_name'])): ?>
                                    <span class="px-2 py-0.5 text-xs font-bold rounded-full
                                        <?= $b['payment_name'] === 'KBZPay' ? 'bg-green-100 text-green-700' : ($b['payment_name'] === 'WavePay' ? 'bg-blue-100 text-blue-700' : ($b['payment_name'] === 'CBPay' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700')) ?>">
                                        <?= htmlspecialchars($b['payment_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="p-3">
                                <?php if (!empty($b['receipt_image'])): ?>
                                    <a href="../<?= htmlspecialchars($b['receipt_image']) ?>" target="_blank" class="text-purple-600 hover:text-purple-800 underline text-xs">View</a>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="p-3">
                                <?php if ($b['status'] === 'Pending'): ?>
                                    <span class="px-3 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700">Pending</span>
                                <?php elseif ($b['status'] === 'Confirmed'): ?>
                                    <span class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-700">Confirmed</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 text-xs rounded-full bg-red-100 text-red-700">Cancelled</span>
                                <?php endif; ?>
                            </td>

                            <td class="p-3 flex gap-2">
                                <a href="?action=approve&id=<?= $b['id'] ?>"
                                   class="px-3 py-1 bg-green-400 text-white rounded-lg text-xs hover:bg-green-600">Confirm</a>
                                <a href="?action=cancel&id=<?= $b['id'] ?>"
                                   onclick="return confirm('Cancel this booking?')"
                                   class="px-3 py-1 bg-yellow-400 text-white rounded-lg text-xs hover:bg-yellow-600">Cancel</a>
                                <a href="?action=delete&id=<?= $b['id'] ?>"
                                   onclick="return confirm('Delete this booking?')"
                                   class="px-3 py-1 bg-red-400 text-white rounded-lg text-xs hover:bg-red-600">Delete</a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        </main>

    </div>

</div>

</body>
</html>