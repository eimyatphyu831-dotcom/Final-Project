<?php
session_start();
require_once '../config/db.php';
$isLoggedIn = isset($_SESSION['user_id']);

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$selectedVenueId = isset($_GET['venue_id']) ? (int) $_GET['venue_id'] : 0;

if ($eventId > 0) {
    $result = $conn->query("SELECT v.*, e.event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id WHERE v.event_id = $eventId ORDER BY e.event_name ASC, v.name ASC");
    $venues = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $result = $conn->query("SELECT v.*, e.event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id ORDER BY e.event_name ASC, v.name ASC");
    $venues = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Selected venue packages
$selectedVenue = null;
$packages = [];
$venueId = $selectedVenueId;

if ($venueId > 0 && $eventId > 0) {
    $stmt = $conn->prepare("SELECT v.*, e.event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id WHERE v.id=?");
    $stmt->bind_param("i", $venueId);
    $stmt->execute();
    $selectedVenue = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selectedVenue) {
        $allPkgs = $conn->query("SELECT p.id, p.name, p.description FROM packages p ORDER BY FIELD(p.name, 'Silver', 'Gold', 'Diamond')");
        $allPackages = $allPkgs ? $allPkgs->fetch_all(MYSQLI_ASSOC) : [];

        $pkgServices = [];
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

?>
<?php include '../includes/header.php'; ?>

<!-- Explore Venues -->
<section id="venues" class="w-full bg-purple-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6 text-center">

            <h2 class="text-3xl font-extrabold text-brand-600 tracking-tight">
                Explore Venues
            </h2>
            <p class="text-slate-500 mt-2">
                Browse and filter venues by name
            </p>
        </div>

        <div class="relative mb-8">

            <!-- Search Bar (Center) -->
            <form class="flex justify-center" onsubmit="event.preventDefault(); filterVenues();">
                <div
                    class="w-full sm:max-w-md rounded-full border border-slate-200 bg-white shadow-sm focus-within:ring-2 focus-within:ring-purple-400 flex items-center px-4 py-2">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 mr-2"></i>
                    <input type="text" id="venueSearch" placeholder="Enter venue name" oninput="filterVenues()"
                        class="w-full bg-transparent text-sm text-slate-700 outline-none">
                </div>
            </form>

            <!-- Back Button (Right) -->
            <a href="javascript:history.back()"
                class="absolute right-0 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 text-sm text-brand-600 hover:text-brand-700 font-bold">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back
            </a>

        </div>
        <p id="noVenueMessage" class="hidden mb-6 text-sm text-slate-500">No venues found for your search.</p>

        <div id="venueGrid" class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($venues as $v): ?>
                <div class="venue-card bg-white rounded-[1.5rem] shadow-sm border <?= ($selectedVenueId == $v['id']) ? 'border-purple-500 ring-2 ring-purple-200' : 'border-slate-100' ?> flex flex-col transition hover:shadow-lg hover:border-purple-200"
                    data-name="<?= htmlspecialchars($v['name']) ?>">
                    <div class="w-full h-[200px] relative rounded-t-[1.5rem] overflow-hidden">
                        <img src="<?= htmlspecialchars($v['image_path'] ?: '../assets/images/venue1.png') ?>"
                            alt="<?= htmlspecialchars($v['name']) ?>" class="w-full h-full object-cover">
                    </div>
                    <div class="px-4 pt-2 flex-1 flex flex-col justify-center">
                        <h3 class="text-xl font-extrabold text-brand-900 mb-1"><?= htmlspecialchars($v['name']) ?></h3>
                        <?php if (!empty($v['event_name'])): ?>
                            <?php
                                $badges = [
                                    'corporate' => 'bg-green-600/60 text-white',
                                    'wedding' => 'bg-pink-500/60 text-white',
                                    'birthday' => 'bg-yellow-500/60 text-white',
                                    'music' => 'bg-purple-500/60 text-white',
                                    'educational' => 'bg-blue-500/60 text-white',
                                ];
                                $eventKey = strtolower(trim($v['event_name']));
                                $badgeClass = $badges[$eventKey] ?? 'bg-purple-400 text-white';
                            ?>
                            <div class="flex py-1 w-full">
                                <span
                                    class="block w-max px-3 py-1 text-[10px] text-center font-semibold rounded-lg <?= $badgeClass ?>">
                                    <?= htmlspecialchars($v['event_name']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center gap-4 text-xs text-slate-400 font-medium mb-3">
                            <div class="flex items-start gap-1 text-xs text-slate-400 mt-1 font-medium">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
                                <span><?= htmlspecialchars($v['address']) ?></span>
                            </div>
                            <span class="flex items-center gap-1"><i data-lucide="users" class="w-3.5 h-3.5"></i>
                                <?= number_format($v['capacity']) ?> </span>
                        </div>
                        <a href="?event_id=<?= $v['event_id'] ?>&venue_id=<?= $v['id'] ?>"
                            class="mb-4 w-full py-2 rounded-xl text-center text-xs font-semibold transition <?= ($selectedVenueId == $v['id']) ? 'bg-brand-700 text-white' : 'border border-brand-600 bg-brand-600 hover:bg-brand-700 text-white' ?>">
                            <?= ($selectedVenueId == $v['id']) ? 'Selected' : 'Select' ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($selectedVenue && !empty($packages)): ?>
    <!-- Packages Section -->
    <section id="packagesSection" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 bg-purple-50">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-extrabold text-brand-600">Choose Your Package</h2>
            <p class="text-slate-500 mt-1">Select the perfect package for <span
                    class="font-semibold"><?= htmlspecialchars($selectedVenue['name']) ?></span></p>
        </div>
        <div class="max-w-4xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
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
    </section>
<?php endif; ?>

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

<?php include '../includes/footer.php'; ?>

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

    function filterVenues() {
        const query = document.getElementById('venueSearch').value.trim().toLowerCase();
        const cards = document.querySelectorAll('.venue-card');
        const noVenueMessage = document.getElementById('noVenueMessage');
        let visibleCount = 0;

        cards.forEach(card => {
            const name = card.getAttribute('data-name').toLowerCase();
            const matches = name.includes(query);
            card.classList.toggle('hidden', !matches);
            if (matches) visibleCount++;
        });

        if (noVenueMessage) {
            noVenueMessage.classList.toggle('hidden', visibleCount !== 0);
        }
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