<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/db.php";

$user_id = $_SESSION['user_id'];

$query = "SELECT b.id, b.event_date, b.total_cost, b.status, b.created_at,
                 p.name AS package_name, e.event_name, v.name AS venue_name, v.address AS location,
                 pm.payment_name
          FROM bookings b
          JOIN packages p ON b.package_id = p.id
          JOIN events e ON b.event_id = e.id
          JOIN venues v ON b.venue_id = v.id
          LEFT JOIN payment_methods pm ON b.paymentmethods_id = pm.id
          WHERE b.user_id = ?
          ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = "My Bookings";
include "../includes/header.php";

$conn->close();
?>

<div class="min-h-screen bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-brand-600">My Bookings</h1>
                <p class="text-sm text-gray-500 mt-1">View all your event bookings and their status</p>
            </div>
            <!-- <a href="bookingform.php"
                class="bg-brand-200 hover:bg-brand-900 text-brand-900 hover:text-white px-5 py-2.5 rounded-full text-sm transition duration-200 font-semibold">
                + New Booking
            </a> -->
        </div>

        <?php if (count($bookings) === 0): ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-400 mb-2">No bookings yet</h3>
                <p class="text-sm text-gray-400 mb-6">Start planning your next event!</p>
                <a href="events.php"
                    class="bg-brand-200 hover:bg-brand-900 text-brand-900 hover:text-white px-5 py-2.5 rounded-full text-sm transition duration-200 font-semibold">
                    Browse Events
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($bookings as $booking): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 hover:shadow-md transition">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-brand-900">
                                        <?= htmlspecialchars($booking['event_name']) ?>
                                    </h3>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
                                        <?= match ($booking['status']) {
                                            'Confirmed' => 'bg-green-100 text-green-700',
                                            'Cancelled' => 'bg-red-100 text-red-700',
                                            'Pending' => 'bg-yellow-100 text-yellow-700',
                                            default => 'bg-gray-100 text-gray-600'
                                        } ?>">
                                        <?= htmlspecialchars($booking['status']) ?>
                                    </span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm text-gray-500">
                                    <div class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-brand-600" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                        </svg>
                                        <span><?= htmlspecialchars($booking['package_name']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-brand-600" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span><?= htmlspecialchars($booking['venue_name']) ?>,
                                            <?= htmlspecialchars($booking['location']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-brand-600" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span><?= date('F j, Y', strtotime($booking['event_date'])) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-brand-600" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <span><?= htmlspecialchars($booking['payment_name'] ?? '—') ?></span>
                                    </div>
                            
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xl font-bold text-brand-900"><?= number_format($booking['total_cost']) ?> MMK</p>
                                <p class="text-xs text-gray-400 mt-1">Booked on
                                    <?= date('M j, Y', strtotime($booking['created_at'])) ?></p>
                                <?php if ($booking['status'] === 'Confirmed'): ?>
                                    <a href="reviews.php"
                                        class="inline-block mt-2 text-xs text-brand-600 hover:text-brand-700 font-semibold">
                                        <svg class="w-3.5 h-3.5 inline mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        Write Review
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "../includes/footer.php"; ?>