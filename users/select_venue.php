<?php
session_start();
require_once "../config/db.php";

if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    header("Location: events.php");
    exit();
}

$eventId = (int) $_GET['event_id'];
$selectedVenueId = isset($_GET['venue_id']) ? (int) $_GET['venue_id'] : 0;

// Get event details
$stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    header("Location: events.php");
    exit();
}

// Get venues for this event
$venues = [];
$stmt = $conn->prepare("SELECT * FROM venues WHERE event_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$vResult = $stmt->get_result();
if ($vResult && $vResult->num_rows > 0) {
    while ($row = $vResult->fetch_assoc()) {
        $venues[] = $row;
    }
}
$stmt->close();

// Fallback: venue_id on events table
if (empty($venues) && !empty($event['venue_id'])) {
    $stmt = $conn->prepare("SELECT * FROM venues WHERE id=?");
    $stmt->bind_param("i", $event['venue_id']);
    $stmt->execute();
    $vResult = $stmt->get_result();
    if ($vResult && $vResult->num_rows > 0) {
        $venues[] = $vResult->fetch_assoc();
    }
    $stmt->close();
}

// Selected venue packages
$selectedVenue = null;
$packages = [];
$venueId = $selectedVenueId;

if ($venueId > 0) {
    $stmt = $conn->prepare("SELECT v.*, e.event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id WHERE v.id=?");
    $stmt->bind_param("i", $venueId);
    $stmt->execute();
    $selectedVenue = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selectedVenue) {
        $allPkgs = $conn->query("SELECT p.id, p.name, p.description FROM packages p ORDER BY FIELD(p.name, 'Silver', 'Gold', 'Diamond')");
        $allPackages = $allPkgs ? $allPkgs->fetch_all(MYSQLI_ASSOC) : [];

        $pkgServices = [];
        if ($eventId > 0) {
            $svcRes = $conn->prepare("SELECT ps.package_id, s.service_name FROM event_package_services ps JOIN services s ON s.id = ps.service_id WHERE ps.event_id = ? ORDER BY ps.package_id, s.id");
            $svcRes->bind_param("i", $eventId);
            $svcRes->execute();
            $svcResult = $svcRes->get_result();
            if ($svcResult) {
                while ($row = $svcResult->fetch_assoc()) {
                    $pkgServices[$row['package_id']][] = $row['service_name'];
                }
            }
            $svcRes->close();
        }

        $vpRes = $conn->prepare("SELECT package_id, price FROM venue_packages WHERE venue_id=?");
        $vpRes->bind_param("i", $venueId);
        $vpRes->execute();
        $vpResult = $vpRes->get_result();
        $vpPrices = [];
        if ($vpResult) {
            while ($row = $vpResult->fetch_assoc()) {
                $vpPrices[$row['package_id']] = $row['price'];
            }
        }
        $vpRes->close();

        foreach ($allPackages as $pkg) {
            $pid = $pkg['id'];
            $price = isset($vpPrices[$pid]) ? (float) $vpPrices[$pid] : 0;
            $packages[] = [
                'id' => $pid,
                'name' => $pkg['name'],
                'price' => $price,
                'price_formatted' => $price > 0 ? number_format($price) . ' MMK' : '---',
                'services' => $pkgServices[$pid] ?? []
            ];
        }
    }
}

$isLoggedIn = isset($_SESSION['user_id']);
$pageTitle = "Select Venue - " . htmlspecialchars($event['event_name']);
include "../includes/header.php";
?>

<div class="min-h-screen bg-purple-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="relative mb-8">

    <!-- Center Title -->
    <div class="text-center">
        <h1 class="text-3xl font-bold text-brand-600">
            Select a Venue
        </h1>

        <p class="text-sm text-gray-500 mt-1">
            Choose a venue for 
            <span class="font-semibold text-gray-700">
                <?= htmlspecialchars($event['event_name']) ?>
                    </span>
                </p>
            </div>
        
        
            <!-- Back Button Right -->
            <a href="viewdetails.php?id=<?= $eventId ?>"
                class="absolute right-0 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 text-sm text-brand-600 hover:text-brand-700 transition mt-6 font-bold">
        
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back
        
            </a>
        
        </div>

        <?php if (empty($venues)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-400 mb-2">No venues available</h3>
                <p class="text-sm text-gray-400">There are no venues for this event yet.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($venues as $v): ?>
                    <div
                        class="bg-white rounded-[1.5rem] shadow-sm border <?= ($selectedVenueId == $v['id']) ? 'border-purple-500 ring-2 ring-purple-200' : 'border-slate-100' ?> flex flex-col transition hover:shadow-lg hover:border-purple-200">
                        <div class="w-full h-[200px] relative rounded-t-[1.5rem] overflow-hidden">
                            <img src="<?= htmlspecialchars($v['image_path'] ?: '../assets/images/venue1.png') ?>"
                                alt="<?= htmlspecialchars($v['name']) ?>" class="w-full h-full object-cover">
                        </div>
                        <div class="p-4 flex-1 flex flex-col">
                            <h3 class="text-xl font-extrabold text-slate-800 mb-1">
                                <?= htmlspecialchars($v['name']) ?>
                            </h3>
                            <div class="flex items-center gap-3 text-xs text-slate-500 mb-3">
                                <div class="flex items-start gap-1">
                                    <i data-lucide="map-pin" class="w-4 h-4 mt-1 flex-shrink-0"></i>
                                    <span class="text-sm text-slate-600">
                                        <?= htmlspecialchars($v['address']) ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <i data-lucide="users" class="w-4 h-4"></i>
                                    <span><?= number_format($v['capacity']) ?></span>
                                </div>
                            </div>
                            <a href="?event_id=<?= $eventId ?>&venue_id=<?= $v['id'] ?>"
                                class="mt-auto w-full py-2 rounded-xl text-center text-xs font-semibold transition <?= ($selectedVenueId == $v['id']) ? 'bg-brand-700 text-white' : 'border border-brand-600 bg-brand-600  hover:bg-purple-700 text-white' ?>">
                                <?= ($selectedVenueId == $v['id']) ? 'Selected' : 'Select' ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($selectedVenue && !empty($packages)): ?>
            <!-- Packages Section -->
            <div id="packagesSection" class="py-16 bg-purple-50">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-extrabold text-brand-600">Choose Your Package</h2>
                    <p class="text-slate-500 mt-1">Select the perfect package for <span
                            class="font-semibold"><?= htmlspecialchars($selectedVenue['name']) ?></span></p>
                </div>
                <div class="max-w-5xl mx-auto bg-purple-50">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Silver -->
                        <div
                            class="bg-white rounded-2xl shadow-md border border-gray-400 p-4 relative flex flex-col hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                                    <i data-lucide="medal" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-700">Silver</h3>
                                    <p class="text-[10px] text-gray-400">Basic Package</p>
                                </div>
                            </div>
                            <h4 class="text-xl font-bold text-gray-900 mb-3">
                                <?= $packages[0]['price_formatted'] ?>
                                <?php if ($packages[0]['price'] > 0): ?>
                                    <span class="text-xs text-gray-400 font-normal">/event</span>
                                <?php endif; ?>
                            </h4>
                            <div class="border-t pt-3 flex-grow">
                                <p class="text-[10px] font-semibold text-gray-500 mb-2">Included Services</p>
                                <ul class="space-y-1 text-xs text-gray-600">
                                    <?php if (!empty($packages[0]['services'])): ?>
                                        <?php foreach ($packages[0]['services'] as $svc): ?>
                                            <li>✓ <?= htmlspecialchars($svc) ?></li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="text-gray-400 italic">No services listed</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php if ($packages[0]['price'] > 0): ?>
                                <button type="button"
                                    onclick="handleBooking('bookingform.php?event_id=<?= $eventId ?>&venue_id=<?= $venueId ?>&package_id=<?= $packages[0]['id'] ?>&total=<?= $packages[0]['price'] ?>')"
                                    class="mt-4 w-full py-2 rounded-xl border border-gray-300 bg-gray-50 text-gray-700 font-semibold text-xs hover:bg-gray-700 hover:text-white transition">
                                    Select Package
                                </button>
                            <?php else: ?>
                                <p
                                    class="mt-4 w-full py-2 rounded-xl border border-gray-200 bg-gray-100 text-gray-400 font-semibold text-xs text-center">
                                    Not Available</p>
                            <?php endif; ?>
                        </div>

                        <!-- Gold -->
                        <div
                            class="bg-white rounded-2xl shadow-md border border-orange-400 p-4 relative flex flex-col hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
                            <div
                                class="absolute top-3 right-3 bg-orange-400 text-white text-[9px] font-bold px-2 py-0.5 rounded-full">
                                POPULAR</div>
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center">
                                    <i data-lucide="crown" class="w-4 h-4 text-orange-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-orange-700">Gold</h3>
                                    <p class="text-[10px] text-orange-400">Premium Package</p>
                                </div>
                            </div>
                            <h4 class="text-xl font-bold text-gray-900 mb-3">
                                <?= $packages[1]['price_formatted'] ?>
                                <?php if ($packages[1]['price'] > 0): ?>
                                    <span class="text-xs text-gray-400 font-normal">/event</span>
                                <?php endif; ?>
                            </h4>
                            <div class="border-t pt-3 flex-grow">
                                <p class="text-[10px] font-semibold text-orange-500 mb-2">Included Services</p>
                                <ul class="space-y-1 text-xs text-orange-500">
                                    <?php if (!empty($packages[1]['services'])): ?>
                                        <?php foreach ($packages[1]['services'] as $svc): ?>
                                            <li>✓ <?= htmlspecialchars($svc) ?></li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="text-orange-300 italic">No services listed</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php if ($packages[1]['price'] > 0): ?>
                                <button type="button"
                                    onclick="handleBooking('bookingform.php?event_id=<?= $eventId ?>&venue_id=<?= $venueId ?>&package_id=<?= $packages[1]['id'] ?>&total=<?= $packages[1]['price'] ?>')"
                                    class="mt-4 w-full py-2 rounded-xl bg-orange-400 text-white font-semibold text-xs hover:bg-orange-500 hover:shadow-md transition">
                                    Select Package
                                </button>
                            <?php else: ?>
                                <p
                                    class="mt-4 w-full py-2 rounded-xl border border-orange-200 bg-orange-50 text-orange-300 font-semibold text-xs text-center">
                                    Not Available</p>
                            <?php endif; ?>
                        </div>

                        <!-- Diamond -->
                        <div
                            class="bg-white rounded-2xl shadow-md border border-blue-400 p-4 relative flex flex-col hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <i data-lucide="gem" class="w-4 h-4 text-blue-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-blue-700">Diamond</h3>
                                    <p class="text-[10px] text-blue-400">Luxury Package</p>
                                </div>
                            </div>
                            <h4 class="text-xl font-bold text-gray-900 mb-3">
                                <?= $packages[2]['price_formatted'] ?>
                                <?php if ($packages[2]['price'] > 0): ?>
                                    <span class="text-xs text-gray-400 font-normal">/event</span>
                                <?php endif; ?>
                            </h4>
                            <div class="border-t pt-3 flex-grow">
                                <p class="text-[10px] font-semibold text-blue-500 mb-2">Included Services</p>
                                <ul class="space-y-1 text-xs text-blue-400">
                                    <?php if (!empty($packages[2]['services'])): ?>
                                        <?php foreach ($packages[2]['services'] as $svc): ?>
                                            <li>✓ <?= htmlspecialchars($svc) ?></li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="text-blue-300 italic">No services listed</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php if ($packages[2]['price'] > 0): ?>
                                <button type="button"
                                    onclick="handleBooking('bookingform.php?event_id=<?= $eventId ?>&venue_id=<?= $venueId ?>&package_id=<?= $packages[2]['id'] ?>&total=<?= $packages[2]['price'] ?>')"
                                    class="mt-4 w-full py-2 rounded-xl border border-blue-300 bg-blue-50 text-blue-600 font-semibold text-xs hover:bg-blue-500 hover:text-white transition">
                                    Select Package
                                </button>
                            <?php else: ?>
                                <p
                                    class="mt-4 w-full py-2 rounded-xl border border-blue-200 bg-blue-50 text-blue-300 font-semibold text-xs text-center">
                                    Not Available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Custom Alert Modal -->
<div id="alertModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white w-full max-w-md mx-4 rounded-3xl shadow-2xl p-8 text-center">
        <div id="modalIcon" class="w-16 h-16 mx-auto rounded-full bg-purple-100 flex items-center justify-center">
            <i data-lucide="info" class="w-8 h-8 text-purple-600"></i>
        </div>
        <h2 id="modalTitle" class="text-2xl font-bold text-slate-800 mt-5"></h2>
        <p id="modalText" class="text-slate-500 mt-3"></p>
        <div class="flex justify-center gap-4 mt-8">
            <button id="modalCancel" onclick="closeModal()"
                class="px-6 py-2 rounded-xl border border-gray-300 text-gray-600 hover:bg-gray-100">
                Cancel
            </button>
            <button id="modalConfirm" class="px-6 py-2 rounded-xl bg-purple-600 text-white hover:bg-purple-700">
                Login Now
            </button>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>

<script>
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    let confirmAction = null;

    function showModal(title, message, confirmText, callback, showCancel = true) {
        document.getElementById('modalTitle').innerText = title;
        document.getElementById('modalText').innerText = message;
        document.getElementById('modalConfirm').innerText = confirmText;
        document.getElementById('alertModal').classList.remove('hidden');
        document.getElementById('alertModal').classList.add('flex');
        document.getElementById('modalCancel').style.display = showCancel ? 'block' : 'none';
        if (window.lucide) {
            window.lucide.createIcons();
        }
        confirmAction = callback;
    }

    document.getElementById('modalConfirm').addEventListener('click', () => {
        if (confirmAction) {
            confirmAction();
        }
        closeModal();
    });

    function closeModal() {
        document.getElementById('alertModal').classList.remove('flex');
        document.getElementById('alertModal').classList.add('hidden');
    }

    function handleBooking(url) {
        if (!isLoggedIn) {
            const bookingUrl = encodeURIComponent(url);
            showModal(
                'Login Required',
                'Please register or login to book this package.',
                'Login Now',
                () => { window.location.href = '../auth/login.php?redirect=' + bookingUrl; }
            );
            return;
        }
        window.location.href = url;
    }

    <?php if ($selectedVenueId > 0): ?>
        document.addEventListener('DOMContentLoaded', function () {
            const section = document.getElementById('packagesSection');
            if (section) {
                setTimeout(() => {
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 300);
            }
        });
    <?php endif; ?>
</script>