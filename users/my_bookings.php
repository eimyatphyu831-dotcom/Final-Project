<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/db.php";
include "../includes/auto_complete_bookings.php";

$user_id = $_SESSION['user_id'];

$query = "SELECT b.id, b.event_date, b.total_cost, b.status, b.created_at,
                 p.name AS package_name, e.event_name, v.name AS venue_name, v.address AS location,
                 pm.payment_name,
                 ts.start_time, ts.end_time,
                 CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END AS has_review
          FROM bookings b
          JOIN packages p ON b.package_id = p.id
          JOIN events e ON b.event_id = e.id
          JOIN venues v ON b.venue_id = v.id
          LEFT JOIN payment_methods pm ON b.paymentmethods_id = pm.id
          LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
          LEFT JOIN reviews r ON r.booking_id = b.id AND r.user_id = ?
          WHERE b.user_id = ?
          ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = "My Bookings";
include "../includes/header.php";

$conn->close();
?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-purple-50/30">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <div class="text-center mb-10">
            <h1 class="text-2xl sm:text-3xl font-bold text-brand-600">My Bookings</h1>
            <p class="text-sm text-gray-500 mt-2">Track and manage all your event bookings in one place</p>
        </div>

        <?php if (count($bookings) === 0): ?>
            <div class="max-w-lg mx-auto">
                <div class="bg-white rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100 p-14 text-center">
                    <div class="mx-auto w-20 h-20 bg-gradient-to-br from-purple-100 to-indigo-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-10 h-10 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">No Bookings Yet</h3>
                    <p class="text-sm text-gray-400 mb-8 leading-relaxed">You haven't made any bookings yet.<br>Start planning your dream event today!</p>
                    <a href="events.php"
                        class="inline-flex items-center gap-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-7 py-3 rounded-xl text-sm font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 shadow-lg shadow-purple-200 hover:shadow-xl hover:shadow-purple-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Browse Events
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                        $statusColor = match ($booking['status']) {
                            'Confirmed' => 'emerald',
                            'Cancelled' => 'red',
                            'Pending'   => 'amber',
                            'Completed' => 'blue',
                            default     => 'gray'
                        };
                        $borderAccent = match ($booking['status']) {
                            'Confirmed' => 'border-l-emerald-400',
                            'Cancelled' => 'border-l-red-400',
                            'Pending'   => 'border-l-amber-400',
                            'Completed' => 'border-l-blue-400',
                            default     => 'border-l-gray-300'
                        };
                    ?>
                    <div class="group bg-white rounded-2xl shadow-sm hover:shadow-xl border border-gray-100 hover:border-gray-200 border-l-4 <?= $borderAccent ?> p-6 transition-all duration-300">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-3 mb-3">
                                    <h3 class="text-lg font-bold text-gray-900 truncate">
                                        <?= htmlspecialchars($booking['event_name']) ?>
                                    </h3>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold
                                        <?= match ($booking['status']) {
                                            'Confirmed' => 'bg-emerald-50 text-emerald-700',
                                            'Cancelled' => 'bg-red-50 text-red-700',
                                            'Pending' => 'bg-amber-50 text-amber-700',
                                            'Completed' => 'bg-blue-50 text-blue-700',
                                            default => 'bg-gray-100 text-gray-600'
                                        } ?>">
                                        <span class="w-1.5 h-1.5 rounded-full
                                            <?= match ($booking['status']) {
                                                'Confirmed' => 'bg-emerald-500',
                                                'Cancelled' => 'bg-red-500',
                                                'Pending' => 'bg-amber-500',
                                                'Completed' => 'bg-blue-500',
                                                default => 'bg-gray-400'
                                            } ?>">
                                        </span>
                                        <?= htmlspecialchars($booking['status']) ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-y-3 gap-x-4 text-sm text-gray-500">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                            </svg>
                                        </div>
                                        <span class="truncate font-medium text-gray-700"><?= htmlspecialchars($booking['package_name']) ?></span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <span class="font-medium text-gray-700"><?= htmlspecialchars($booking['venue_name']) ?></span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <span class="font-medium text-gray-700"><?= date('M j, Y', strtotime($booking['event_date'])) ?></span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <span class="font-medium text-gray-700"><?= date('g:i A', strtotime($booking['start_time'])) ?> – <?= date('g:i A', strtotime($booking['end_time'])) ?></span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                        </div>
                                        <span class="font-medium text-gray-700"><?= htmlspecialchars($booking['payment_name'] ?? '—') ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-row lg:flex-col items-center lg:items-end gap-4 lg:gap-2 shrink-0 lg:pl-6 lg:border-l lg:border-gray-100">
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-gray-900"><?= number_format($booking['total_cost']) ?></p>
                                    <p class="text-[11px] font-semibold text-gray-400 tracking-wide uppercase">MMK</p>
                                </div>
                                <p class="text-[11px] text-gray-400 whitespace-nowrap">
                                    <span class="hidden lg:inline">Booked </span><?= date('M j, Y', strtotime($booking['created_at'])) ?>
                                </p>
                                <?php if ($booking['status'] === 'Completed'): ?>
                                    <?php if ($booking['has_review']): ?>
                                        <span
                                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-100 text-gray-500 rounded-xl text-xs font-semibold border border-gray-200">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                            Reviewed
                                        </span>
                                    <?php else: ?>
                                        <a href="my_reviews.php"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gradient-to-r from-purple-50 to-indigo-50 text-purple-700 rounded-xl text-xs font-semibold hover:from-purple-100 hover:to-indigo-100 transition-all duration-200 border border-purple-200/50">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                            Write Review
                                        </a>
                                    <?php endif; ?>
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