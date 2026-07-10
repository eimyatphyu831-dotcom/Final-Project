<?php
session_start();
require_once "../config/db.php";


//   Check Event ID or Type

if (isset($_GET['type'])) {
    $type = strtolower(trim($_GET['type']));
    $stmt = $conn->prepare("SELECT id FROM events WHERE LOWER(event_name) = ? LIMIT 1");
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        die("Event not found.");
    }
    $row = $res->fetch_assoc();
    $stmt->close();
    header("Location: viewdetails.php?id=" . $row['id']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Event not found.");
}

$id = (int) $_GET['id'];


//   Get Event Details
$stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Event not found.");
}

$event = $result->fetch_assoc();
$stmt->close();

$isLoggedIn = isset($_SESSION['user_id']);


//   Get Event Gallery
$stmt = $conn->prepare("
    SELECT image_path
    FROM event_gallery
    WHERE event_id=?
    ORDER BY id ASC
");
$stmt->bind_param("i", $id);
$stmt->execute();

$gallery = $stmt->get_result();
$stmt->close();


//   Count Photos
$totalPhotos = $gallery->num_rows;


//   Get Venues for This Event
$venues = [];

// 1) Venues assigned to this event (venues.event_id)
$stmt = $conn->prepare("SELECT * FROM venues WHERE event_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $id);
$stmt->execute();
$vResult = $stmt->get_result();
if ($vResult && $vResult->num_rows > 0) {
    while ($row = $vResult->fetch_assoc()) {
        $venues[] = $row;
    }
}
$stmt->close();

// 2) Fallback: venue_id on events table
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

// Fetch packages data for venue-package modal
$allPkgs = $conn->query("SELECT p.id, p.name, p.description FROM packages p ORDER BY FIELD(p.name, 'Silver', 'Gold', 'Diamond')");
$allPackages = $allPkgs ? $allPkgs->fetch_all(MYSQLI_ASSOC) : [];

$svcRes = $conn->prepare("SELECT ps.package_id, s.service_name FROM event_package_services ps JOIN services s ON s.id = ps.service_id WHERE ps.event_id = ? ORDER BY ps.package_id, s.id");
$svcRes->bind_param("i", $id);
$svcRes->execute();
$svcRes = $svcRes->get_result();
$pkgServices = [];
if ($svcRes) {
    while ($row = $svcRes->fetch_assoc()) {
        $pkgServices[$row['package_id']][] = $row['service_name'];
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
    $data = [
        'venue_id' => $vid,
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
                $data[$key] = $pkgServices[$pid] ?? [];
                $data['package_ids'][$idx] = $pid;
            }
            $idx++;
        }
    }
    $venuePkgData[$v['name']] = $data;
}
?>

<?php include '../includes/header.php'; ?>

<section class="max-w-7xl mx-auto px-6 py-12">

    <div class="grid lg:grid-cols-2 gap-16 items-center">

        <!-- Image -->
        <div class="relative group">

            <!-- Decorative background -->
            <div
                class="absolute -inset-4 bg-gradient-to-r from-purple-50 via-pink-100 to-purple-100 rounded-[40px] blur-xl opacity-60 group-hover:opacity-90 transition duration-700">
            </div>

            <!-- Main image -->
            <div class="relative overflow-hidden rounded-[32px] shadow-2xl">

                <img src="<?php echo htmlspecialchars($event['image']); ?>"
                    alt="<?php echo htmlspecialchars($event['event_name']); ?>"
                    class="w-full h-[450px] object-cover transition duration-700 group-hover:scale-110">

                <!-- Dark gradient -->
                <!-- <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div> -->

                <!-- Floating Badge -->
                <div class="absolute top-6 left-6"></div>

            </div>

        </div>

        <!-- Content -->
        <div>
            <h1 class="text-purple-400 #9d84c7 text-4xl font-bold">
                <?php echo htmlspecialchars($event['event_name']); ?>&nbsp;Event
            </h1>
            <p class="mt-8 text-lg text-gray-600 leading-9">
                <?php echo htmlspecialchars($event['description']); ?>
            </p>

            <div class="mt-10 flex flex-wrap gap-4">


                <button type="button" onclick="handleBooking('bookingform.php?event_id=<?php echo $event['id']; ?>')"
                    class="px-8 py-4 rounded-xl bg-brand-600 text-white font-bold hover:bg-purple-700 transition shadow-xl cursor-pointer">

                    Book This Event

                </button>

                <a href="events.php"
                    class="group relative inline-flex items-center justify-center overflow-hidden px-8 py-4 rounded-xl border-2 border-purple-300 text-purple-600 font-bold transition">

                    <span
                        class="absolute inset-0 bg-brand-600 origin-left scale-x-0 transition-transform duration-300 ease-out group-hover:scale-x-100"></span>

                    <span class="relative z-10 group-hover:text-white transition">
                        Back to Events
                    </span>

                </a>

            </div>

            <!-- Features -->
            <div class="grid grid-cols-3 gap-5 mt-12">

                <div class="bg-white rounded-2xl shadow-lg p-5 text-center">
                    <h3 class="text-3xl font-bold text-purple-500">500+</h3>
                    <p class="text-gray-500 text-sm mt-2">Guests</p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-5 text-center">
                    <h3 class="text-3xl font-bold text-purple-500">★★★★★</h3>
                    <p class="text-gray-500 text-sm mt-2">Top Rated</p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-5 text-center">
                    <h3 class="text-3xl font-bold text-purple-500">24/7</h3>
                    <p class="text-gray-500 text-sm mt-2">Support</p>
                </div>

            </div>

        </div>

    </div>

</section>

<!-- Event Gallery -->
<section class="max-w-7xl mx-auto px-6 pb-12 overflow-hidden">

    <div class="flex justify-between items-center mb-8">
        <h2 class="text-3xl font-bold text-purple-400">
            Event Gallery
        </h2>

        <span class="text-gray-500">
            <?php echo $totalPhotos; ?> Photos
        </span>
    </div>

    <div class="relative overflow-hidden">

        <div class="flex gap-6 marquee">

            <?php
            // Convert result into array
            $images = [];
            while ($photo = $gallery->fetch_assoc()) {
                $images[] = $photo;
            }

            // Duplicate images for infinite scrolling
            foreach (array_merge($images, $images) as $photo):
                ?>

                <div class="min-w-[320px] h-72 rounded-3xl overflow-hidden shadow-xl flex-shrink-0">
                    <img src="<?php echo htmlspecialchars($photo['image_path']); ?>"
                        class="w-full h-full object-cover hover:scale-110 transition duration-500">
                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>

<section class="max-w-7xl mx-auto px-6 pb-12">
    <h2 class="text-3xl font-bold text-purple-400 mb-8">Available Venues</h2>
    <div id="venueGrid" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($venues as $v): ?>
            <div onclick="showPackages('<?= htmlspecialchars($v['name']) ?>')"
                class="venue-card cursor-pointer bg-white rounded-[2rem] p-4 shadow-sm border border-slate-100 flex flex-col transition hover:shadow-lg hover:border-purple-200">
                <div class="w-full h-[240px] relative rounded-2xl overflow-hidden">
                    <img src="<?= htmlspecialchars($v['image_path'] ?: '../assets/images/venue1.png') ?>"
                        alt="<?= htmlspecialchars($v['name']) ?>" class="w-full h-full object-cover">
                </div>
                <div class="pt-5 flex-1 flex flex-col">
                    <h3 class="text-2xl font-extrabold text-slate-800 mb-2"><?= htmlspecialchars($v['name']) ?></h3>
                    <!-- <p class="text-slate-500 text-sm mb-6">Located at <?= htmlspecialchars($v['address']) ?> &mdash;
                        capacity up to <?= number_format($v['capacity']) ?> guests.</p> -->
                    <div class="mt-auto flex items-center gap-4 text-xs text-slate-400 font-medium">
                        <span class="flex items-center gap-1"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                            <?= htmlspecialchars($v['address']) ?></span>
                        <span class="flex items-center gap-1"><i data-lucide="users" class="w-3.5 h-3.5"></i>
                            <?= number_format($v['capacity']) ?> </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section id="packagesSection" class="max-w-7xl mx-auto px-6 pb-16 mt-4 hidden">

    <h2 id="selectedVenueTitle" class="text-2xl font-bold mb-8 text-brand-600">
        Available Packages
    </h2>

    <div class="grid lg:grid-cols-3 gap-8">

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
<script>
    const venuePackageData = <?= json_encode($venuePkgData) ?>;

    function showPackages(venueName) {
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

            const eventId = <?= $id ?>;
            const tiers = ['silver', 'gold', 'diamond'];
            const ids = ['btn-silver', 'btn-gold', 'btn-diamond'];
            tiers.forEach((tier, i) => {
                const btn = document.getElementById(ids[i]);
                const pid = data.package_ids[i];
                const price = data.raw_prices[i];
                const params = `event_id=${eventId}&venue_id=${data.venue_id}&package_id=${pid}&total=${price}`;
                btn.onclick = () => handleBooking(`bookingform.php?${params}`);
            });

            section.classList.remove('hidden');
            section.scrollIntoView({ behavior: 'smooth' });
        }
    }
    lucide.createIcons();
</script>

<?php include '../includes/footer.php'; ?>

<script>
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    function handleBooking(url) {
        if (!isLoggedIn) {
            const params = new URLSearchParams(url.split('?')[1] || '');
            const hasPackage = params.get('venue_id') && params.get('package_id');
            const redirectUrl = hasPackage ? url : window.location.href;
            const bookingUrl = encodeURIComponent(redirectUrl);
            Swal.fire({
                title: 'Login Required',
                text: 'Please register or login to book this event.',
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
        const params = new URLSearchParams(url.split('?')[1] || '');
        if (!params.get('venue_id') || !params.get('package_id')) {
            Swal.fire({
                title: 'Select Venue & Package',
                text: 'Please select a venue and package before booking.',
                icon: 'info',
                confirmButtonColor: '#9d84c7',
                confirmButtonText: 'OK'
            });
            return;
        }
        window.location.href = url;
    }
</script>

<style>
    .marquee {
        width: max-content;
        animation: marquee 30s linear infinite;
    }

    .marquee:hover {
        animation-play-state: paused;
    }

    @keyframes marquee {
        from {
            transform: translateX(0);
        }

        to {
            transform: translateX(-50%);
        }
    }
</style>