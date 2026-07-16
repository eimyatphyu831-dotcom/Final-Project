<?php
session_start();
require_once '../config/db.php';

if (!isset($_GET['venue_id']) || !is_numeric($_GET['venue_id'])) {
    die("Venue not found.");
}

$venueId = (int) $_GET['venue_id'];
$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

$isLoggedIn = isset($_SESSION['user_id']);

$stmt = $conn->prepare("SELECT v.*, e.event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id WHERE v.id=?");
$stmt->bind_param("i", $venueId);
$stmt->execute();
$venue = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$venue) {
    die("Venue not found.");
}

if ($eventId === 0) {
    $eventId = (int) $venue['event_id'];
}

// Packages
$allPkgs = $conn->query("SELECT p.id, p.name, p.description FROM packages p ORDER BY FIELD(p.name, 'Silver', 'Gold', 'Diamond')");
$allPackages = $allPkgs ? $allPkgs->fetch_all(MYSQLI_ASSOC) : [];

// Services per event+package
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

// Prices for this venue
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

// Build package data
$packages = [];
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
?>
<?php include '../includes/header.php'; ?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Back link -->
    <a href="javascript:history.back()" class="inline-flex items-center gap-2 text-sm text-purple-500 hover:text-purple-700 mb-6 transition">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Back
    </a>

    <!-- Venue Hero -->
    <div class="relative w-full h-[400px] rounded-[2rem] overflow-hidden shadow-xl mb-10">
        <img src="<?= htmlspecialchars($venue['image_path'] ?: '../assets/images/venue1.png') ?>"
             alt="<?= htmlspecialchars($venue['name']) ?>"
             class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
        <div class="absolute bottom-0 left-0 right-0 p-8">
            <h1 class="text-4xl font-extrabold text-white mb-2"><?= htmlspecialchars($venue['name']) ?></h1>
            <div class="flex flex-wrap items-center gap-4 text-sm text-white/80">
                <?php if (!empty($venue['event_name'])): ?>
                    <span class="px-3 py-1 rounded-full bg-purple-500 text-white font-semibold text-xs">
                        <?= htmlspecialchars($venue['event_name']) ?>
                    </span>
                <?php endif; ?>
                <span class="flex items-center gap-1"><i data-lucide="map-pin" class="w-4 h-4"></i> <?= htmlspecialchars($venue['address']) ?></span>
                <span class="flex items-center gap-1"><i data-lucide="users" class="w-4 h-4"></i> <?= number_format($venue['capacity']) ?> guests</span>
            </div>
        </div>
    </div>

    <!-- Packages Heading -->
    <div class="text-center mb-10">
        <h2 class="text-3xl font-extrabold text-brand-600">Choose Your Package</h2>
        <p class="text-slate-500 mt-2">Select the perfect package for your event at <?= htmlspecialchars($venue['name']) ?></p>
    </div>

    <!-- 3 Packages -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Silver -->
        <div class="bg-white rounded-3xl shadow-md border border-gray-400 p-6 relative flex flex-col hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                    <i data-lucide="medal" class="w-5 h-5 text-gray-400"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-700">Silver</h3>
                    <p class="text-xs text-gray-400">Basic Package</p>
                </div>
            </div>
            <h4 class="text-2xl font-bold text-gray-900 mb-4">
                <?= $packages[0]['price_formatted'] ?>
                <?php if ($packages[0]['price'] > 0): ?>
                    <span class="text-sm text-gray-400 font-normal">/event</span>
                <?php endif; ?>
            </h4>
            <div class="border-t pt-4 flex-grow">
                <p class="text-xs font-semibold text-gray-500 mb-3">Included Services</p>
                <ul class="space-y-2 text-sm text-gray-600">
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
                <button type="button" onclick="handleBooking('bookingform.php?event_id=<?= $eventId ?>&venue_id=<?= $venueId ?>&package_id=<?= $packages[0]['id'] ?>&total=<?= $packages[0]['price'] ?>')"
                    class="mt-6 w-full py-3 rounded-xl border border-gray-300 bg-gray-50 text-gray-700 font-semibold text-sm hover:bg-gray-700 hover:text-white transition">
                    Select Package
                </button>
            <?php else: ?>
                <p class="mt-6 w-full py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-400 font-semibold text-sm text-center">Not Available</p>
            <?php endif; ?>
        </div>

        <!-- Gold -->
        <div class="bg-white rounded-3xl shadow-md border border-orange-400 p-6 relative flex flex-col hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
            <div class="absolute top-4 right-4 bg-orange-400 text-white text-[10px] font-bold px-3 py-1 rounded-full">POPULAR</div>
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">
                    <i data-lucide="crown" class="w-5 h-5 text-orange-400"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-orange-700">Gold</h3>
                    <p class="text-xs text-orange-400">Premium Package</p>
                </div>
            </div>
            <h4 class="text-2xl font-bold text-gray-900 mb-4">
                <?= $packages[1]['price_formatted'] ?>
                <?php if ($packages[1]['price'] > 0): ?>
                    <span class="text-sm text-gray-400 font-normal">/event</span>
                <?php endif; ?>
            </h4>
            <div class="border-t pt-4 flex-grow">
                <p class="text-xs font-semibold text-orange-500 mb-3">Included Services</p>
                <ul class="space-y-2 text-sm text-orange-500">
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
                <button type="button" onclick="handleBooking('bookingform.php?event_id=<?= $eventId ?>&venue_id=<?= $venueId ?>&package_id=<?= $packages[1]['id'] ?>&total=<?= $packages[1]['price'] ?>')"
                    class="mt-6 w-full py-3 rounded-xl bg-orange-400 text-white font-semibold text-sm hover:bg-orange-500 hover:shadow-md transition">
                    Select Package
                </button>
            <?php else: ?>
                <p class="mt-6 w-full py-3 rounded-xl border border-orange-200 bg-orange-50 text-orange-300 font-semibold text-sm text-center">Not Available</p>
            <?php endif; ?>
        </div>

        <!-- Diamond -->
        <div class="bg-white rounded-3xl shadow-md border border-blue-400 p-6 relative flex flex-col hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <i data-lucide="gem" class="w-5 h-5 text-blue-400"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-blue-700">Diamond</h3>
                    <p class="text-xs text-blue-400">Luxury Package</p>
                </div>
            </div>
            <h4 class="text-2xl font-bold text-gray-900 mb-4">
                <?= $packages[2]['price_formatted'] ?>
                <?php if ($packages[2]['price'] > 0): ?>
                    <span class="text-sm text-gray-400 font-normal">/event</span>
                <?php endif; ?>
            </h4>
            <div class="border-t pt-4 flex-grow">
                <p class="text-xs font-semibold text-blue-500 mb-3">Included Services</p>
                <ul class="space-y-2 text-sm text-blue-400">
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
                <button type="button" onclick="handleBooking('bookingform.php?event_id=<?= $eventId ?>&venue_id=<?= $venueId ?>&package_id=<?= $packages[2]['id'] ?>&total=<?= $packages[2]['price'] ?>')"
                    class="mt-6 w-full py-3 rounded-xl border border-blue-300 bg-blue-50 text-blue-600 font-semibold text-sm hover:bg-blue-500 hover:text-white transition">
                    Select Package
                </button>
            <?php else: ?>
                <p class="mt-6 w-full py-3 rounded-xl border border-blue-200 bg-blue-50 text-blue-300 font-semibold text-sm text-center">Not Available</p>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php include '../includes/footer.php'; ?>

<script>
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    function handleBooking(url) {
        if (!isLoggedIn) {
            const bookingUrl = encodeURIComponent(url);
            Swal.fire({
                title: 'Login Required',
                text: 'Please register or login to book this package.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#9d84c7',
                cancelButtonColor: '#d1d5db',
                confirmButtonText: 'Login Now',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../auth/login.php?redirect=' + bookingUrl;
                }
            });
            return;
        }
        window.location.href = url;
    }
</script>
