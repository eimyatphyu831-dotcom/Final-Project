<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/notification_helper.php';

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

// Fetch current user's booked dates (non-cancelled)
$bookedDates = [];
$r = $conn->query("SELECT DISTINCT event_date FROM bookings WHERE status != 'Cancelled'");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $bookedDates[] = $row['event_date'];
    }
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_date = $_POST['event_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $address = $_POST['address'] ?? '';
    $eid = (int) ($_POST['event_id'] ?? 0);
    $vid = (int) ($_POST['venue_id'] ?? 0);
    $pid = (int) ($_POST['package_id'] ?? 0);
    $total = (float) ($_POST['total_cost'] ?? 0);
    $paymentMethodId = !empty($_POST['paymentmethods_id']) ? (int) $_POST['paymentmethods_id'] : null;

    if ($eid > 0 && $vid > 0 && $pid > 0 && $event_date && $start_time) {
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

        // Check if the venue is already booked on this date
        $stmt = $conn->prepare("SELECT id FROM bookings WHERE venue_id = ? AND event_date = ? AND status != 'Cancelled'");
        $stmt->bind_param("is", $vid, $event_date);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $message = 'This venue is already booked on ' . htmlspecialchars($event_date) . '. Please choose another date.';
        } else {
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO bookings (user_id, event_id, venue_id, package_id, event_date, start_time, total_cost, status, paymentmethods_id, receipt_image) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)");
            $stmt->bind_param("iiiissdis", $userId, $eid, $vid, $pid, $event_date, $start_time, $total, $paymentMethodId, $receiptPath);
            if ($stmt->execute()) {
                $bookingId = $stmt->insert_id;
                $stmt->close();
                // Notify all admins about new booking
                $dateStr = date('M j, Y', strtotime($event_date));
                $adminResult = $conn->query("SELECT id FROM admins");
                if ($adminResult) {
                    while ($admin = $adminResult->fetch_assoc()) {
                        createNotification($conn, $admin['id'], 'New Booking', "{$userName} booked {$eventName} on {$dateStr} for " . number_format($total) . " MMK.", '../admin/bookings.php');
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


    $stmt = $conn->prepare("
    SELECT id FROM bookings 
    WHERE venue_id = ? 
    AND event_date = ? 
    AND status != 'Cancelled'
");
    $stmt->bind_param("is", $vid, $event_date);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $message = "This date is already booked. Please choose another date.";
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
    </style>
</head>

<body>

    <?php include '../includes/header.php'; ?>



    <section class="max-w-3xl mx-auto px-2 sm:px-3 py-10">
        <div class="bg-white rounded-2xl shadow-[0_12px_40px_rgba(0,0,0,0.12)] border border-gray-100 p-2 md:p-3">

            <div class="flex items-start justify-between mb-3 gap-3">
                <div class="flex-1 flex flex-col items-center justify-center text-center">
                    <h2 class="text-2xl font-extrabold text-purple-600/60 text-center">Complete Your Booking</h2>
                    <p class="text-gray-500 mt-1 text-center text-sm">Finalize your event request details.</p>
                </div>
                <a href="index.php" class="text-gray-400 hover:text-purple-600 transition flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </a>
            </div>

            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="grid lg:grid-cols-[1fr_0.8fr] gap-3 items-start">
                <div class="rounded-2xl border border-gray-200 p-3 bg-white shadow-sm">
                    <form id="bookingForm" method="POST" enctype="multipart/form-data" class="space-y-4"
                        onsubmit="return confirmBooking()">

                        <input type="hidden" name="event_id" value="<?= $eventId ?>">
                        <input type="hidden" name="venue_id" value="<?= $venueId ?>">
                        <input type="hidden" name="package_id" value="<?= $packageId ?>">
                        <input type="hidden" name="total_cost" value="<?= $totalCost ?>">
                        <input type="hidden" name="paymentmethods_id" id="payment_method_id" value="">

                        <div class="grid md:grid-cols-2 gap-4">
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

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Phone
                                    Number</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($userPhone) ?>"
                                    placeholder="+959..."
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-purple-500 outline-none transition"
                                    required>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Event
                                    Date</label>
                                <input type="date" name="event_date" id="eventDatePicker"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-purple-500 outline-none transition"
                                    required>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">

                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Start
                                    Time</label>
                                <input type="time" name="start_time"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-purple-500 outline-none transition"
                                    required>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">End Time</label>
                                <input type="time" name="end_time"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-purple-500 outline-none transition"
                                    required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Upload
                                Receipt</label>
                            <input type="file" name="receipt" accept="image/*,.pdf"
                                class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-purple-500 outline-none transition text-sm">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Payment
                                Method</label>
                            <div class="grid grid-cols-2 gap-3">
                                <?php foreach ($paymentMethods as $pm):
                                    $logo = $logoMap[$pm['payment_name']] ?? strtolower($pm['payment_name']) . '.png';
                                    $qr = $qrMap[$pm['payment_name']] ?? strtolower($pm['payment_name']) . '_qr.png';
                                    ?>
                                    <div class="pm-card rounded-xl border border-gray-200 p-3 text-center"
                                        data-id="<?= $pm['id'] ?>" data-qr="<?= $base . $qr ?>"
                                        onclick="selectPayment(this)">
                                        <img src="<?= $base . $logo ?>" alt="<?= htmlspecialchars($pm['payment_name']) ?>"
                                            class="h-10 mx-auto mb-1.5 object-contain">
                                        <span
                                            class="text-xs font-semibold text-gray-700"><?= htmlspecialchars($pm['payment_name']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-3">
                            <button type="submit"
                                class="bg-purple-600/60 text-white py-2 rounded-lg font-bold hover:bg-purple-800 transition shadow-md shadow-purple-200">Confirm
                                Booking</button>
                            <button type="button" onclick="window.history.back()"
                                class="bg-white text-gray-700 py-2 rounded-lg font-bold border border-gray-200 hover:bg-gray-50 transition">Cancel</button>
                        </div>
                    </form>
                </div>

                <div class="rounded-2xl border border-gray-200 p-3 bg-gray-50 shadow-sm">
                    <h3 class="text-lg font-bold mb-3 text-gray-800">Booking Summary</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm text-gray-600"><span>Event</span> <span
                                class="font-semibold text-gray-900"><?= htmlspecialchars($eventName) ?></span></div>
                        <div class="flex justify-between text-sm text-gray-600"><span>Package</span> <span
                                class="font-semibold text-gray-900"><?= htmlspecialchars($packageName) ?></span></div>
                        <div class="flex justify-between text-sm text-gray-600"><span>Venue</span> <span
                                class="font-semibold text-gray-900"><?= htmlspecialchars($venueName) ?></span></div>
                        <hr class="border-gray-200">
                        <div class="flex justify-between text-lg font-bold">
                            <span>Total</span>
                            <span
                                class="text-purple-600/60"><?= $totalCost > 0 ? number_format($totalCost) . ' MMK' : '—' ?></span>
                        </div>
                    </div>
                    <hr class="border-gray-200 my-3">
                    <div class="text-center" id="summaryQrSection">
                        <p class="text-xs font-bold text-gray-500 uppercase mb-2">Pay with <span
                                id="summaryPmName">KBZPay</span></p>
                        <img id="summaryQr" src="<?= $kpayQr ?: $base . 'kpayqr.jpg' ?>" alt="QR"
                            class="w-40 h-40 mx-auto rounded-xl border border-gray-200 shadow-sm">
                        <p class="text-[10px] text-gray-400 mt-2">Scan to pay</p>
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

            const name = el.querySelector('span').textContent;

            document.getElementById('summaryQr').src = el.dataset.qr;
            document.getElementById('summaryPmName').textContent = name;
        }

        function closeQr() {
            document.getElementById('qrModal').classList.remove('open');
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
                    document.getElementById('summaryQr').src = kpayCard.dataset.qr;
                    document.getElementById('summaryPmName').textContent = kpayCard.querySelector('span').textContent;
                }
            }

            const bookedDates = <?= json_encode($bookedDates) ?>;
            const input = document.getElementById("eventDatePicker");

            input.addEventListener("change", function () {
                if (bookedDates.includes(this.value)) {
                    alert("This date is already booked!");
                    this.value = "";
                }
            });
        });
    </script>
</body>

</html>