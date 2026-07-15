<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/notification_helper.php';

// --- Setup teams table and time_slot columns ---
$conn->query("CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$r = $conn->query("SELECT COUNT(*) as cnt FROM teams");
if ($r && $r->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO teams (name, description) VALUES
        ('Team Alpha', 'Morning shift team'),
        ('Team Beta',  'Evening shift team'),
        ('Team Gamma', 'Flexible team')");
}

$colCheck = $conn->query("SHOW COLUMNS FROM bookings LIKE 'time_slot'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE bookings ADD COLUMN time_slot ENUM('Morning','Evening') AFTER venue_id");
    $conn->query("ALTER TABLE bookings ADD COLUMN team_id INT AFTER time_slot");
    $conn->query("ALTER TABLE bookings ADD FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE");
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

$eventName = '—';
$venueName = '—';
$packageName = '—';

if ($eventId > 0) {
    $r = $conn->query("SELECT event_name FROM events WHERE id=$eventId");
    if ($r && $row = $r->fetch_assoc())
        $eventName = $row['event_name'];
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

// Fetch booked slots for this specific venue (non-cancelled)
$bookedSlots = [];
if ($venueId > 0) {
    $stmt = $conn->prepare("SELECT event_date, time_slot FROM bookings WHERE venue_id = ? AND status != 'Cancelled'");
    $stmt->bind_param("i", $venueId);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $bookedSlots[$row['event_date']][] = $row['time_slot'];
        }
    }
    $stmt->close();
}

// Fetch all teams for assignment
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

$timeSlotMap = [
    'Morning' => ['09:00:00', '12:00:00'],
    'Evening' => ['18:00:00', '21:00:00'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_date = $_POST['event_date'] ?? '';
    $time_slot = $_POST['time_slot'] ?? '';
    $address = $_POST['address'] ?? '';
    $eid = (int) ($_POST['event_id'] ?? 0);
    $vid = (int) ($_POST['venue_id'] ?? 0);
    $pid = (int) ($_POST['package_id'] ?? 0);
    $total = (float) ($_POST['total_cost'] ?? 0);
    $paymentMethodId = !empty($_POST['paymentmethods_id']) ? (int) $_POST['paymentmethods_id'] : 0;

    if ($eid > 0 && $vid > 0 && $pid > 0 && $event_date && $time_slot && isset($timeSlotMap[$time_slot])) {
        [$start_time, $end_time] = $timeSlotMap[$time_slot];

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

        // Check if this specific slot is already booked
        $stmt = $conn->prepare("SELECT id FROM bookings WHERE venue_id = ? AND event_date = ? AND time_slot = ? AND status != 'Cancelled'");
        $stmt->bind_param("iss", $vid, $event_date, $time_slot);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $message = "{$time_slot} slot on " . htmlspecialchars($event_date) . ' is already booked. Please choose another slot or date.';
        } else {
            $stmt->close();

            // Auto-assign a team (round-robin: team with fewest bookings for this slot)
            $teamId = null;
            $r = $conn->query("SELECT t.id, COUNT(b.id) as cnt FROM teams t LEFT JOIN bookings b ON b.team_id = t.id AND b.time_slot = '$time_slot' AND b.status != 'Cancelled' GROUP BY t.id ORDER BY cnt ASC LIMIT 1");
            if ($r && $row = $r->fetch_assoc())
                $teamId = (int) $row['id'];

            $stmt = $conn->prepare("INSERT INTO bookings (user_id, event_id, venue_id, package_id, time_slot, team_id, event_date, start_time, end_time, total_cost, status, paymentmethods_id, receipt_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)");
            $stmt->bind_param("iiiisissdsis", $userId, $eid, $vid, $pid, $time_slot, $teamId, $event_date, $start_time, $end_time, $total, $paymentMethodId, $receiptPath);
            if ($stmt->execute()) {
                $bookingId = $stmt->insert_id;
                $stmt->close();
                $dateStr = date('M j, Y', strtotime($event_date));
                $adminResult = $conn->query("SELECT id FROM admins");
                if ($adminResult) {
                    while ($admin = $adminResult->fetch_assoc()) {
                        createNotification($conn, $admin['id'], 'New Booking', "{$userName} booked {$eventName} on {$dateStr} ({$time_slot}) for " . number_format($total) . " MMK.", '../admin/bookings.php');
                    }
                }
                header("Location: booking_success.php?booking_id=$bookingId");
                exit();
            }
            $stmt->close();
            $message = 'Failed to create booking. Please try again.';
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
            border-color: #7c3aed;
            background: #f5f3ff;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
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
            background: #f5f3ff;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
        }
    </style>
</head>

<body>

    <?php include '../includes/header.php'; ?>



    <section class="max-w-4xl mx-auto px-2 sm:px-3 py-5">
        <div class="bg-white rounded-2xl shadow-[0_12px_40px_rgba(0,0,0,0.12)] border border-gray-100 p-2 md:p-3">

            <div class="flex items-start justify-between mb-2 gap-2">
                <div class="flex-1 flex flex-col items-center justify-center text-center">
                    <h2 class="text-2xl font-extrabold text-purple-600/60 text-center">Complete Your Booking</h2>
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
                        <form id="bookingForm" method="POST" enctype="multipart/form-data" class="space-y-3"
                            onsubmit="return confirmBooking()">

                            <input type="hidden" name="event_id" value="<?= $eventId ?>">
                            <input type="hidden" name="venue_id" value="<?= $venueId ?>">
                            <input type="hidden" name="package_id" value="<?= $packageId ?>">
                            <input type="hidden" name="total_cost" value="<?= $totalCost ?>">
                            <input type="hidden" name="paymentmethods_id" id="payment_method_id" value="">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Full
                                        Name</label>
                                    <input type="text" name="full_name" value="<?= htmlspecialchars($userName) ?>"
                                        placeholder="Jane Doe"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-purple-500 outline-none transition"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Email
                                        Address</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($userEmail) ?>"
                                        placeholder="jane@example.com"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-purple-500 outline-none transition"
                                        required>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Phone
                                        Number</label>
                                    <input type="tel" name="phone" value="<?= htmlspecialchars($userPhone) ?>"
                                        placeholder="+959..."
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-purple-500 outline-none transition"
                                        required>
                                </div>

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

                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-2">Time
                                    Slot</label>
                                <div id="timeSlotGroup" class="grid grid-cols-2 gap-3">
                                    <label
                                        class="slot-option border border-gray-300 rounded-xl p-2 text-center cursor-pointer transition hover:border-purple-400"
                                        onclick="selectSlot(this)">
                                        <input type="radio" name="time_slot" value="Morning" class="hidden" required>
                                        <div class="text-md font-bold text-gray-700">☀️ Morning</div>
                                        <div class="text-xs text-gray-400">9:00 AM – 12:00 PM</div>
                                    </label>
                                    <label
                                        class="slot-option border border-gray-300 rounded-xl p-2 text-center cursor-pointer transition hover:border-purple-400"
                                        onclick="selectSlot(this)">
                                        <input type="radio" name="time_slot" value="Evening" class="hidden" required>
                                        <div class="text-md font-bold text-gray-700">🌙 Evening</div>
                                        <div class="text-xs text-gray-400">6:00 PM – 9:00 PM</div>
                                    </label>
                                </div>
                                <p id="slotStatus" class="text-xs mt-1 text-gray-400">Select a date first to check slot
                                    availability.</p>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Upload
                                    Receipt</label>
                                <input type="file" name="receipt" accept="image/*,.pdf" required
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-purple-500 outline-none transition text-sm">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Payment
                                    Method</label>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <?php foreach ($paymentMethods as $pm):
                                        $logo = $logoMap[$pm['payment_name']] ?? strtolower($pm['payment_name']) . '.png';
                                        $qr = $qrMap[$pm['payment_name']] ?? strtolower($pm['payment_name']) . '_qr.png';
                                        ?>
                                        <div class="pm-card rounded-lg border border-gray-300 p-1 text-center w-15 h-13 flex flex-col items-center justify-center cursor-pointer"
                                            data-id="<?= $pm['id'] ?>" data-qr="<?= $base . $qr ?>"
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

                            <div class="grid grid-cols-2 gap-3 pt-1">
                                <button type="submit"
                                    class="bg-purple-600/60 text-white py-2 rounded-lg font-bold hover:bg-purple-800 transition shadow-md shadow-purple-200">Confirm
                                    Booking</button>
                                <button type="button" onclick="window.history.back()"
                                    class="bg-white text-gray-700 py-2 rounded-lg font-bold border border-gray-200 hover:bg-gray-50 transition">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Booking Summary -->
                <div class="lg:col-span-1">
                    <div class="rounded-2xl border border-gray-200 p-4 bg-white shadow-sm  top-4">
                        <h3 class="text-lg font-bold mb-3 text-gray-800">Booking Summary</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Event</span>
                                <span class="font-semibold text-gray-900"><?= htmlspecialchars($eventName) ?></span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Package</span>
                                <span class="font-semibold text-gray-900"><?= htmlspecialchars($packageName) ?></span>
                            </div>
                            <div class="flex justify-between items-start text-sm text-gray-600">
                                <span class="shrink-0">Venue</span>
                                <span class="font-semibold text-gray-900 text-right max-w-[65%] break-words">
                                    <?= htmlspecialchars($venueName ?? '') ?>
                                </span>
                            </div>
                            <hr class="border-gray-200">
                            <div class="flex justify-between text-lg font-bold">
                                <span>Total</span>
                                <span class="text-purple-600/60"><?= number_format($totalCost) ?> MMK</span>
                            </div>
                        </div>
                        <hr class="border-gray-200 my-3">
                        <div class="text-center" id="summaryQrSection">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">Pay with <span
                                    id="summaryPmName">KBZPay</span></p>
                            <img id="summaryQr" src="<?= $base ?>kpayqr.jpg"
                                class="w-40 h-40 mx-auto rounded-xl border border-gray-200 shadow-sm">
                            <p class="text-[10px] text-gray-400 mt-2">Scan to pay</p>
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
            <img id="qrImage" src="" alt="QR Code"
                class="w-56 h-56 mx-auto rounded-xl border border-gray-200 shadow-sm">
            <p class="text-xs text-gray-400 mt-4">Open your mobile banking app and scan this QR code to complete
                payment.</p>
            <button onclick="closeQr()"
                class="mt-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-6 rounded-xl text-sm transition">Close</button>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>

    <script>
        let selectedPm = null;

        function selectPayment(el) {
            document.querySelectorAll('.pm-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('payment_method_id').value = el.dataset.id;
            selectedPm = el;

            // Update summary QR section
            const pmName = el.querySelector('span') ? el.querySelector('span').textContent.trim() : 'KBZPay';
            const qrSrc = el.dataset.qr || '<?= $base ?>kpayqr.jpg';
            document.getElementById('summaryPmName').textContent = pmName;
            document.getElementById('summaryQr').src = qrSrc;
        }

        function closeQr() {
            document.getElementById('qrModal').classList.remove('open');
        }

        function selectSlot(el) {
            document.querySelectorAll('.slot-option').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            el.querySelector('input[type="radio"]').checked = true;
        }

        function confirmBooking() {
            if (!document.getElementById('payment_method_id').value) {
                Swal.fire({ icon: 'error', title: 'Payment required', text: 'Please select a payment method.', confirmButtonColor: '#7c3aed' });
                return false;
            }
            Swal.fire({
                title: 'Confirm booking?',
                text: 'You are about to finalize your event request.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#d1d5db',
                confirmButtonText: 'Yes, Confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('bookingForm').submit();
                }
            });
            return false;
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

                    // Update summary QR on load
                    const pmName = kpayCard.querySelector('span') ? kpayCard.querySelector('span').textContent.trim() : 'KBZPay';
                    document.getElementById('summaryPmName').textContent = pmName;
                    document.getElementById('summaryQr').src = kpayCard.dataset.qr || '<?= $base ?>kpayqr.jpg';
                }
            }

            const bookedSlots = <?= json_encode($bookedSlots) ?>;
            const input = document.getElementById("eventDatePicker");
            const slotRadios = document.querySelectorAll('input[name="time_slot"]');
            const slotStatus = document.getElementById('slotStatus');

            // Disable dates where BOTH slots are booked
            const fullyBookedDates = Object.keys(bookedSlots).filter(d =>
                bookedSlots[d].includes('Morning') && bookedSlots[d].includes('Evening')
            );

            function updateSlotAvailability(selectedDate) {
                const taken = bookedSlots[selectedDate] || [];
                slotRadios.forEach(r => {
                    const label = r.closest('.slot-option');
                    const isTaken = taken.includes(r.value);
                    if (isTaken) {
                        label.classList.add('opacity-40', 'pointer-events-none', 'border-red-300');
                        label.classList.remove('hover:border-purple-400', 'selected');
                        r.disabled = true;
                    } else {
                        label.classList.remove('opacity-40', 'pointer-events-none', 'border-red-300');
                        label.classList.add('hover:border-purple-400');
                        r.disabled = false;
                    }
                    if (r.checked && isTaken) r.checked = false;
                });

                const available = [...slotRadios].filter(r => !r.disabled);
                if (available.length === 0) {
                    slotStatus.textContent = 'Both slots are booked on this date.';
                    slotStatus.className = 'text-xs mt-1 text-red-500 font-semibold';
                } else if (available.length === 1) {
                    slotStatus.textContent = 'Only ' + available[0].value + ' slot is available.';
                    slotStatus.className = 'text-xs mt-1 text-orange-500 font-semibold';
                } else {
                    slotStatus.textContent = 'Both slots are available.';
                    slotStatus.className = 'text-xs mt-1 text-green-600 font-semibold';
                }
            }

            flatpickr(input, {
                minDate: "today",
                dateFormat: "Y-m-d",
                disable: fullyBookedDates,
                onChange: function (selectedDates, dateStr) {
                    if (dateStr) updateSlotAvailability(dateStr);
                },
                onDayCreate: function (dObj, dStr, fp, dayElem) {
                    const dateStr = dayElem.dateObj ? dayElem.dateObj.toISOString().split('T')[0] : '';
                    const taken = bookedSlots[dateStr] || [];
                    if (taken.length > 0) {
                        const tips = taken.map(s => s + ' booked').join(', ');
                        dayElem.title = tips;
                        if (taken.includes('Morning')) dayElem.style.boxShadow = 'inset 0 -3px 0 #fbbf24';
                        if (taken.includes('Evening')) dayElem.style.boxShadow = 'inset 0 -3px 0 #6366f1';
                        if (taken.length === 2) dayElem.style.opacity = '0.4';
                    }
                }
            });

            // Reset status when date field is cleared
            slotRadios.forEach(r => {
                r.addEventListener('change', function () {
                    slotStatus.textContent = 'Slot selected: ' + this.value;
                    slotStatus.className = 'text-xs mt-1 text-purple-600 font-semibold';
                });
            });
        });
    </script>
</body>

</html>