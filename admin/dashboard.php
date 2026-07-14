<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$stmt = $conn->prepare("SELECT profile_image FROM admins WHERE id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$adminImg = $stmt->get_result()->fetch_assoc()['profile_image'] ?? null;
$stmt->close();
$adminAvatar = $adminImg ? 'uploads/profile/' . $adminImg : null;

$search = $_GET['search'] ?? '';
$period = $_GET['period'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build date condition
$dateCond = '';
$dateLabel = '';
if ($period === 'this_week') {
    $start = date('Y-m-d', strtotime('monday this week'));
    $end = date('Y-m-d', strtotime('sunday this week'));
    $dateCond = "AND b.created_at >= '$start' AND b.created_at <= '$end 23:59:59'";
    $dateLabel = date('M j', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end));
} elseif ($period === 'last_week') {
    $start = date('Y-m-d', strtotime('monday last week'));
    $end = date('Y-m-d', strtotime('sunday last week'));
    $dateCond = "AND b.created_at >= '$start' AND b.created_at <= '$end 23:59:59'";
    $dateLabel = date('M j', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end));
} elseif ($dateFrom && $dateTo) {
    $dateCond = "AND DATE(b.created_at) >= '$dateFrom' AND DATE(b.created_at) <= '$dateTo'";
    $dateLabel = date('M j, Y', strtotime($dateFrom)) . ' - ' . date('M j, Y', strtotime($dateTo));
} else {
    $dateLabel = 'All Time';
}

// Search condition
$searchCond = '';
if ($search) {
    $s = $conn->real_escape_string($search);
    $searchCond = "AND (e.event_name LIKE '%$s%' OR u.name LIKE '%$s%')";
}

$bkJoin = "FROM bookings b JOIN events e ON b.event_id = e.id JOIN users u ON b.user_id = u.id";
$bkWhere = "WHERE 1=1 $dateCond $searchCond";

$totalEvents = $conn->query("SELECT COUNT(*) c FROM events")->fetch_assoc()['c'] ?? 0;
$totalCustomers = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0;
$totalBookings = $conn->query("SELECT COUNT(*) c $bkJoin $bkWhere")->fetch_assoc()['c'] ?? 0;
$totalRevenue = $conn->query("SELECT COALESCE(SUM(total_cost),0) c $bkJoin $bkWhere AND b.status='Confirmed'")->fetch_assoc()['c'] ?? 0;
$pendingCount = $conn->query("SELECT COUNT(*) c $bkJoin $bkWhere AND b.status='Pending'")->fetch_assoc()['c'] ?? 0;

$notifications = [];
$bookingStatuses = $conn->query("SELECT b.status, COUNT(*) cnt $bkJoin $bkWhere GROUP BY b.status")->fetch_all(MYSQLI_ASSOC);

$pending = 0;
$confirmed = 0;
$cancelled = 0;
foreach ($bookingStatuses as $bs) {
    if ($bs['status'] === 'Pending')
        $pending = $bs['cnt'];
    elseif ($bs['status'] === 'Confirmed')
        $confirmed = $bs['cnt'];
    elseif ($bs['status'] === 'Cancelled')
        $cancelled = $bs['cnt'];
}

$recentBookings = $conn->query("SELECT b.id, b.event_date, b.total_cost, b.status,
                                       e.event_name, u.name AS customer_name
                                $bkJoin $bkWhere
                                ORDER BY b.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$topEvents = $conn->query("SELECT e.event_name, COUNT(b.id) AS cnt
                           FROM events e
                           LEFT JOIN bookings b ON b.event_id = e.id $dateCond $searchCond
                           GROUP BY e.id, e.event_name
                           ORDER BY cnt DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

function time_elapsed($datetime)
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)
        return 'Just now';
    if ($diff < 3600)
        return floor($diff / 60) . 'm ago';
    if ($diff < 86400)
        return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

$weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$thisWeekData = [];
$lastWeekData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $lw = date('Y-m-d', strtotime("-$i days -1 week"));
    $thisWeekData[] = (int) ($conn->query("SELECT COUNT(*) c FROM bookings WHERE DATE(created_at)='$d'")->fetch_assoc()['c'] ?? 0);
    $lastWeekData[] = (int) ($conn->query("SELECT COUNT(*) c FROM bookings WHERE DATE(created_at)='$lw'")->fetch_assoc()['c'] ?? 0);
}

// Last month data: daily booking counts for the previous calendar month
$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
$lastMonthEnd = date('Y-m-t', strtotime('first day of last month'));
$lastMonthLabels = [];
$lastMonthValues = [];
$daysInLastMonth = (int) date('t', strtotime('first day of last month'));
for ($i = 1; $i <= $daysInLastMonth; $i++) {
    $d = sprintf('%s-%02d', date('Y-m', strtotime('first day of last month')), $i);
    $lastMonthLabels[] = $i;
    $lastMonthValues[] = (int) ($conn->query("SELECT COUNT(*) c FROM bookings WHERE DATE(created_at)='$d'")->fetch_assoc()['c'] ?? 0);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventPlan Admin Panel</title>
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


        .custom-scroll::-webkit-scrollbar {
            width: 4px;
            /* scrollbar size */
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background: #c4b5fd;
            /* purple light */
            border-radius: 9999px;
        }

        .custom-scroll::-webkit-scrollbar-thumb:hover {
            background: #a78bfa;
        }

        /* Firefox */
        .custom-scroll {
            scrollbar-width: thin;
            scrollbar-color: #c4b5fd transparent;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen overflow-hidden">

    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col ml-64">
            <!-- Header -->
            <?php include 'admin_header.php'; ?>

            <main class="flex-1 p-6 space-y-6 overflow-y-auto">

                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="?period=all<?= $search ? '&search=' . urlencode($search) : '' ?>"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition <?= $period === 'all' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">All
                            Time</a>
                        <a href="?period=this_week<?= $search ? '&search=' . urlencode($search) : '' ?>"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition <?= $period === 'this_week' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">This
                            Week</a>
                        <a href="?period=last_week<?= $search ? '&search=' . urlencode($search) : '' ?>"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition <?= $period === 'last_week' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">Last
                            Week</a>
                        <form method="GET" class="flex items-center gap-2 ml-2">
                            <input type="date" name="date_from" value="<?= $dateFrom ?>"
                                class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:border-purple-400">
                            <span class="text-xs text-gray-400">—</span>
                            <input type="date" name="date_to" value="<?= $dateTo ?>"
                                class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:border-purple-400">
                            <?php if ($search): ?><input type="hidden" name="search"
                                    value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                            <button type="submit"
                                class="px-3 py-1.5 bg-purple-600 text-white text-xs rounded-lg hover:bg-purple-700 transition font-medium">View</button>
                        </form>
                        <div
                            class="bg-white border border-gray-200 px-3 py-1.5 rounded-lg flex items-center gap-2 text-xs text-gray-500">
                            <i class="fa-regular fa-calendar text-gray-400"></i>
                            <span class="font-medium text-gray-700"><?= $dateLabel ?></span>
                        </div>
                    </div>
                </div>

                <!-- Dashboard -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                    <div
                        class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-3 hover:shadow-md transition">
                        <div
                            class="w-10 h-10 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center text-base">
                            <i class="fa-regular fa-calendar-check"></i>
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs text-gray-400 font-medium">Total Events</p>
                            <p class="text-2xl font-bold text-gray-600"><?= $totalEvents ?></h3>
                        </div>
                    </div>
                    <div
                        class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-3 hover:shadow-md transition">
                        <div
                            class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-base">
                            <i class="fa-solid fa-users-rectangle"></i>
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs text-gray-400 font-medium">Total Bookings</p>
                            <p class="text-2xl font-bold text-gray-600"><?= $totalBookings ?></h3>
                        </div>
                    </div>
                    <div
                        class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-3 hover:shadow-md transition">
                        <div
                            class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-base">
                            <i class="fa-solid fa-dollar-sign"></i>
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs text-gray-400 font-medium">Revenue (MMK)</p>
                            <p class="text-2xl font-bold text-gray-600"><?= number_format($totalRevenue) ?></h3>
                        </div>
                    </div>
                    <div
                        class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-3 hover:shadow-md transition">
                        <div
                            class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center text-base">
                            <i class="fa-regular fa-user"></i>
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs text-gray-400 font-medium">Customers</p>
                            <p class="text-2xl font-bold text-gray-600"><?= $totalCustomers ?></h3>
                        </div>
                    </div>

                </div>


                <!-- Recent bookings -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div
                        class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between">
                        <div>
                            <h3 class="text-base font-bold text-gray-800 mb-4">Recent Bookings</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="text-xs font-semibold text-gray-400 border-b border-gray-100">
                                            <!-- <th class="pb-3 font-medium">Booking ID</th> -->
                                            <th class="pb-3 font-medium">Event Name</th>
                                            <th class="pb-3 font-medium">Customer</th>
                                            <th class="pb-3 font-medium">Date</th>
                                            <th class="pb-3 font-medium">Amount</th>
                                            <th class="pb-3 font-medium">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm text-gray-700 divide-y divide-gray-50">
                                        <?php foreach ($recentBookings as $rb): ?>
                                            <tr class="hover:bg-gray-50/50">
                                                <!-- <td class="py-3.5 text-gray-400 text-xs font-medium">#BK-<?= str_pad($rb['id'], 4, '0', STR_PAD_LEFT) ?></td> -->
                                                <td class="py-3.5 font-semibold text-gray-800">
                                                    <?= htmlspecialchars($rb['event_name']) ?>
                                                </td>
                                                <td class="py-3.5 text-gray-500">
                                                    <?= htmlspecialchars($rb['customer_name']) ?>
                                                </td>
                                                <td class="py-3.5 text-gray-500">
                                                    <?= date('M j, Y', strtotime($rb['event_date'])) ?>
                                                </td>
                                                <td class="py-3.5 font-medium text-gray-800">
                                                    <?= number_format($rb['total_cost']) ?> MMK
                                                </td>
                                                <td class="py-3.5">
                                                    <span
                                                        class="px-2.5 py-1 text-xs font-medium rounded-lg
                                                  <?= $rb['status'] === 'Confirmed' ? 'text-emerald-600 bg-emerald-50' : ($rb['status'] === 'Cancelled' ? 'text-rose-600 bg-rose-50' : 'text-amber-600 bg-amber-50') ?>">
                                                        <?= $rb['status'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($recentBookings)): ?>
                                            <tr>
                                                <td colspan="6" class="py-4 text-center text-gray-400 text-sm">No bookings
                                                    yet
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-50 text-center">
                            <a href="bookings.php"
                                class="text-xs font-semibold text-purple-brand hover:text-purple-700 inline-flex items-center gap-2">View
                                All Bookings <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <!-- Top events -->
                    <div
                        class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-gray-800">Top Events</h3>
                            <a href="events.php" class="text-xs text-purple-brand font-medium hover:underline">View
                                All</a>
                        </div>
                        <div class="space-y-4 h-full flex flex-col justify-between">
                            <?php foreach ($topEvents as $i => $te): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-bold text-gray-400 w-4"><?= $i + 1 ?></span>
                                        <div
                                            class="w-12 h-10 rounded-lg bg-gradient-to-br from-purple-50 to-indigo-50 flex items-center justify-center text-purple-400 font-bold text-sm">
                                            <?= strtoupper(substr($te['event_name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-bold text-gray-800">
                                                <?= htmlspecialchars($te['event_name']) ?>
                                            </h4>
                                            <span class="text-[11px] text-gray-400"><?= $te['cnt'] ?> Bookings</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($topEvents)): ?>
                                <p class="text-xs text-gray-400 text-center py-4">No events yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Booking overwiew -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div
                        class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between">

                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-gray-800">
                                Booking Overview
                            </h3>

                            <!-- <select id="bookingFilter"
                                class="border border-gray-200 text-xs px-3 py-1.5 rounded-lg bg-white font-medium text-gray-600 focus:outline-none">

                                <option value="week">This Week</option>
                                <option value="month">Last Month</option>

                            </select> -->
                        </div>

                        <div class="h-64">
                            <canvas id="bookingOverviewChart"></canvas>
                        </div>

                    </div>
                    <!-- Event status -->
                    <div
                        class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-gray-800">Event Status</h3>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <div class="w-1/2 h-40 relative flex items-center justify-center mb-16">
                                <canvas id="eventStatusChart"></canvas>
                                <div class="absolute text-center">
                                    <h4 class="text-2xl font-bold text-gray-800 leading-none"><?= $totalBookings ?>
                                    </h4>
                                    <span
                                        class="text-[10px] uppercase text-gray-400 font-semibold tracking-wider">Total</span>
                                </div>
                            </div>
                            <div class="w-1/2 space-y-4 text-xs text-gray-600 mb-14">

                                <div class="flex justify-between items-center">
                                    <span class="flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                                        Pending
                                    </span>

                                    <span class="font-semibold text-gray-800 flex items-center gap-1 whitespace-nowrap">
                                        <span>
                                            <?= $pending ?>
                                        </span>
                                        <span>(
                                            <?= $totalBookings > 0 ? round($pending / $totalBookings * 100) : 0 ?>%)
                                        </span>
                                    </span>
                                </div>

                                <div class="flex justify-between items-center gap-1">
                                    <span class="flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                        Confirmed
                                    </span>

                                    <span class="font-semibold text-gray-800 flex items-center whitespace-nowrap">
                                        <span>
                                            <?= $confirmed ?>
                                        </span>
                                        <span class="ml-0.5">(
                                            <?= $totalBookings > 0 ? round($confirmed / $totalBookings * 100) : 0 ?>%)
                                        </span>
                                    </span>
                                </div>
                                <!-- <div class="flex justify-between items-center"><span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-rose-400"></span> Cancelled</span><span class="font-semibold text-gray-800 "><?= $cancelled ?> (<?= $totalBookings > 0 ? round($cancelled / $totalBookings * 100) : 0 ?>%)</span></div> -->
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        const thisWeek = <?= json_encode($thisWeekData) ?>;
        const lastWeek = <?= json_encode($lastWeekData) ?>;
        const lastMonthLabels = <?= json_encode($lastMonthLabels) ?>;
        const lastMonthValues = <?= json_encode($lastMonthValues) ?>;

        // Donut Chart (Booking Status)
        const ctxDonut = document.getElementById('eventStatusChart').getContext('2d');
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [<?= $pending ?>, <?= $confirmed ?>, <?= $cancelled ?>],
                    backgroundColor: ['#fbbf24', '#34d399', '#f87171'],
                    borderWidth: 0,
                    cutout: '75%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        enabled: true
                    }
                }
            }
        });

        let bookingChart;

        function loadBookingChart(type = "week") {
            if (bookingChart) {
                bookingChart.destroy();
            }

            let labels, datasets;

            if (type === "month") {
                labels = lastMonthLabels.map(d => 'Day ' + d);
                datasets = [{
                    label: 'Last Month',
                    data: lastMonthValues,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.08)',
                    tension: 0.4,
                    borderWidth: 0.5,
                    pointBackgroundColor: '#8b5cf6',
                    fill: true
                }];
            } else {
                labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                datasets = [{
                    label: 'This Week',
                    data: thisWeek,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.08)',
                    tension: 0.4,
                    borderWidth: 0.5,
                    pointBackgroundColor: '#8b5cf6',
                    fill: true
                }, {
                    label: 'Last Week',
                    data: lastWeek,
                    borderColor: '#cbd5e1',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    borderWidth: 0.5,
                    pointBackgroundColor: '#cbd5e1',
                    borderDash: [5, 5]
                }];
            }

            bookingChart = new Chart(
                document.getElementById("bookingOverviewChart"),
                {
                    type: "line",
                    data: {
                        labels: labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: type !== "month",
                                position: 'top',
                                labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } }
                            }
                        },
                        scales: {
                            y: {
                                min: 0,
                                beginAtZero: true,
                                grid: { color: '#f1f5f9' },
                                ticks: { stepSize: 1, precision: 0 }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                }
            );
        }

        // First load
        loadBookingChart();


        // Change dropdown
        document
            .getElementById("bookingFilter")
            .addEventListener("change", function () {
                loadBookingChart(this.value);
            });
    </script>
</body>

</html>