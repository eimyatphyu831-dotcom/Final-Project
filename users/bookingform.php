<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/notification_helper.php';

// --- Setup time_slots, teams, and migrate columns ---
$conn->query("CREATE TABLE IF NOT EXISTS time_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_name VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL
)");

$r = $conn->query("SELECT COUNT(*) as cnt FROM time_slots");
if ($r && $r->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO time_slots (slot_name, start_time, end_time) VALUES
        ('Slot 1', '09:00:00', '12:00:00'),
        ('Slot 2', '12:00:00', '15:00:00'),
        ('Slot 3', '15:00:00', '18:00:00'),
        ('Slot 4', '18:00:00', '21:00:00')");
}

// Seed teams if empty
$r = $conn->query("SELECT COUNT(*) as cnt FROM teams");
if ($r && $r->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO teams (name) VALUES
        ('Team A'),
        ('Team B'),
        ('Team C'),
        ('Team D')");
}

// Migrate bookings.time_slot (ENUM) -> bookings.time_slot_id (INT FK)
$slotEnumCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'time_slot'");
$slotIdCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'time_slot_id'");

if ($slotEnumCol && $slotEnumCol->num_rows > 0 && !($slotIdCol && $slotIdCol->num_rows > 0)) {
    // Old ENUM exists, new column doesn't: migrate
    $conn->query("ALTER TABLE bookings ADD COLUMN time_slot_id INT AFTER venue_id");
    $conn->query("UPDATE bookings b JOIN time_slots ts ON b.time_slot = ts.slot_name SET b.time_slot_id = ts.id");
    $conn->query("ALTER TABLE bookings DROP COLUMN time_slot");
} elseif (!($slotEnumCol && $slotEnumCol->num_rows > 0) && !($slotIdCol && $slotIdCol->num_rows > 0)) {
    // Neither exists: fresh add
    $conn->query("ALTER TABLE bookings ADD COLUMN time_slot_id INT NOT NULL DEFAULT 1 AFTER venue_id");
}

// team_id column
$teamIdCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'team_id'");
if ($teamIdCol && $teamIdCol->num_rows === 0) {
    $conn->query("ALTER TABLE bookings ADD COLUMN team_id INT AFTER time_slot_id");
    $conn->query("ALTER TABLE bookings ADD FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE");
}


// Create reviews table
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    rating TINYINT NOT NULL,
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE ON UPDATE CASCADE
)");

// FK for time_slot_id
$fkSlot = $conn->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'time_slot_id' AND REFERENCED_TABLE_NAME = 'time_slots'");
if ($fkSlot && $fkSlot->num_rows === 0) {
    $conn->query("ALTER TABLE bookings ADD FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE RESTRICT ON UPDATE CASCADE");
}

// Add Completed status to ENUM if missing
$statusCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'status'");
if ($statusCol && $row = $statusCol->fetch_assoc()) {
    if (strpos($row['Type'], 'Completed') === false) {
        $conn->query("ALTER TABLE bookings MODIFY COLUMN status ENUM('Pending','Confirmed','Cancelled','Completed') DEFAULT 'Pending'");
    }
}

$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$userId = $_SESSION['user_id'];
$message = '';

// Fetch user phone from DB
$userPhone = '';
$stmt = $conn->prepare("SELECT phone FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$r = $stmt->get_result();
if ($r && $row = $r->fetch_assoc())
    $userPhone = $row['phone'];
$stmt->close();

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$venueId = isset($_GET['venue_id']) ? (int) $_GET['venue_id'] : 0;
$packageId = isset($_GET['package_id']) ? (int) $_GET['package_id'] : 0;
$totalCost = isset($_GET['total']) ? (float) $_GET['total'] : 0;

$eventImage = '../assets/images/slide1.png';
$eventName = '—';
$venueName = '—';
$packageName = '—';

if ($eventId > 0) {
    $r = $conn->query("SELECT event_name, image FROM events WHERE id=$eventId");
    if ($r && $row = $r->fetch_assoc()) {
        $eventName = $row['event_name'];
        $eventImage = $row['image'] ?: '../assets/images/slide1.png';
    }
}
if ($venueId > 0) {
    $r = $conn->query("SELECT name FROM venues WHERE id=$venueId");
    if ($r && $row = $r->fetch_assoc())
        $venueName = $row['name'];
}
if ($packageId > 0) {
    $r = $conn->query("SELECT name FROM packages WHERE id=$packageId");
    if ($r && $row = $r->fetch_assoc())
        $packageName = $row['name'];
}

// Fetch time slots from DB
$timeSlots = [];
$r = $conn->query("SELECT * FROM time_slots ORDER BY id");
if ($r) {
    $timeSlots = $r->fetch_all(MYSQLI_ASSOC);
}

$timeSlotMap = [];
$slotNames = [];
foreach ($timeSlots as $ts) {
    $timeSlotMap[$ts['id']] = [$ts['start_time'], $ts['end_time']];
    $slotNames[$ts['id']] = $ts['slot_name'];
}

// Track which teams are already assigned per date (across ALL venues and slots)
$assignedTeamsByDate = [];
$r = $conn->query("SELECT event_date, team_id FROM bookings WHERE status != 'Cancelled' AND team_id IS NOT NULL");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $assignedTeamsByDate[$row['event_date']][] = (int) $row['team_id'];
    }
}

// Fetch dates where this user already booked this event
$userBookedDates = [];
if ($userId > 0 && $eventId > 0) {
    $stmt = $conn->prepare("SELECT event_date FROM bookings WHERE user_id = ? AND event_id = ? AND status != 'Cancelled'");
    $stmt->bind_param("ii", $userId, $eventId);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $userBookedDates[] = $row['event_date'];
        }
    }
    $stmt->close();
}

// Fetch booked slots for this specific venue
$bookedSlots = [];
if ($venueId > 0) {
    $stmt = $conn->prepare("SELECT event_date, time_slot_id FROM bookings WHERE venue_id = ? AND status != 'Cancelled'");
    $stmt->bind_param("i", $venueId);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $bookedSlots[$row['event_date']][] = (int) $row['time_slot_id'];
        }
    }
    $stmt->close();
}

// Total number of teams
$totalTeams = 0;
$r = $conn->query("SELECT COUNT(*) as cnt FROM teams");
if ($r && $row = $r->fetch_assoc())
    $totalTeams = (int) $row['cnt'];

// Fetch all teams for assignment (ordered A→B→C→D)
$teams = [];
$r = $conn->query("SELECT id, name FROM teams ORDER BY name");
if ($r)
    $teams = $r->fetch_all(MYSQLI_ASSOC);

$paymentMethods = [];
$r = $conn->query("SELECT id, payment_name FROM payment_methods ORDER BY id");
if ($r)
    $paymentMethods = $r->fetch_all(MYSQLI_ASSOC);

$base = '../assets/images/';

// Find KBZPay ID for auto-selection
$kpayId = 0;
$kpayQr = '';
foreach ($paymentMethods as $pm) {
    if (strtolower($pm['payment_name']) === 'kbzpay') {
        $kpayId = $pm['id'];
        $kpayQr = $base . 'kpayqr.jpg';
        break;
    }
}

$logoMap = [
    'KBZPay' => 'kpay.png',
    'WavePay' => 'wavepay.png',
    'CBPay' => 'cbpay.png',
    'AYAPay' => 'ayapay.png',
];

$qrMap = [
    'KBZPay' => 'kpayqr.jpg',
    'WavePay' => 'wavepay_qr.png',
    'CBPay' => 'cbpay_qr.png',
    'AYAPay' => 'ayapay_qr.png',
];

$accountMap = [
    'KBZPay' => 'Daw Ei Myat Phyu',
    'WavePay' => 'U Myat Maung',
    'CBPay' => 'Ko Kyaw Kyaw',
    'AYAPay' => 'Ma Hla Hla',
];

$phoneMap = [
    'KBZPay' => '09-950305004',
    'WavePay' => '09-662602024',
    'CBPay' => '09-694407879',
    'AYAPay' => '09-965707826',
];

$colorMap = [
    'KBZPay' => '#205fb0ff',
    'WavePay' => '#d3bf25ff',
    'CBPay' => '#0a1472ff',
    'AYAPay' => '#540b0bff',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_date = $_POST['event_date'] ?? '';
    $time_slot_id = (int) ($_POST['time_slot'] ?? 0);
    $eid = (int) ($_POST['event_id'] ?? 0);
    $vid = (int) ($_POST['venue_id'] ?? 0);
    $pid = (int) ($_POST['package_id'] ?? 0);
    $total = (float) ($_POST['total_cost'] ?? 0);
    $paymentMethodId = !empty($_POST['paymentmethods_id']) ? (int) $_POST['paymentmethods_id'] : 0;

    if ($eid > 0 && $vid > 0 && $pid > 0 && $event_date && $time_slot_id > 0 && isset($timeSlotMap[$time_slot_id])) {
        $slotName = $slotNames[$time_slot_id];

        // Handle receipt upload
        $receiptPath = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/receipts/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $filename = 'receipt_' . time() . '_' . $userId . '.' . $ext;
            $dest = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) {
                $receiptPath = 'uploads/receipts/' . $filename;
            }
        }

        // Step 1: Check if this user already has a booking for this event on this date
        $stmt = $conn->prepare("SELECT id FROM bookings WHERE user_id = ? AND event_id = ? AND event_date = ? AND status != 'Cancelled'");
        $stmt->bind_param("iis", $userId, $eid, $event_date);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $message = "You have already booked this event on this date.";
        } else {
            $stmt->close();

            // Step 2: Check if this venue is already booked for this date and time slot
            $stmt = $conn->prepare("SELECT id FROM bookings WHERE venue_id = ? AND event_date = ? AND time_slot_id = ? AND status != 'Cancelled'");
        $stmt->bind_param("isi", $vid, $event_date, $time_slot_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $message = "This time slot is already booked at this venue.";
        } else {
            $stmt->close();

            // Step 2: Check if all teams are already assigned on this date
            $assignedTeamIds = $assignedTeamsByDate[$event_date] ?? [];
            if (count($assignedTeamIds) >= $totalTeams) {
                $message = "All teams are fully booked on this date. Please choose another date.";
            } else {
                // Step 3: Find the first available team (FIFO order: A → B → C → D)
                $teamId = null;
                foreach ($teams as $t) {
                    if (!in_array((int) $t['id'], $assignedTeamIds)) {
                        $teamId = (int) $t['id'];
                        break;
                    }
                }

                // Step 4: If no team is available, reject
                if ($teamId === null) {
                    $message = "No service team is available on this date. Please choose another date.";
                } else {
                    // Step 5: Venue slot and team are available — assign team, save
                    $stmt = $conn->prepare("INSERT INTO bookings (user_id, event_id, venue_id, package_id, time_slot_id, team_id, event_date, total_cost, status, paymentmethods_id, receipt_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)");
                    $stmt->bind_param("iiiiiisdis", $userId, $eid, $vid, $pid, $time_slot_id, $teamId, $event_date, $total, $paymentMethodId, $receiptPath);
                    if ($stmt->execute()) {
                        $bookingId = $stmt->insert_id;
                        $stmt->close();
                        $dateStr = date('M j, Y', strtotime($event_date));
                        // Notify all admins
                        $adminResult = $conn->query("SELECT id FROM admins");
                        if ($adminResult) {
                            while ($admin = $adminResult->fetch_assoc()) {
                                createNotification($conn, $admin['id'], 'New Booking', "{$userName} booked {$eventName} on {$dateStr} ({$slotName}) for " . number_format($total) . " MMK.", '../admin/bookings.php', 'admin');
                            }
                        }
                        header("Location: booking_success.php?booking_id=$bookingId");
                        exit();
                    }
                    $stmt->close();
                    $message = 'Failed to create booking. Please try again.';
                }
            }
        }
        }
    } else {
        $message = 'Missing required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Form</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        .pm-card {
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }

        .pm-card:hover {
            border-color: #c4b5fd;
            transform: translateY(-2px);
        }

        .pm-card.selected {
            border-color: var(--pm-color, #205fb0ff);
            border-width: 3px;
            background: var(--pm-bg, #ede9fe);
            box-shadow: 0 4px 16px var(--pm-shadow, rgba(124, 58, 237, 0.3));
            transform: translateY(-2px) scale(1.03);
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 50;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            background: white;
            border-radius: 24px;
            padding: 32px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.3);
            animation: modalIn 0.25s ease;
        }

        @keyframes modalIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .slot-option.selected {
            border-color: #7c3aed !important;
            background: #9274c6ff;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
        }
    </style>
</head>

<body class="bg-purple-50">

    <?php include '../includes/header.php'; ?>

    <section class="max-w-5xl mx-auto px-2 sm:px-3 py-5">
        <div class="bg-white rounded-2xl shadow-[0_12px_40px_rgba(0,0,0,0.12)] border border-gray-100 p-2 md:p-3">

            <div class="flex items-start justify-between mb-2 gap-2">
                <div class="flex-1 flex flex-col items-center justify-center text-center">
                    <h2 class="text-2xl font-extrabold text-brand-600/60 text-center">Complete Your Booking</h2>
                    <p class="text-gray-500 mt-1 text-center text-sm">Finalize your event request details.</p>
                </div>
                <a href="javascript:history.back()"
                    class="text-gray-400 hover:text-purple-600 transition flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </a>
            </div>

            <?php if ($message): ?>
                <div class="mb-3 p-2 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Booking Form -->
                <div class="lg:col-span-2">
                    <div class="rounded-2xl border border-gray-200 p-2 bg-white shadow-sm">
                        <form id="bookingForm" method="POST" enctype="multipart/form-data" class="space-y-3">

                            <input type="hidden" name="event_id" value="<?= $eventId ?>">
                            <input type="hidden" name="venue_id" value="<?= $venueId ?>">
                            <input type="hidden" name="package_id" value="<?= $packageId ?>">
                            <input type="hidden" name="total_cost" value="<?= $totalCost ?>">
                            <input type="hidden" name="paymentmethods_id" id="payment_method_id" value="">


                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">
                                        Event Date
                                    </label>

                                    <div class="relative">
                                        <input type="text" name="event_date" id="eventDatePicker"
                                            class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 pr-10 focus:ring-2 focus:ring-purple-500 outline-none transition"
                                            placeholder="Select Date" required>

                                        <button type="button" id="calendarBtn"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-purple-600">
                                            <i class="fa-solid fa-calendar-days"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Time schedule -->
                            <div id="timeScheduleSection" class="hidden">
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-2">Time
                                    Schedule</label>
                                <div id="timeSlotGroup" class="grid grid-cols-<?= count($timeSlots) ?> gap-3">
                                    <?php foreach ($timeSlots as $ts): ?>
                                        <label
                                            class="slot-option border  border-gray-300 rounded-xl p-2 text-center cursor-pointer transition hover:border-purple-400"
                                            onclick="selectSlot(this)">
                                            <input type="radio" name="time_slot" value="<?= $ts['id'] ?>" class="sr-only" required>

                                            <div class="text-xs font-bold text-gray-700">
                                                <span class="slot-time"><?= date('g:i A', strtotime($ts['start_time'])) ?> –
                                                    <?= date('g:i A', strtotime($ts['end_time'])) ?></span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p id="slotStatus" class="hidden"></p>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Upload
                                    Receipt</label>
                                <label for="receiptInput" id="receiptUploadBox"
                                    class="relative flex flex-col items-center justify-center w-full border-2 border-dashed border-purple-200 rounded-xl p-6 cursor-pointer transition-all duration-300 overflow-hidden group"
                                    style="background: linear-gradient(135deg, #faf5ff 0%, #ede9fe 50%, #fdf2f8 100%);">
                                    <div id="receiptPlaceholder" class="flex flex-col items-center gap-2">
                                        <div
                                            class="w-14 h-14 rounded-full bg-purple-100 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-purple-500"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div class="text-center">
                                            <span id="receiptLabel" class="text-sm font-semibold text-purple-700">Upload
                                                Receipt Image</span>
                                            <p class="text-[10px] text-gray-400 mt-0.5">PNG, JPG or WEBP</p>
                                        </div>
                                        <!-- <span class="mt-1 px-4 py-1.5 text-xs font-bold text-white bg-purple-500 rounded-full group-hover:bg-purple-600 transition-colors shadow-sm shadow-purple-200">Choose Image</span> -->
                                    </div>
                                    <img id="receiptPreviewImg" src=""
                                        class="hidden w-full h-48 object-contain rounded-lg">
                                </label>
                                <input type="file" name="receipt" id="receiptInput" accept="image/*"
                                    capture="environment" required onchange="previewReceipt(this)" class="absolute w-px h-px opacity-0 overflow-hidden">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Payment
                                    Method</label>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <?php foreach ($paymentMethods as $pm):
                                        $logo = $logoMap[$pm['payment_name']] ?? strtolower($pm['payment_name']) . '.png';
                                        $qr = $qrMap[$pm['payment_name']] ?? strtolower($pm['payment_name']) . '_qr.png';
                                        $account = $accountMap[$pm['payment_name']] ?? '';
                                        $phone = $phoneMap[$pm['payment_name']] ?? '';
                                        ?>
                                        <div class="pm-card rounded-lg border border-gray-300 p-1 text-center w-15 h-13 flex flex-col items-center justify-center cursor-pointer"
                                            data-id="<?= $pm['id'] ?>" data-qr="<?= $base . $qr ?>"
                                            data-account="<?= htmlspecialchars($account) ?>"
                                            data-phone="<?= htmlspecialchars($phone) ?>"
                                            data-color="<?= $colorMap[$pm['payment_name']] ?? '#7c3aed' ?>"
                                            onclick="selectPayment(this)">

                                            <img src="<?= $base . $logo ?>"
                                                alt="<?= htmlspecialchars($pm['payment_name']) ?>"
                                                class="w-6 h-6 object-contain mb-0.5">

                                            <span class="text-[10px] font-semibold text-gray-700">
                                                <?= htmlspecialchars($pm['payment_name']) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Payment QR Scan -->
                            <div class="bg-purple-50 border border-gray-200 rounded-xl p-4 shadow-sm text-center"
                                id="summaryQrSection">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Scan to Pay with <span
                                        id="summaryPmName">KBZPay</span></p>
                                <div id="summaryAccountInfo" class="text-sm text-gray-700 mb-3 space-y-1">
                                    <div class="font-semibold text-gray-800" id="summaryAccountName">U Mya Maung</div>
                                    <div class="flex items-center justify-center gap-1 text-xs text-gray-500">
                                        <span id="summaryPhone">09-123456789</span>
                                        <button onclick="copyPhone(this)" title="Copy"
                                            class="text-purple-500 hover:text-purple-700 transition">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <img id="summaryQr" src="<?= $base ?>kpayqr.jpg" onclick="openQr()"
                                    class="w-28 h-28 mx-auto rounded-xl border border-gray-200 shadow-sm cursor-pointer">
                                <p class="text-[10px] text-gray-400 mt-2">Open your mobile banking app and scan to pay
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-1">

                                <button type="button" onclick="window.history.back()"
                                    class="bg-gray-200 text-gray-700 py-2 rounded-lg font-bold border border-gray-200 hover:bg-gray-400 transition hover:text-white">Cancel</button>
                                <button type="button" onclick="openConfirmModal()"
                                    class="bg-purple-600/60 text-white py-2 rounded-lg font-bold hover:bg-purple-800 transition shadow-md shadow-purple-200">Confirm
                                    Booking</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Booking Summary -->
                <div class="lg:col-span-1">
                    <div class="rounded-2xl border border-gray-200 p-4 bg-white shadow-sm top-4 space-y-3">

                        <!-- Event Photo -->
                        <div class="w-full h-36 rounded-xl overflow-hidden border border-gray-200">
                            <img src="<?= htmlspecialchars($eventImage) ?>" alt="<?= htmlspecialchars($eventName) ?>"
                                class="w-full h-full object-cover">
                        </div>

                        <!-- User Details -->
                        <div>
                            <h4 class="text-xs font-bold text-gray-500 uppercase mb-1.5 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Your Details
                            </h4>
                            <div class="bg-purple-50 rounded-xl p-2.5 space-y-1 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 w-16 text-xs">Name</span>
                                    <span
                                        class="font-medium text-gray-800 truncate"><?= htmlspecialchars($userName) ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 w-16 text-xs">Email</span>
                                    <span
                                        class="font-medium text-gray-800 truncate"><?= htmlspecialchars($userEmail) ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 w-16 text-xs">Phone</span>
                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($userPhone) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Booking Schedule -->
                        <div>
                            <h4 class="text-xs font-bold text-gray-500 uppercase mb-1.5 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Schedule
                            </h4>
                            <div class="bg-gray-50 rounded-xl p-2.5 space-y-1 text-sm" id="summarySchedule">
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 w-16 text-xs">Event</span>
                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($eventName) ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 w-16 text-xs">Date</span>
                                    <span class="font-medium text-gray-800" id="summaryDate">Not selected</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 w-16 text-xs">Time</span>
                                    <span class="font-medium text-gray-800" id="summaryTimeSlot">—</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 w-16 text-xs">Venue</span>
                                    <span
                                        class="font-medium text-gray-800"><?= htmlspecialchars($venueName ?? '') ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 w-16 text-xs">Package</span>
                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($packageName) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Total -->
                        <hr class="border-gray-200">
                        <div class="flex justify-between text-lg font-bold">
                            <span>Total</span>
                            <span class="text-purple-600/60"><?= number_format($totalCost) ?> MMK</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- QR Modal -->
    <div class="modal-overlay" id="qrModal" onclick="if(event.target===this)closeQr()">
        <div class="modal-box">
            <h3 class="text-lg font-extrabold text-gray-800 mb-1">Scan to Pay</h3>
            <p class="text-sm text-gray-500 mb-4">Use your <span id="pmName" class="font-bold text-purple-600"></span>
                app to scan</p>
            <div id="modalAccountInfo" class="text-sm text-gray-700 mb-3">
                <div class="font-medium" id="modalAccountName">U Mya Maung</div>
                <div class="flex items-center justify-center gap-1 text-xs text-gray-500">
                    <span id="modalPhone">09-123456789</span>
                    <button onclick="copyPhone(this)" title="Copy phone number"
                        class="text-purple-500 hover:text-purple-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                </div>
            </div>
            <img id="qrImage" src="" alt="QR Code"
                class="w-56 h-56 mx-auto rounded-xl border border-gray-200 shadow-sm">
            <p class="text-xs text-gray-400 mt-4">Open your mobile banking app and scan this QR code to complete
                payment.</p>
            <button onclick="closeQr()"
                class="mt-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-6 rounded-xl text-sm transition">Close</button>
        </div>
    </div>

    <!-- Confirm Booking Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">

        <div class="bg-white w-full max-w-md rounded-3xl shadow-xl p-8 mx-4 text-center">

            <div class="mx-auto w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center">
                <i data-lucide="calendar-check" class="w-8 h-8 text-purple-600"></i>
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mt-5">
                Confirm Booking?
            </h2>

            <p class="text-gray-500 mt-3">
                You are about to finalize your event request.
            </p>

            <div class="flex justify-center gap-4 mt-8">

                <button type="button" onclick="closeConfirmModal()"
                    class="px-6 py-2 rounded-xl border border-gray-300 text-gray-600 hover:bg-gray-100 transition">
                    Cancel
                </button>


                <button type="button" onclick="submitBooking()"
                    class="px-6 py-2 rounded-xl bg-purple-600 text-white hover:bg-purple-700 transition">
                    Yes, Confirm
                </button>

            </div>

        </div>

    </div>
    <?php include '../includes/footer.php'; ?>

    <script>
        let selectedPm = null;

        function selectPayment(el) {
            document.querySelectorAll('.pm-card').forEach(c => {
                c.classList.remove('selected');
                c.style.removeProperty('--pm-color');
                c.style.removeProperty('--pm-bg');
                c.style.removeProperty('--pm-shadow');
            });
            el.classList.add('selected');
            const color = el.dataset.color || '#7c3aed';
            const r = parseInt(color.slice(1, 3), 16), g = parseInt(color.slice(3, 5), 16), b = parseInt(color.slice(5, 7), 16);
            el.style.setProperty('--pm-color', color);
            el.style.setProperty('--pm-bg', `rgba(${r},${g},${b},0.2)`);
            el.style.setProperty('--pm-shadow', `rgba(${r},${g},${b},0.35)`);
            document.getElementById('payment_method_id').value = el.dataset.id;
            selectedPm = el;

            const pmName = el.querySelector('span') ? el.querySelector('span').textContent.trim() : 'KBZPay';
            const qrSrc = el.dataset.qr || '<?= $base ?>kpayqr.jpg';
            const account = el.dataset.account || '';
            const phone = el.dataset.phone || '';

            document.getElementById('summaryPmName').textContent = pmName;
            document.getElementById('summaryAccountName').textContent = account;
            document.getElementById('summaryPhone').textContent = phone;
            document.getElementById('summaryQr').src = qrSrc;

            document.getElementById('pmName').textContent = pmName;
            document.getElementById('modalAccountName').textContent = account;
            document.getElementById('modalPhone').textContent = phone;
            document.getElementById('qrImage').src = qrSrc;
        }

        function copyPhone(btn) {
            const phone = btn.closest('.flex').querySelector('span').textContent;
            navigator.clipboard.writeText(phone).then(() => {
                const orig = btn.innerHTML;
                btn.innerHTML = '<svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
                setTimeout(() => btn.innerHTML = orig, 1500);
            });
        }

        function openQr() {
            document.getElementById('qrModal').classList.add('open');
        }

        function closeQr() {
            document.getElementById('qrModal').classList.remove('open');
        }

        function previewReceipt(input) {
            const placeholder = document.getElementById('receiptPlaceholder');
            const img = document.getElementById('receiptPreviewImg');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                img.classList.add('hidden');
                img.src = '';
                placeholder.classList.remove('hidden');
            }
        }

        function selectSlot(el) {
            document.querySelectorAll('.slot-option').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            el.querySelector('input[type="radio"]').checked = true;
            const timeText = el.querySelector('.slot-time') ? el.querySelector('.slot-time').textContent.trim() : '—';
            document.getElementById('summaryTimeSlot').textContent = timeText;
        }

        function openConfirmModal() {

            const form = document.getElementById('bookingForm');

            if (!form.reportValidity()) return;

            if (!document.getElementById('payment_method_id').value) {
                document.getElementById('payment_method_id').setCustomValidity('Please select a payment method.');
                form.reportValidity();
                document.getElementById('payment_method_id').setCustomValidity('');
                return;
            }

            document.getElementById('confirmModal').classList.remove('hidden');
            document.getElementById('confirmModal').classList.add('flex');
        }



        function submitBooking() {

            document.getElementById('bookingForm').submit();

        }



        function closeConfirmModal() {

            document.getElementById('confirmModal')
                .classList.remove('flex');

            document.getElementById('confirmModal')
                .classList.add('hidden');

        }





        document.addEventListener('DOMContentLoaded', function () {
            const kpayId = <?= $kpayId ?>;
            if (kpayId) {
                const kpayCard = document.querySelector(`.pm-card[data-id="${kpayId}"]`);
                if (kpayCard) {
                    document.querySelectorAll('.pm-card').forEach(c => c.classList.remove('selected'));
                    kpayCard.classList.add('selected');
                    document.getElementById('payment_method_id').value = kpayCard.dataset.id;
                    selectedPm = kpayCard;

                    const pmName = kpayCard.querySelector('span') ? kpayCard.querySelector('span').textContent.trim() : 'KBZPay';
                    const qrSrc = kpayCard.dataset.qr || '<?= $base ?>kpayqr.jpg';
                    const account = kpayCard.dataset.account || '';
                    const phone = kpayCard.dataset.phone || '';

                    document.getElementById('summaryPmName').textContent = pmName;
                    document.getElementById('summaryAccountName').textContent = account;
                    document.getElementById('summaryPhone').textContent = phone;
                    document.getElementById('summaryQr').src = qrSrc;

                    document.getElementById('pmName').textContent = pmName;
                    document.getElementById('modalAccountName').textContent = account;
                    document.getElementById('modalPhone').textContent = phone;
                    document.getElementById('qrImage').src = qrSrc;
                }
            }

            const bookedSlots = <?= json_encode($bookedSlots) ?>;
            const assignedTeamsByDate = <?= json_encode($assignedTeamsByDate) ?>;
            const userBookedDates = <?= json_encode($userBookedDates) ?>;
            const totalTeams = <?= $totalTeams ?>;
            const slotNames = <?= json_encode($slotNames) ?>;
            const input = document.getElementById("eventDatePicker");
            const slotRadios = document.querySelectorAll('input[name="time_slot"]');
            const slotStatus = document.getElementById('slotStatus');

            // Disable dates where all teams are fully booked
            const fullyBookedDates = Object.keys(assignedTeamsByDate).filter(d => {
                return assignedTeamsByDate[d].length >= totalTeams;
            });

            function updateSlotAvailability(selectedDate) {
                const venueTaken = bookedSlots[selectedDate] || [];
                const teamsAssigned = assignedTeamsByDate[selectedDate] || [];
                const isDateFull = teamsAssigned.length >= totalTeams;
                const alreadyBooked = userBookedDates.includes(selectedDate);
                slotRadios.forEach(r => {
                    const id = parseInt(r.value);
                    const label = r.closest('.slot-option');
                    const isVenueBooked = venueTaken.includes(id);
                    const isUnavailable = isVenueBooked || isDateFull || alreadyBooked;
                    if (isUnavailable) {
                        label.classList.add('opacity-40', 'pointer-events-none');
                        r.disabled = true;
                    } else {
                        label.classList.remove('opacity-40', 'pointer-events-none');
                        r.disabled = false;
                    }
                    if (r.checked && isUnavailable) r.checked = false;
                });

                const anySelected = [...slotRadios].some(r => r.checked);
                if (!anySelected) {
                    document.getElementById('summaryTimeSlot').textContent = '—';
                }
            }

            function formatDateDisplay(dateStr) {
                if (!dateStr) return 'Not selected';
                const d = new Date(dateStr + 'T00:00:00');
                return d.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            }

            document.getElementById('calendarBtn').addEventListener('click', function () {
                input.focus();
            });

            flatpickr(input, {
                minDate: "today",
                dateFormat: "Y-m-d",
                onChange: function (selectedDates, dateStr) {
                    const section = document.getElementById('timeScheduleSection');
                    if (dateStr) {
                        section.classList.remove('hidden');
                        updateSlotAvailability(dateStr);
                        document.getElementById('summaryDate').textContent = formatDateDisplay(dateStr);
                        document.getElementById('summaryTimeSlot').textContent = '—';
                    } else {
                        section.classList.add('hidden');
                        document.getElementById('summaryDate').textContent = 'Not selected';
                        document.getElementById('summaryTimeSlot').textContent = '—';
                    }
                },
                onDayCreate: function (dObj, dStr, fp, dayElem) {
                    const dateStr = dayElem.dateObj ? dayElem.dateObj.toISOString().split('T')[0] : '';
                    const teams = assignedTeamsByDate[dateStr] || [];
                    if (teams.length > 0 && teams.length < totalTeams) {
                        dayElem.style.boxShadow = 'inset 0 -3px 0 #7c3aed';
                    }
                }
            });

            // Reset status when date field is cleared
            slotRadios.forEach(r => {
                r.addEventListener('change', function () {
                    const name = slotNames[parseInt(this.value)] || this.value;
                    slotStatus.textContent = 'Slot selected: ' + name;
                    slotStatus.className = 'text-xs mt-1 text-purple-600 font-semibold';
                    const label = this.closest('.slot-option');
                    const timeText = label ? label.querySelector('.slot-time').textContent.trim() : '—';
                    document.getElementById('summaryTimeSlot').textContent = timeText;
                });
            });
        });
    </script>
</body>

</html>