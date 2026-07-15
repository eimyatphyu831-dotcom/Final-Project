<?php
session_start();
require_once '../config/db.php';
$isLoggedIn = isset($_SESSION['user_id']);

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$currentEvent = null;

if ($eventId > 0) {
    $stmt = $conn->prepare("SELECT id, event_name FROM events WHERE id=?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $evRes = $stmt->get_result();
    $currentEvent = $evRes->fetch_assoc();
    $stmt->close();

    $result = $conn->query("SELECT v.*, e.event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id WHERE v.event_id = $eventId ORDER BY v.name ASC");
    $venues = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $result = $conn->query("SELECT v.*, e.event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id ORDER BY v.name ASC");
    $venues = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Fetch packages data for venue-package modal
$allPkgs = $conn->query("SELECT p.id, p.name, p.description FROM packages p ORDER BY FIELD(p.name, 'Silver', 'Gold', 'Diamond')");
$allPackages = $allPkgs ? $allPkgs->fetch_all(MYSQLI_ASSOC) : [];

$pkgServicesByEvent = [];
$svcRes = $conn->query("SELECT ps.event_id, ps.package_id, s.service_name FROM event_package_services ps JOIN services s ON s.id = ps.service_id ORDER BY ps.event_id, ps.package_id, s.id");
if ($svcRes) {
    while ($row = $svcRes->fetch_assoc()) {
        $pkgServicesByEvent[$row['event_id']][$row['package_id']][] = $row['service_name'];
    }
}

$vpRes = $conn->query("SELECT venue_id, package_id, price FROM venue_packages");
$vpPrices = [];
if ($vpRes) {
    while ($row = $vpRes->fetch_assoc()) {
        $vpPrices[$row['venue_id']][$row['package_id']] = $row['price'];
    }
}

$venuePkgData = [];
foreach ($venues as $v) {
    $vid = $v['id'];
    $vEventId = $v['event_id'];
    $data = [
        'venue_id' => $vid,
        'event_id' => $v['event_id'],
        'venue_name' => $v['name'],
        'prices' => ['---', '---', '---'],
        'raw_prices' => [0, 0, 0],
        'silver' => [],
        'gold' => [],
        'diamond' => [],
        'package_ids' => [0, 0, 0],
        'package_names' => ['Silver', 'Gold', 'Diamond']
    ];
    if (isset($vpPrices[$vid])) {
        $idx = 0;
        foreach ($allPackages as $pkg) {
            $pid = $pkg['id'];
            $key = strtolower($pkg['name']);
            if (isset($vpPrices[$vid][$pid])) {
                $data['prices'][$idx] = number_format($vpPrices[$vid][$pid]) . ' MMK';
                $data['raw_prices'][$idx] = (float) $vpPrices[$vid][$pid];
                $data[$key] = $pkgServicesByEvent[$vEventId][$pid] ?? [];
                $data['package_ids'][$idx] = $pid;
            }
            $idx++;
        }
    }
    $venuePkgData[$v['name']] = $data;
}
?>
<?php include '../includes/header.php'; ?>

<!-- Explore Venues -->
<section id="venues" class="w-full bg-purple-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto">
        <div class="mb-10 text-center">
            <h2 class="text-3xl font-extrabold text-brand-600 tracking-tight">
                Explore Venues
            </h2>

            <p class="text-slate-500 mt-2">
                Browse and filter venues by name
            </p>
        </div>


        <form class="mb-8 flex justify-center" onsubmit="event.preventDefault(); filterVenues();">

            <div
                class="w-full sm:max-w-md rounded-full border border-slate-200 bg-white shadow-sm focus-within:ring-2 focus-within:ring-purple-400 flex items-center px-4 py-2">

                <i data-lucide="search" class="w-4 h-4 text-slate-400 mr-2"></i>

                <input type="text" id="venueSearch" placeholder="Enter venue name" oninput="filterVenues()"
                    class="w-full bg-transparent text-sm text-slate-700 outline-none">

            </div>

        </form>

        <p id="noVenueMessage" class="hidden mb-6 text-sm text-slate-500">No venues found for your search.</p>

        <div id="venueGrid" class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($venues as $v): ?>
                <div onclick="showPackages('<?= htmlspecialchars($v['name']) ?>', this)"
                    class="venue-card cursor-pointer bg-white rounded-[1.5rem]  shadow-sm border border-slate-100 flex flex-col transition hover:shadow-lg hover:border-purple-200"
                    data-name="<?= htmlspecialchars($v['name']) ?>">
                    <div class="w-full h-[240px] relative rounded-t-[1.5rem] overflow-hidden">
                        <img src="<?= htmlspecialchars($v['image_path'] ?: '../assets/images/venue1.png') ?>"
                            alt="<?= htmlspecialchars($v['name']) ?>" class="w-full h-full object-cover">
                    </div>
                    <div class="px-4 pt-2 flex-1 flex flex-col justify-center">
                        <h3 class="text-2xl font-extrabold text-brand-900 mb-2"><?= htmlspecialchars($v['name']) ?></h3>
                        <?php if (!empty($v['event_name'])): ?>
                            <div class="flex py-2 w-full">
                                <span
                                    class="block w-max px-3 py-1 text-xs text-center font-semibold rounded-lg bg-purple-400 text-white">
                                    <?= htmlspecialchars($v['event_name']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <!-- <p class="text-slate-500 text-sm mb-4">Located at <?= htmlspecialchars($v['address']) ?> &mdash;
                            capacity up to <?= number_format($v['capacity']) ?> guests.</p> -->
                        <div class="flex items-center gap-4 text-xs text-slate-400 font-medium mb-6">
                            <div class="flex items-start gap-1 text-xs text-slate-400 mt-1 font-medium">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
                                <span><?= htmlspecialchars($v['address']) ?></span>
                            </div>
                            <span class="flex items-center gap-1"><i data-lucide="users" class="w-3.5 h-3.5"></i>
                                <?= number_format($v['capacity']) ?> </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- Available Packages -->
<section id="packagesSection" class="max-w-7xl mx-auto px-6 pb-16  hidden bg-purple-50 ">

    <h2 id="selectedVenueTitle" class="text-2xl font-bold mb-8 text-purple-400">
        Available Packages
    </h2>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Silver -->
        <div
            class="bg-white rounded-3xl shadow-md border border-gray-400 p-6 relative flex flex-col hover:shadow-xl hover:-translate-y-2 transition-all duration-300">

            <!-- <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-gray-300 to-gray-400"></div> -->

            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                    <i data-lucide="medal" class="w-5 h-5 text-gray-400"></i>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-gray-700">Silver</h3>
                    <p class="text-xs text-gray-400">Basic Package</p>
                </div>
            </div>

            <h4 id="price-silver" class="text-2xl font-bold text-gray-900 mb-4">---</h4>

            <div class="border-t pt-4 flex-grow">
                <p class="text-xs font-semibold text-gray-500 mb-3">Included Services</p>
                <ul id="list-silver" class="space-y-2 text-sm text-gray-600"></ul>
            </div>

            <button type="button" id="btn-silver" onclick="handleBooking('bookingform.php')"
                class="mt-6 w-full py-3 rounded-xl border border-gray-300 bg-gray-50 text-gray-700 font-semibold text-sm hover:bg-gray-700 hover:text-white transition">
                Select Package
            </button>

        </div>

        <!-- Gold -->
        <!-- <div class="bg-white rounded-3xl shadow-md border border-orange-400 p-6 relative flex-col hover:shadow-xl hover:-translate-y-2 transition-all duration-300"> -->
        <div
            class="bg-white rounded-3xl shadow-md border border-orange-400 p-6 relative flex flex-col hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
            <!-- <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-orange-300 to-orange-400"></div> -->

            <div class="absolute top-4 right-4 bg-orange-400 text-white text-[10px] font-bold px-3 py-1 rounded-full">
                POPULAR
            </div>

            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">
                    <i data-lucide="crown" class="w-5 h-5 text-orange-400"></i>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-orange-700">Gold</h3>
                    <p class="text-xs text-orange-400">Premium Package</p>
                </div>
            </div>

            <h4 id="price-gold" class="text-2xl font-bold text-gray-900 mb-4">---</h4>

            <div class="border-t pt-4 flex-grow">
                <p class="text-xs font-semibold text-orange-500 mb-3">Included Services</p>
                <ul id="list-gold" class="space-y-2 text-sm text-orange-500"></ul>
            </div>

            <button type="button" id="btn-gold" onclick="handleBooking('bookingform.php')"
                class="mt-6 w-full py-3 rounded-xl bg-orange-400 text-white font-semibold text-sm hover:bg-orange-500 hover:shadow-md transition">
                Select Package
            </button>
        </div>

        <!-- Diamond -->
        <div
            class="bg-white rounded-3xl shadow-md border border-blue-400 p-6 relative flex flex-col hover:shadow-xl hover:-translate-y-2 transition-all duration-300">

            <!-- <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-blue-300 to-blue-400"></div> -->

            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <i data-lucide="gem" class="w-5 h-5 text-blue-400"></i>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-blue-700">Diamond</h3>
                    <p class="text-xs text-blue-400">Luxury Package</p>
                </div>
            </div>

            <h4 id="price-diamond" class="text-2xl font-bold text-gray-900 mb-4">---</h4>

            <div class="border-t pt-4 flex-grow">
                <p class="text-xs font-semibold text-blue-500 mb-3">Included Services</p>
                <ul id="list-diamond" class="space-y-2 text-sm text-blue-400"></ul>
            </div>

            <button type="button" id="btn-diamond" onclick="handleBooking('bookingform.php')"
                class="mt-6 w-full py-3 rounded-xl border border-blue-300 bg-blue-50 text-blue-600 font-semibold text-sm hover:bg-blue-500 hover:text-white transition">
                Select Package
            </button>

        </div>

    </div>

</section>

<?php include '../includes/footer.php'; ?>

<script>
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    function handleBooking(url) {
        if (isLoggedIn) {
            window.location.href = url;
        } else {
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
        }
    }

    const venuePackageData = <?= json_encode($venuePkgData) ?>;

    function showPackages(venueName, el) {
        document.querySelectorAll('.venue-card').forEach(c => c.classList.remove('selected-venue'));
        if (el) el.classList.add('selected-venue');
        const section = document.getElementById('packagesSection');
        const data = venuePackageData[venueName];

        if (data) {
            document.getElementById('selectedVenueTitle').innerText = "Packages for " + venueName;
            document.getElementById('price-silver').innerHTML = `${data.prices[0]}<span class="text-sm text-gray-400 font-normal">/event</span>`;
            document.getElementById('price-gold').innerHTML = `${data.prices[1]}<span class="text-sm text-gray-400 font-normal">/event</span>`;
            document.getElementById('price-diamond').innerHTML = `${data.prices[2]}<span class="text-sm text-gray-400 font-normal">/event</span>`;

            const updateList = (id, items) => {
                document.getElementById(id).innerHTML = items.map(s => `<li>✓ ${s}</li>`).join('');
            };
            updateList('list-silver', data.silver);
            updateList('list-gold', data.gold);
            updateList('list-diamond', data.diamond);

            const tiers = ['silver', 'gold', 'diamond'];
            const ids = ['btn-silver', 'btn-gold', 'btn-diamond'];
            tiers.forEach((tier, i) => {
                const btn = document.getElementById(ids[i]);
                const pid = data.package_ids[i];
                const price = data.raw_prices[i];
                const params = `event_id=${data.event_id}&venue_id=${data.venue_id}&package_id=${pid}&total=${price}`;
                btn.onclick = () => handleBooking(`bookingform.php?${params}`);
            });

            section.classList.remove('hidden');
            section.scrollIntoView({ behavior: 'smooth' });
        }
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
</script>

<style>
    .venue-card.selected-venue {
        border-color: #9d84c7 !important;
        box-shadow: 0 0 0 3px rgba(157, 132, 199, 0.3), 0 10px 30px -5px rgba(157, 132, 199, 0.2);
    }
</style>