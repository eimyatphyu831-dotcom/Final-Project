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
$period = $_GET['period'] ?? 'weekly';
$selectedYear = (int)($_GET['year'] ?? date('Y'));
$selectedMonth = (int)($_GET['month'] ?? date('m'));
$selectedWeek = (int)($_GET['week'] ?? date('W'));

// --- Revenue Report: Daily ---
$dailyRevenueLabels = [];
$dailyRevenueValues = [];
if ($reportType === 'revenue' && $period === 'daily') {
    $daysInMonth = (int) date('t', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
    for ($i = 1; $i <= $daysInMonth; $i++) {
        $d = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $i);
        $dailyRevenueLabels[] = $i;
        $row = $conn->query("SELECT COALESCE(SUM(total_cost),0) rev FROM bookings WHERE status='Confirmed' AND DATE(created_at)='$d'")->fetch_assoc();
        $dailyRevenueValues[] = (float) $row['rev'];
    }
}

// --- Revenue Report: Monthly ---
$monthlyRevenueLabels = [];
$monthlyRevenueValues = [];
if ($reportType === 'revenue' && $period === 'monthly') {
    for ($m = 1; $m <= 12; $m++) {
        $start = sprintf('%04d-%02d-01', $selectedYear, $m);
        $end = date('Y-m-t', strtotime($start));
        $monthlyRevenueLabels[] = date('M', mktime(0, 0, 0, $m, 1));
        $row = $conn->query("SELECT COALESCE(SUM(total_cost),0) rev FROM bookings WHERE status='Confirmed' AND DATE(created_at) >= '$start' AND DATE(created_at) <= '$end'")->fetch_assoc();
        $monthlyRevenueValues[] = (float) $row['rev'];
    }
}

// --- Revenue Report: Yearly ---
$yearlyRevenueLabels = [];
$yearlyRevenueValues = [];
if ($reportType === 'revenue' && $period === 'yearly') {
    $minYearRow = $conn->query("SELECT YEAR(MIN(created_at)) y FROM bookings")->fetch_assoc();
    $minYear = $minYearRow && $minYearRow['y'] ? (int) $minYearRow['y'] : (int) date('Y') - 4;
    $maxYear = (int) date('Y');
    for ($y = $minYear; $y <= $maxYear; $y++) {
        $yearlyRevenueLabels[] = $y;
        $row = $conn->query("SELECT COALESCE(SUM(total_cost),0) rev FROM bookings WHERE status='Confirmed' AND YEAR(created_at)=$y")->fetch_assoc();
        $yearlyRevenueValues[] = (float) $row['rev'];
    }
}

// --- Revenue Report: Weekly ---
$weeklyRevenueLabels = [];
$weeklyRevenueValues = [];
if ($reportType === 'revenue' && $period === 'weekly') {
    $wStart = new DateTime();
    $wStart->setISODate($selectedYear, $selectedWeek);
    $wEnd = clone $wStart;
    $wEnd->modify('+6 days');
    for ($d = 0; $d < 7; $d++) {
        $date = (clone $wStart)->modify("+$d days");
        $dStr = $date->format('Y-m-d');
        $weeklyRevenueLabels[] = $date->format('D M j');
        $row = $conn->query("SELECT COALESCE(SUM(total_cost),0) rev FROM bookings WHERE status='Confirmed' AND DATE(created_at)='$dStr'")->fetch_assoc();
        $weeklyRevenueValues[] = (float) $row['rev'];
    }
}

// --- Revenue Summary ---
$yearlyTotalRev = $conn->query("SELECT COALESCE(SUM(total_cost),0) rev FROM bookings WHERE status='Confirmed' AND YEAR(created_at)=$selectedYear")->fetch_assoc()['rev'] ?? 0;
$monthlyTotalRev = $conn->query("SELECT COALESCE(SUM(total_cost),0) rev FROM bookings WHERE status='Confirmed' AND YEAR(created_at)=$selectedYear AND MONTH(created_at)=$selectedMonth")->fetch_assoc()['rev'] ?? 0;
$dailyTotalRev = $conn->query("SELECT COALESCE(SUM(total_cost),0) rev FROM bookings WHERE status='Confirmed' AND YEAR(created_at)=$selectedYear AND MONTH(created_at)=$selectedMonth AND DAY(created_at)=DAY(NOW())")->fetch_assoc()['rev'] ?? 0;

// --- Confirmed Bookings Report: Monthly ---
$monthlyBookingLabels = [];
$monthlyBookingValues = [];
if ($reportType === 'bookings' && $period === 'monthly') {
    for ($m = 1; $m <= 12; $m++) {
        $start = sprintf('%04d-%02d-01', $selectedYear, $m);
        $end = date('Y-m-t', strtotime($start));
        $monthlyBookingLabels[] = date('M', mktime(0, 0, 0, $m, 1));
        $row = $conn->query("SELECT COUNT(*) cnt FROM bookings WHERE status='Confirmed' AND DATE(created_at) >= '$start' AND DATE(created_at) <= '$end'")->fetch_assoc();
        $monthlyBookingValues[] = (int) $row['cnt'];
    }
}

// --- Confirmed Bookings Report: Yearly ---
$yearlyBookingLabels = [];
$yearlyBookingValues = [];
if ($reportType === 'bookings' && $period === 'yearly') {
    $minYearRow2 = $conn->query("SELECT YEAR(MIN(created_at)) y FROM bookings")->fetch_assoc();
    $minYear2 = $minYearRow2 && $minYearRow2['y'] ? (int) $minYearRow2['y'] : (int) date('Y') - 4;
    $maxYear2 = (int) date('Y');
    for ($y = $minYear2; $y <= $maxYear2; $y++) {
        $yearlyBookingLabels[] = $y;
        $row = $conn->query("SELECT COUNT(*) cnt FROM bookings WHERE status='Confirmed' AND YEAR(created_at)=$y")->fetch_assoc();
        $yearlyBookingValues[] = (int) $row['cnt'];
    }
}

// --- Confirmed Bookings Report: Weekly ---
$weeklyBookingLabels = [];
$weeklyBookingValues = [];
if ($reportType === 'bookings' && $period === 'weekly') {
    $wStart = new DateTime();
    $wStart->setISODate($selectedYear, $selectedWeek);
    $wEnd = clone $wStart;
    $wEnd->modify('+6 days');
    for ($d = 0; $d < 7; $d++) {
        $date = (clone $wStart)->modify("+$d days");
        $dStr = $date->format('Y-m-d');
        $weeklyBookingLabels[] = $date->format('D M j');
        $row = $conn->query("SELECT COUNT(*) cnt FROM bookings WHERE status='Confirmed' AND DATE(created_at)='$dStr'")->fetch_assoc();
        $weeklyBookingValues[] = (int) $row['cnt'];
    }
}

// --- Booking Summary ---
$yearlyTotalBk = $conn->query("SELECT COUNT(*) cnt FROM bookings WHERE status='Confirmed' AND YEAR(created_at)=$selectedYear")->fetch_assoc()['cnt'] ?? 0;
$monthlyTotalBk = $conn->query("SELECT COUNT(*) cnt FROM bookings WHERE status='Confirmed' AND YEAR(created_at)=$selectedYear AND MONTH(created_at)=$selectedMonth")->fetch_assoc()['cnt'] ?? 0;

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
        if ($avg2 > $avg1 * 1.15) $ep['trend'] = 'up';
        elseif ($avg2 < $avg1 * 0.85) $ep['trend'] = 'down';
        else $ep['trend'] = 'stable';
    }
    unset($ep);
}
$eventMonthLabels = array_column($recentMonths ?? [], 'label');

// Available years
$availableYears = [];
$yr = $conn->query("SELECT DISTINCT YEAR(created_at) y FROM bookings ORDER BY y DESC")->fetch_all(MYSQLI_ASSOC);
foreach ($yr as $r) $availableYears[] = (int) $r['y'];
if (empty($availableYears)) $availableYears[] = (int) date('Y');

// --- Excel Export ---
if (isset($_GET['export_excel']) && $_GET['export_excel'] === '1') {
    require_once 'XlsxWriter.php';

    $xlsx = new XlsxWriter();
    $periodLabel = ucfirst($period) . ' ' . $selectedYear;
    if ($period === 'weekly') $periodLabel = 'Week ' . $selectedWeek . ', ' . $selectedYear;
    elseif ($period === 'daily') $periodLabel = date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));

    $xlsx->addSheet('Report ' . $selectedYear);

    $xlsx->writeRow(['Report - ' . $periodLabel], ['section' => true]);
    $xlsx->writeRow([]);

    $xlsx->writeRow([' SUMMARY '], ['section' => true]);
    $xlsx->writeRow(['Yearly Revenue', number_format($yearlyTotalRev) . ' MMK'], ['bordered' => true]);
    $xlsx->writeRow(['Monthly Revenue (' . date('F', mktime(0, 0, 0, $selectedMonth, 1)) . ')', number_format($monthlyTotalRev) . ' MMK'], ['bordered' => true]);
    $xlsx->writeRow(['Daily Revenue', number_format($dailyTotalRev) . ' MMK'], ['bordered' => true]);
    $xlsx->writeRow(['Yearly Confirmed Bookings', $yearlyTotalBk], ['bordered' => true]);
    $xlsx->writeRow(['Monthly Confirmed Bookings (' . date('F', mktime(0, 0, 0, $selectedMonth, 1)) . ')', $monthlyTotalBk], ['bordered' => true]);
    $xlsx->writeRow([]);

    if ($period === 'weekly') {
        $xlsx->writeRow([' Revenue by Day (Week ' . $selectedWeek . ') '], ['section' => true]);
        $xlsx->writeRow(['Day', 'Revenue (MMK)'], ['header' => true]);
        foreach ($weeklyRevenueLabels as $i => $label) {
            $xlsx->writeRow([$label, $weeklyRevenueValues[$i]], ['bordered' => true]);
        }
        $xlsx->writeRow(['Total', array_sum($weeklyRevenueValues)], ['total' => true]);
        $xlsx->writeRow([]);
    } else {
        $xlsx->writeRow([' Revenue by Month '], ['section' => true]);
        $xlsx->writeRow(['Month', 'Revenue (MMK)'], ['header' => true]);
        foreach ($monthlyRevenueLabels as $i => $label) {
            $xlsx->writeRow([$label, $monthlyRevenueValues[$i]], ['bordered' => true]);
        }
        $xlsx->writeRow(['Total', array_sum($monthlyRevenueValues)], ['total' => true]);
        $xlsx->writeRow([]);
    }

    if ($period === 'weekly') {
        $xlsx->writeRow([' Confirmed Bookings by Day (Week ' . $selectedWeek . ') '], ['section' => true]);
        $xlsx->writeRow(['Day', 'Confirmed Bookings'], ['header' => true]);
        foreach ($weeklyBookingLabels as $i => $label) {
            $xlsx->writeRow([$label, $weeklyBookingValues[$i]], ['bordered' => true]);
        }
        $xlsx->writeRow(['Total', array_sum($weeklyBookingValues)], ['total' => true]);
        $xlsx->writeRow([]);
    } else {
        $xlsx->writeRow([' Confirmed Bookings by Month '], ['section' => true]);
        $xlsx->writeRow(['Month', 'Confirmed Bookings'], ['header' => true]);
        foreach ($monthlyBookingLabels as $i => $label) {
            $xlsx->writeRow([$label, $monthlyBookingValues[$i]], ['bordered' => true]);
        }
        $xlsx->writeRow(['Total', array_sum($monthlyBookingValues)], ['total' => true]);
        $xlsx->writeRow([]);
    }

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

    $xlsx->output('Monthly_Report_' . $selectedYear . '.xlsx');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - EventPro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Playfair Display', serif; }
        .bg-sidebar { background-color: #ffffff; }
        .bg-sidebar-active { background-color: #C3B1E1; color: #ffffff; }
        .text-purple-brand { color: #9966cc; }
        .bg-purple-brand { background-color: #C3B1E1; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #c4b5fd; border-radius: 9999px; }
        .custom-scroll { scrollbar-width: thin; scrollbar-color: #c4b5fd transparent; }
    </style>
</head>

<body class="bg-gray-50 min-h-screen overflow-hidden">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col ml-64">
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
                    <a href="?report=revenue&period=<?= $period ?>&year=<?= $selectedYear ?>&month=<?= $selectedMonth ?>"
                        class="px-4 py-2 rounded-lg text-sm font-semibold border transition <?= $reportType === 'revenue' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">
                        <i class="fa-solid fa-chart-line mr-1"></i> Revenue
                    </a>
                    <a href="?report=bookings&period=<?= $period === 'daily' ? 'monthly' : $period ?>&year=<?= $selectedYear ?>&month=<?= $selectedMonth ?>"
                        class="px-4 py-2 rounded-lg text-sm font-semibold border transition <?= $reportType === 'bookings' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">
                        <i class="fa-solid fa-clipboard-check mr-1"></i> Confirmed Bookings
                    </a>
                    <a href="?report=forecast"
                        class="px-4 py-2 rounded-lg text-sm font-semibold border transition <?= $reportType === 'forecast' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">
                        <i class="fa-solid fa-magnifying-glass-chart mr-1"></i> Event Forecast
                    </a>
                    <a href="?report=<?= $reportType ?>&period=<?= $period ?>&year=<?= $selectedYear ?>&month=<?= $selectedMonth ?>&week=<?= $selectedWeek ?>&export_excel=1"
                        class="px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition flex items-center gap-2">
                        <i class="fa-solid fa-file-excel"></i> Export Excel
                    </a>
                </div>

                <!-- ===================== REVENUE REPORT ===================== -->
                <?php if ($reportType === 'revenue'): ?>
                <!-- Period + Filters -->
                <div class="flex flex-wrap items-center gap-3">
                    <a href="?report=revenue&period=daily&year=<?= $selectedYear ?>&month=<?= $selectedMonth ?>"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition <?= $period === 'daily' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">Daily</a>
                    <a href="?report=revenue&period=weekly&year=<?= $selectedYear ?>&week=<?= $selectedWeek ?>"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition <?= $period === 'weekly' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">Weekly</a>
                    <a href="?report=revenue&period=monthly&year=<?= $selectedYear ?>&month=<?= $selectedMonth ?>"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition <?= $period === 'monthly' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">Monthly</a>
                    <a href="?report=revenue&period=yearly&year=<?= $selectedYear ?>&month=<?= $selectedMonth ?>"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition <?= $period === 'yearly' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">Yearly</a>

                    <form method="GET" class="flex items-center gap-2 ml-2">
                        <input type="hidden" name="report" value="revenue">
                        <input type="hidden" name="period" value="<?= $period ?>">
                        <select name="year" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:border-purple-400">
                            <?php foreach ($availableYears as $ay): ?>
                            <option value="<?= $ay ?>" <?= $ay === $selectedYear ? 'selected' : '' ?>><?= $ay ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($period === 'daily'): ?>
                        <select name="month" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:border-purple-400">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>><?= date('M', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                        <?php elseif ($period === 'weekly'): ?>
                        <input type="number" name="week" min="1" max="53" value="<?= $selectedWeek ?>"
                            class="w-16 border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:border-purple-400">
                        <?php endif; ?>
                        <button type="submit" class="px-3 py-1.5 bg-purple-600 text-white text-xs rounded-lg hover:bg-purple-700 transition font-medium">View</button>
                    </form>
                </div>

                <!-- Revenue Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                        <p class="text-xs text-gray-400 font-medium mb-1">Today's Revenue</p>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($dailyTotalRev) ?> <span class="text-sm font-normal text-gray-400">MMK</span></p>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                        <p class="text-xs text-gray-400 font-medium mb-1"><?= date('F', mktime(0, 0, 0, $selectedMonth, 1)) ?> <?= $selectedYear ?> Revenue</p>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($monthlyTotalRev) ?> <span class="text-sm font-normal text-gray-400">MMK</span></p>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                        <p class="text-xs text-gray-400 font-medium mb-1"><?= $selectedYear ?> Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($yearlyTotalRev) ?> <span class="text-sm font-normal text-gray-400">MMK</span></p>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <h3 class="text-base font-bold text-gray-800 mb-4">
                        Revenue <?= ucfirst($period) ?> — <?= $period === 'daily' ? date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)) : ($period === 'weekly' ? 'Week ' . $selectedWeek . ', ' . $selectedYear : $selectedYear) ?>
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
                                    <?php if ($period === 'daily'): ?>
                                    <th class="pb-3 font-medium">Day</th>
                                    <?php elseif ($period === 'weekly'): ?>
                                    <th class="pb-3 font-medium">Day</th>
                                    <?php elseif ($period === 'monthly'): ?>
                                    <th class="pb-3 font-medium">Month</th>
                                    <?php else: ?>
                                    <th class="pb-3 font-medium">Year</th>
                                    <?php endif; ?>
                                    <th class="pb-3 font-medium text-right">Revenue (MMK)</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-gray-700 divide-y divide-gray-50">
                                <?php
                                if ($period === 'daily') { $labels = $dailyRevenueLabels; $values = $dailyRevenueValues; }
                                elseif ($period === 'weekly') { $labels = $weeklyRevenueLabels; $values = $weeklyRevenueValues; }
                                elseif ($period === 'monthly') { $labels = $monthlyRevenueLabels; $values = $monthlyRevenueValues; }
                                else { $labels = $yearlyRevenueLabels; $values = $yearlyRevenueValues; }
                                $totalRev = array_sum($values);
                                foreach ($labels as $i => $label):
                                    $pct = $totalRev > 0 ? ($values[$i] / $totalRev * 100) : 0;
                                ?>
                                <tr class="hover:bg-gray-50/50">
                                    <td class="py-3 font-semibold text-gray-800"><?= $label ?></td>
                                    <td class="py-3 text-right">
                                        <span class="font-medium text-gray-800"><?= number_format($values[$i]) ?></span>
                                        <span class="text-gray-400 text-xs ml-2"><?= number_format($pct, 1) ?>%</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($labels)): ?>
                                <tr><td colspan="2" class="py-4 text-center text-gray-400 text-sm">No revenue data</td></tr>
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

                <!-- ===================== CONFIRMED BOOKINGS REPORT ===================== -->
                <?php elseif ($reportType === 'bookings'): ?>
                <!-- Period + Filters -->
                <div class="flex flex-wrap items-center gap-3">
                    <a href="?report=bookings&period=weekly&year=<?= $selectedYear ?>&week=<?= $selectedWeek ?>"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition <?= $period === 'weekly' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">Weekly</a>
                    <a href="?report=bookings&period=monthly&year=<?= $selectedYear ?>"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition <?= $period === 'monthly' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">Monthly</a>
                    <a href="?report=bookings&period=yearly&year=<?= $selectedYear ?>"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition <?= $period === 'yearly' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300' ?>">Yearly</a>

                    <form method="GET" class="flex items-center gap-2 ml-2">
                        <input type="hidden" name="report" value="bookings">
                        <input type="hidden" name="period" value="<?= $period ?>">
                        <select name="year" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:border-purple-400">
                            <?php foreach ($availableYears as $ay): ?>
                            <option value="<?= $ay ?>" <?= $ay === $selectedYear ? 'selected' : '' ?>><?= $ay ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($period === 'weekly'): ?>
                        <input type="number" name="week" min="1" max="53" value="<?= $selectedWeek ?>"
                            class="w-16 border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:border-purple-400">
                        <?php endif; ?>
                        <button type="submit" class="px-3 py-1.5 bg-purple-600 text-white text-xs rounded-lg hover:bg-purple-700 transition font-medium">View</button>
                    </form>
                </div>

                <!-- Booking Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                        <p class="text-xs text-gray-400 font-medium mb-1"><?= date('F', mktime(0, 0, 0, $selectedMonth, 1)) ?> <?= $selectedYear ?> Confirmed</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $monthlyTotalBk ?> <span class="text-sm font-normal text-gray-400">bookings</span></p>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                        <p class="text-xs text-gray-400 font-medium mb-1"><?= $selectedYear ?> Total Confirmed</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $yearlyTotalBk ?> <span class="text-sm font-normal text-gray-400">bookings</span></p>
                    </div>
                </div>

                <!-- Bookings Chart -->
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <h3 class="text-base font-bold text-gray-800 mb-4">
                        Confirmed Bookings <?= $period === 'weekly' ? 'by Week' : ($period === 'monthly' ? 'by Month' : 'by Year') ?> — <?= $selectedYear ?>
                    </h3>
                    <div class="h-80">
                        <canvas id="bookingChart"></canvas>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <h3 class="text-base font-bold text-gray-800 mb-4">Confirmed Bookings Breakdown</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-400 border-b border-gray-100">
                                    <th class="pb-3 font-medium"><?= $period === 'weekly' ? 'Day' : ($period === 'monthly' ? 'Month' : 'Year') ?></th>
                                    <th class="pb-3 font-medium text-right">Confirmed Bookings</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-gray-700 divide-y divide-gray-50">
                                <?php
                                if ($period === 'weekly') { $bLabels = $weeklyBookingLabels; $bValues = $weeklyBookingValues; }
                                elseif ($period === 'monthly') { $bLabels = $monthlyBookingLabels; $bValues = $monthlyBookingValues; }
                                else { $bLabels = $yearlyBookingLabels; $bValues = $yearlyBookingValues; }
                                $totalBk = array_sum($bValues);
                                foreach ($bLabels as $i => $label):
                                    $pct = $totalBk > 0 ? ($bValues[$i] / $totalBk * 100) : 0;
                                ?>
                                <tr class="hover:bg-gray-50/50">
                                    <td class="py-3 font-semibold text-gray-800"><?= $label ?></td>
                                    <td class="py-3 text-right">
                                        <span class="font-medium text-gray-800"><?= $bValues[$i] ?></span>
                                        <span class="text-gray-400 text-xs ml-2"><?= number_format($pct, 1) ?>%</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($bLabels)): ?>
                                <tr><td colspan="2" class="py-4 text-center text-gray-400 text-sm">No confirmed bookings</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-200 font-bold text-gray-800">
                                    <td class="py-3">Total</td>
                                    <td class="py-3 text-right"><?= $totalBk ?> bookings</td>
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
                        <p class="text-2xl font-bold text-gray-800"><?= !empty($eventPopularity) ? htmlspecialchars($eventPopularity[0]['event_name']) : 'N/A' ?></p>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                        <p class="text-xs text-gray-400 font-medium mb-1">Upcoming Bookings</p>
                        <?php
                        $totalUpcoming = 0;
                        foreach ($eventPopularity as $ep) $totalUpcoming += (int) $ep['upcoming_bookings'];
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
                                    <td class="py-3 font-semibold text-gray-800"><?= htmlspecialchars($ep['event_name']) ?></td>
                                    <td class="py-3 text-right text-gray-600"><?= $ep['total_bookings'] ?></td>
                                    <td class="py-3 text-right text-emerald-600 font-medium"><?= $ep['confirmed_bookings'] ?></td>
                                    <td class="py-3 text-right text-gray-800 font-medium"><?= number_format($ep['total_revenue']) ?></td>
                                    <td class="py-3 text-right">
                                        <?php if ($ep['upcoming_bookings'] > 0): ?>
                                        <span class="text-blue-600 font-medium"><?= $ep['upcoming_bookings'] ?></span>
                                        <?php else: ?>
                                        <span class="text-gray-400">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-right font-semibold text-purple-600">~<?= $ep['forecast_next_month'] ?></td>
                                    <td class="py-3 text-center">
                                        <?php if ($ep['trend'] === 'up'): ?>
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                                            <i class="fa-solid fa-arrow-trend-up"></i> Rising
                                        </span>
                                        <?php elseif ($ep['trend'] === 'down'): ?>
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 bg-red-50 px-2 py-1 rounded-full">
                                            <i class="fa-solid fa-arrow-trend-down"></i> Falling
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                            <i class="fa-solid fa-minus"></i> Stable
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($eventPopularity)): ?>
                                <tr><td colspan="7" class="py-4 text-center text-gray-400 text-sm">No event data available</td></tr>
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
                                    <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($ep['event_name']) ?></span>
                                    <span class="text-xs text-gray-500"><?= $ep['total_bookings'] ?> bookings</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-purple-500 to-indigo-500 h-2 rounded-full transition-all" style="width: <?= $width ?>%"></div>
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
                labels: <?= json_encode($period === 'daily' ? $dailyRevenueLabels : ($period === 'weekly' ? $weeklyRevenueLabels : ($period === 'monthly' ? $monthlyRevenueLabels : $yearlyRevenueLabels))) ?>,
                datasets: [{
                    label: 'Revenue (MMK)',
                    data: <?= json_encode($period === 'daily' ? $dailyRevenueValues : ($period === 'weekly' ? $weeklyRevenueValues : ($period === 'monthly' ? $monthlyRevenueValues : $yearlyRevenueValues))) ?>,
                    backgroundColor: 'rgba(139, 92, 246, 0.7)',
                    borderColor: '#8b5cf6',
                    borderWidth: 1,
                    borderRadius: 6,
                    maxBarThickness: <?= $period === 'daily' ? 12 : 40 ?>
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
    <?php elseif ($reportType === 'bookings'): ?>
        new Chart(document.getElementById('bookingChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($period === 'weekly' ? $weeklyBookingLabels : ($period === 'monthly' ? $monthlyBookingLabels : $yearlyBookingLabels)) ?>,
                datasets: [{
                    label: 'Confirmed Bookings',
                    data: <?= json_encode($period === 'weekly' ? $weeklyBookingValues : ($period === 'monthly' ? $monthlyBookingValues : $yearlyBookingValues)) ?>,
                    backgroundColor: 'rgba(52, 211, 153, 0.7)',
                    borderColor: '#34d399',
                    borderWidth: 1,
                    borderRadius: 6,
                    maxBarThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1, precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
    <?php else: ?>
        const forecastColors = ['#8b5cf6','#34d399','#f59e0b','#ef4444','#3b82f6','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16'];
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
