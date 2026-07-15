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

$reportType = $_GET['report'] ?? 'revenue';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// --- Revenue Report by Date Range ---
$revenueLabels = [];
$revenueValues = [];
$totalRev = 0;
if ($reportType === 'revenue') {
    $sd = $conn->real_escape_string($startDate);
    $ed = $conn->real_escape_string($endDate);
    $result = $conn->query("
        SELECT DATE(created_at) as day, COALESCE(SUM(total_cost),0) as rev
        FROM bookings
        WHERE status='Confirmed' AND DATE(created_at) >= '$sd' AND DATE(created_at) <= '$ed'
        GROUP BY DATE(created_at)
        ORDER BY day
    ");
    while ($row = $result->fetch_assoc()) {
        $revenueLabels[] = $row['day'];
        $revenueValues[] = (float) $row['rev'];
    }
    $totalRev = array_sum($revenueValues);
}

// --- Event Popularity Forecast ---
$eventPopularity = $conn->query("SELECT e.event_name, COUNT(b.id) AS total_bookings, 
    SUM(CASE WHEN b.status='Confirmed' THEN 1 ELSE 0 END) AS confirmed_bookings,
    COALESCE(SUM(CASE WHEN b.status='Confirmed' THEN b.total_cost ELSE 0 END),0) AS total_revenue,
    SUM(CASE WHEN b.event_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming_bookings,
    MIN(b.event_date) AS first_booking,
    MAX(b.event_date) AS last_booking
    FROM events e
    LEFT JOIN bookings b ON b.event_id = e.id
    GROUP BY e.id, e.event_name
    ORDER BY total_bookings DESC")->fetch_all(MYSQLI_ASSOC);

// Monthly trend per event for forecast
$eventMonthlyTrend = [];
if (!empty($eventPopularity)) {
    $recentMonths = [];
    for ($m = 5; $m >= 0; $m--) {
        $t = strtotime("-$m months");
        $recentMonths[] = ['label' => date('M Y', $t), 'start' => date('Y-m-01', $t), 'end' => date('Y-m-t', $t)];
    }

    foreach ($eventPopularity as &$ep) {
        $ep['monthly_counts'] = [];
        foreach ($recentMonths as $rm) {
            $row = $conn->query("SELECT COUNT(*) cnt FROM bookings WHERE event_id=(SELECT id FROM events WHERE event_name='" . $conn->real_escape_string($ep['event_name']) . "' LIMIT 1) AND DATE(created_at) >= '{$rm['start']}' AND DATE(created_at) <= '{$rm['end']}'")->fetch_assoc();
            $ep['monthly_counts'][] = (int) $row['cnt'];
        }
        // Simple forecast: average of last 3 months
        $last3 = array_slice($ep['monthly_counts'], -3);
        $ep['forecast_next_month'] = count($last3) > 0 ? round(array_sum($last3) / count($last3), 1) : 0;
        // Trend direction
        $firstHalf = array_slice($ep['monthly_counts'], 0, 3);
        $secondHalf = array_slice($ep['monthly_counts'], 3);
        $avg1 = count($firstHalf) > 0 ? array_sum($firstHalf) / count($firstHalf) : 0;
        $avg2 = count($secondHalf) > 0 ? array_sum($secondHalf) / count($secondHalf) : 0;
        if ($avg2 > $avg1 * 1.15)
            $ep['trend'] = 'up';
        elseif ($avg2 < $avg1 * 0.85)
            $ep['trend'] = 'down';
        else
            $ep['trend'] = 'stable';
    }
    unset($ep);
}
$eventMonthLabels = array_column($recentMonths ?? [], 'label');

// --- Approved & Paid Bookings ---
$sd = $conn->real_escape_string($startDate);
$ed = $conn->real_escape_string($endDate);
$approvedBookings = $conn->query("
    SELECT b.id, b.created_at, b.total_cost, u.name AS customer_name,
           e.event_name, p.name AS package_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN events e ON b.event_id = e.id
    JOIN packages p ON b.package_id = p.id
    WHERE b.status='Confirmed' AND DATE(b.created_at) >= '$sd' AND DATE(b.created_at) <= '$ed'
    ORDER BY b.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// --- Excel Export ---
if (isset($_GET['export_excel']) && $_GET['export_excel'] === '1') {
    require_once 'XlsxWriter.php';

    $xlsx = new XlsxWriter();

    $xlsx->addSheet('Report');

    $xlsx->writeRow(['Revenue Report - ' . $startDate . ' to ' . $endDate], ['section' => true]);
    $xlsx->writeRow([]);

    $xlsx->writeRow([' SUMMARY '], ['section' => true]);
    $xlsx->writeRow(['Total Revenue', number_format($totalRev) . ' MMK'], ['bordered' => true]);
    $xlsx->writeRow([]);

    $xlsx->writeRow([' Revenue by Day '], ['section' => true]);
    $xlsx->writeRow(['Date', 'Revenue (MMK)'], ['header' => true]);
    foreach ($revenueLabels as $i => $label) {
        $xlsx->writeRow([$label, $revenueValues[$i]], ['bordered' => true]);
    }
    $xlsx->writeRow(['Total', array_sum($revenueValues)], ['total' => true]);
    $xlsx->writeRow([]);

    // Event Popularity
    $xlsx->writeRow([' Event Popularity & Forecast '], ['section' => true]);
    $xlsx->writeRow(['Event', 'Total Bookings', 'Confirmed', 'Revenue (MMK)', 'Upcoming', 'Forecast', 'Trend'], ['header' => true]);
    foreach ($eventPopularity as $ep) {
        $xlsx->writeRow([
            $ep['event_name'],
            (int) $ep['total_bookings'],
            (int) $ep['confirmed_bookings'],
            (float) $ep['total_revenue'],
            (int) $ep['upcoming_bookings'],
            (float) $ep['forecast_next_month'],
            $ep['trend'],
        ], ['bordered' => true]);
    }

    // Approved & Paid Bookings
    $xlsx->addSheet('Approved Payments');
    $xlsx->writeRow([' Approved & Paid Bookings '], ['section' => true]);
    $xlsx->writeRow([]);
    $xlsx->writeRow(['Date', 'Customer Name', 'Event', 'Package', 'Amount (MMK)'], ['header' => true]);
    foreach ($approvedBookings as $ab) {
        $xlsx->writeRow([
            date('Y-m-d', strtotime($ab['created_at'])),
            $ab['customer_name'],
            $ab['event_name'],
            $ab['package_name'],
            (float) $ab['total_cost'],
        ], ['bordered' => true]);
    }
    $xlsx->writeRow([]);
    $xlsx->writeRow(['', '', '', 'Total', array_sum(array_column($approvedBookings, 'total_cost'))], ['total' => true]);

    $xlsx->output('Revenue_Report_' . $startDate . '_to_' . $endDate . '.xlsx');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - EventPro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap"
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
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background: #c4b5fd;
            border-radius: 9999px;
        }

        .custom-scroll {
            scrollbar-width: thin;
            scrollbar-color: #c4b5fd transparent;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .print-area,
            .print-area * {
                visibility: visible;
            }

            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                border: none !important;
                box-shadow: none !important;
                padding: 20px !important;
            }

            .print-area table {
                font-size: 12px;
            }

            .print-area table th {
                background: #f3f0fa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-area table th,
            .print-area table td {
                padding: 8px 6px !important;
            }

            .print-area tfoot td {
                border-top: 2px solid #000 !important;
            }

            .print-hide {
                display: none !important;
            }

            .print-title {
                display: block !important;
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 15px;
                text-align: center;
            }

            header,
            .bg-sidebar,
            .admin-header,
            .flex.items-center.gap-2,
            .flex.flex-wrap.items-center.gap-3,
            .grid.grid-cols-1.md\\:grid-cols-3,
            .bg-white.p-6.rounded-2xl.border.border-gray-100 {
                display: none !important;
            }

            .flex.items-center.justify-between.mb-4 .print-hide {
                display: none !important;
            }
        }

        .print-title {
            display: none;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen overflow-hidden">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col lg:ml-64">
            <?php include 'admin_header.php'; ?>

            <main class="flex-1 p-6 space-y-6 overflow-y-auto">

                <!-- Page Title -->
                <!-- <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Reports & Analytics</h2>
                        <p class="text-xs text-gray-400 mt-1">Revenue, bookings, and event popularity insights</p>
                    </div>
                    <a href="?report=<?= $reportType ?>&period=<?= $period ?>&year=<?= $selectedYear ?>&month=<?= $selectedMonth ?>&export_excel=1"
                        class="px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition flex items-center gap-2">
                        <i class="fa-solid fa-file-excel"></i> Export Excel
                    </a>
                </div> -->

                <!-- Report Type Tabs -->
                <div class="flex items-center gap-2">
                    <a href="?report=revenue&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>"
                        class="px-4 py-2 rounded-lg text-sm font-semibold border transition <?= $reportType === 'revenue' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">
                        <i class="fa-solid fa-chart-line mr-1"></i> Revenue
                    </a>
                    <a href="?report=forecast"
                        class="px-4 py-2 rounded-lg text-sm font-semibold border transition <?= $reportType === 'forecast' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">
                        <i class="fa-solid fa-magnifying-glass-chart mr-1"></i> Event Forecast
                    </a>
                </div>

                <!-- ===================== REVENUE REPORT ===================== -->
                <?php if ($reportType === 'revenue'): ?>
                    <!-- Period + Filters -->
                    <div class="flex flex-wrap items-center gap-3">
                        <form method="GET" class="flex items-center gap-2">
                            <input type="hidden" name="report" value="revenue">
                            <label class="text-xs font-medium text-gray-500">From</label>
                            <input type="date" name="start_date" value="<?= $startDate ?>"
                                class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:border-purple-400">
                            <label class="text-xs font-medium text-gray-500">To</label>
                            <input type="date" name="end_date" value="<?= $endDate ?>"
                                class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:border-purple-400">
                            <button type="submit"
                                class="px-3 py-1.5 bg-purple-600 text-white text-xs rounded-lg hover:bg-purple-700 transition font-medium">View</button>
                        </form>
                    </div>

                    <!-- Revenue Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                            <p class="text-xs text-gray-400 font-medium mb-1">Total Revenue (<?= $startDate ?> to <?= $endDate ?>)</p>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($totalRev) ?> <span
                                    class="text-sm font-normal text-gray-400">MMK</span></p>
                        </div>
                    </div>

                    <!-- Revenue Chart -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <h3 class="text-base font-bold text-gray-800 mb-4">
                            Revenue — <?= $startDate ?> to <?= $endDate ?>
                        </h3>
                        <div class="h-80">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Revenue Table -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <h3 class="text-base font-bold text-gray-800 mb-4">Revenue Breakdown</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-xs font-semibold text-gray-400 border-b border-gray-100">
                                        <th class="pb-3 font-medium">Date</th>
                                        <th class="pb-3 font-medium text-right">Revenue (MMK)</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm text-gray-700 divide-y divide-gray-50">
                                    <?php foreach ($revenueLabels as $i => $label):
                                        $pct = $totalRev > 0 ? ($revenueValues[$i] / $totalRev * 100) : 0;
                                        ?>
                                        <tr class="hover:bg-gray-50/50">
                                            <td class="py-3 font-semibold text-gray-800"><?= $label ?></td>
                                            <td class="py-3 text-right">
                                                <span class="font-medium text-gray-800"><?= number_format($revenueValues[$i]) ?></span>
                                                <span class="text-gray-400 text-xs ml-2"><?= number_format($pct, 1) ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($revenueLabels)): ?>
                                        <tr>
                                            <td colspan="2" class="py-4 text-center text-gray-400 text-sm">No revenue data for this period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-gray-200 font-bold text-gray-800">
                                        <td class="py-3">Total</td>
                                        <td class="py-3 text-right"><?= number_format($totalRev) ?> MMK</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Approved & Paid Bookings -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm print-area">
                        <div class="print-title">Approved & Paid Bookings Report</div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-gray-800">Approved & Paid Bookings</h3>
                            <button onclick="window.print()"
                                class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition flex items-center gap-1.5 print-hide">
                                <i class="fa-solid fa-print"></i> Print
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-xs font-semibold text-gray-400 border-b border-gray-100">
                                        <th class="pb-3 font-medium">Date</th>
                                        <th class="pb-3 font-medium">Customer Name</th>
                                        <th class="pb-3 font-medium">Event</th>
                                        <th class="pb-3 font-medium">Package</th>
                                        <th class="pb-3 font-medium text-right">Amount (MMK)</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm text-gray-700 divide-y divide-gray-50">
                                    <?php foreach ($approvedBookings as $ab): ?>
                                        <tr class="hover:bg-gray-50/50">
                                            <td class="py-3 text-gray-600"><?= date('Y-m-d', strtotime($ab['created_at'])) ?>
                                            </td>
                                            <td class="py-3 font-semibold text-gray-800">
                                                <?= htmlspecialchars($ab['customer_name']) ?></td>
                                            <td class="py-3 text-gray-600"><?= htmlspecialchars($ab['event_name']) ?></td>
                                            <td class="py-3"><span
                                                    class="px-2 py-0.5 bg-purple-50 text-purple-700 text-xs font-semibold rounded-full"><?= htmlspecialchars($ab['package_name']) ?></span>
                                            </td>
                                            <td class="py-3 text-right font-medium text-gray-800">
                                                <?= number_format($ab['total_cost']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($approvedBookings)): ?>
                                        <tr>
                                            <td colspan="5" class="py-4 text-center text-gray-400 text-sm">No approved bookings
                                                found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-gray-200 font-bold text-gray-800">
                                        <td colspan="4" class="py-3">Total</td>
                                        <td class="py-3 text-right">
                                            <?= number_format(array_sum(array_column($approvedBookings, 'total_cost'))) ?>
                                            MMK</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- ===================== EVENT POPULARITY FORECAST ===================== -->
                <?php else: ?>
                    <!-- Forecast Summary -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                            <p class="text-xs text-gray-400 font-medium mb-1">Total Events</p>
                            <p class="text-2xl font-bold text-gray-800"><?= count($eventPopularity) ?></p>
                        </div>
                        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                            <p class="text-xs text-gray-400 font-medium mb-1">Most Popular Event</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?= !empty($eventPopularity) ? htmlspecialchars($eventPopularity[0]['event_name']) : 'N/A' ?>
                            </p>
                        </div>
                        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                            <p class="text-xs text-gray-400 font-medium mb-1">Upcoming Bookings</p>
                            <?php
                            $totalUpcoming = 0;
                            foreach ($eventPopularity as $ep)
                                $totalUpcoming += (int) $ep['upcoming_bookings'];
                            ?>
                            <p class="text-2xl font-bold text-gray-800"><?= $totalUpcoming ?></p>
                        </div>
                    </div>

                    <!-- Event Popularity Chart -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <h3 class="text-base font-bold text-gray-800 mb-4">Event Popularity — Last 6 Months Trend</h3>
                        <div class="h-80">
                            <canvas id="forecastChart"></canvas>
                        </div>
                    </div>

                    <!-- Forecast Table -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-gray-800">Event Forecast Details</h3>
                            <p class="text-xs text-gray-400">Based on 6-month trend analysis</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-xs font-semibold text-gray-400 border-b border-gray-100">
                                        <th class="pb-3 font-medium">Event</th>
                                        <th class="pb-3 font-medium text-right">Total Bookings</th>
                                        <th class="pb-3 font-medium text-right">Confirmed</th>
                                        <th class="pb-3 font-medium text-right">Revenue (MMK)</th>
                                        <th class="pb-3 font-medium text-right">Upcoming</th>
                                        <th class="pb-3 font-medium text-right">Forecast (Next Month)</th>
                                        <th class="pb-3 font-medium text-center">Trend</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm text-gray-700 divide-y divide-gray-50">
                                    <?php foreach ($eventPopularity as $ep): ?>
                                        <tr class="hover:bg-gray-50/50">
                                            <td class="py-3 font-semibold text-gray-800">
                                                <?= htmlspecialchars($ep['event_name']) ?></td>
                                            <td class="py-3 text-right text-gray-600"><?= $ep['total_bookings'] ?></td>
                                            <td class="py-3 text-right text-emerald-600 font-medium">
                                                <?= $ep['confirmed_bookings'] ?></td>
                                            <td class="py-3 text-right text-gray-800 font-medium">
                                                <?= number_format($ep['total_revenue']) ?></td>
                                            <td class="py-3 text-right">
                                                <?php if ($ep['upcoming_bookings'] > 0): ?>
                                                    <span class="text-blue-600 font-medium"><?= $ep['upcoming_bookings'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-gray-400">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 text-right font-semibold text-purple-600">
                                                ~<?= $ep['forecast_next_month'] ?></td>
                                            <td class="py-3 text-center">
                                                <?php if ($ep['trend'] === 'up'): ?>
                                                    <span
                                                        class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                                                        <i class="fa-solid fa-arrow-trend-up"></i> Rising
                                                    </span>
                                                <?php elseif ($ep['trend'] === 'down'): ?>
                                                    <span
                                                        class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 bg-red-50 px-2 py-1 rounded-full">
                                                        <i class="fa-solid fa-arrow-trend-down"></i> Falling
                                                    </span>
                                                <?php else: ?>
                                                    <span
                                                        class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                                        <i class="fa-solid fa-minus"></i> Stable
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($eventPopularity)): ?>
                                        <tr>
                                            <td colspan="7" class="py-4 text-center text-gray-400 text-sm">No event data
                                                available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Popularity Ranking -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <h3 class="text-base font-bold text-gray-800 mb-4">Popularity Ranking</h3>
                        <div class="space-y-3">
                            <?php
                            $maxBookings = !empty($eventPopularity) ? max(array_column($eventPopularity, 'total_bookings')) : 1;
                            foreach ($eventPopularity as $rank => $ep):
                                $width = $maxBookings > 0 ? ($ep['total_bookings'] / $maxBookings * 100) : 0;
                                ?>
                                <div class="flex items-center gap-4">
                                    <span class="text-sm font-bold text-gray-400 w-6 text-right">#<?= $rank + 1 ?></span>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-1">
                                            <span
                                                class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($ep['event_name']) ?></span>
                                            <span class="text-xs text-gray-500"><?= $ep['total_bookings'] ?> bookings</span>
                                        </div>
                                        <div class="w-full bg-gray-100 rounded-full h-2">
                                            <div class="bg-gradient-to-r from-purple-500 to-indigo-500 h-2 rounded-full transition-all"
                                                style="width: <?= $width ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($eventPopularity)): ?>
                                <p class="text-sm text-gray-400 text-center py-4">No events to display</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <script>
        <?php if ($reportType === 'revenue'): ?>
            new Chart(document.getElementById('revenueChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($revenueLabels) ?>,
                    datasets: [{
                        label: 'Revenue (MMK)',
                        data: <?= json_encode($revenueValues) ?>,
                        backgroundColor: 'rgba(139, 92, 246, 0.7)',
                        borderColor: '#8b5cf6',
                        borderWidth: 1,
                        borderRadius: 6,
                        maxBarThickness: 40
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.parsed.y.toLocaleString() + ' MMK'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: {
                                callback: v => v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        <?php else: ?>
            const forecastColors = ['#8b5cf6', '#34d399', '#f59e0b', '#ef4444', '#3b82f6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'];
            const forecastDatasets = <?= json_encode($eventPopularity) ?>.map((ev, i) => ({
                label: ev.event_name,
                data: ev.monthly_counts,
                borderColor: forecastColors[i % forecastColors.length],
                backgroundColor: 'transparent',
                tension: 0.4,
                borderWidth: 2,
                pointBackgroundColor: forecastColors[i % forecastColors.length],
                pointRadius: 3
            }));

            new Chart(document.getElementById('forecastChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($eventMonthLabels) ?>,
                    datasets: forecastDatasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1, precision: 0 } },
                        x: { grid: { display: false } }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>