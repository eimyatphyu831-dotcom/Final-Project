<?php
session_start();
require_once '../config/db.php';

$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
$booking = null;

if ($bookingId > 0) {
    $r = $conn->query("SELECT b.id, b.event_date, b.total_cost, b.status, b.created_at,
                              u.name AS customer_name,
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
                       WHERE b.id = $bookingId");
    if ($r)
        $booking = $r->fetch_assoc();
}

$pageTitle = "Booking Confirmed";
include '../includes/header.php';
?>

<div class="max-w-lg mx-auto px-4 py-4">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-4 text-center">

        <div class="mx-auto w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mb-2">
            <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h1 class="text-2xl font-extrabold text-slate-800 mb-2">Booking Confirmed</h1>
        <p class="text-slate-500 text-sm mb-4 leading-relaxed">
            Thank you for your booking. Your request has been submitted successfully and our team will review it
            shortly.
        </p>

        <div class="bg-slate-50 rounded-2xl p-5 mb-8 text-left space-y-1">
            <h2 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2">Booking Summary</h2>

            <?php if ($booking): ?>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-slate-600">Event</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($booking['event_name']) ?></span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-slate-600">Package</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($booking['package_name']) ?> Package</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-slate-600">Venue</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($booking['venue_name']) ?></span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-slate-600">Payment</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($booking['payment_name'] ?? '—') ?></span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-slate-600">Date</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($booking['event_date']) ?></span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-slate-600">Total</span>
                    <span class="font-bold text-slate-800"><?= number_format($booking['total_cost']) ?> MMK</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-slate-600">Status</span>
                    <span class="flex items-center gap-1.5 font-bold text-yellow-600">
                        <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                        Pending Approval
                    </span>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500">Booking details not available.</p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <a href="my_bookings.php"
                class="bg-[#2a1b40] text-white py-3 rounded-xl text-sm font-semibold hover:bg-opacity-90 transition shadow-md">
                View My Bookings
            </a>
            <a href="index.php"
                class="bg-white text-slate-700 py-3 rounded-xl text-sm font-semibold border border-slate-200 hover:bg-slate-50 transition">
                Return Home
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>