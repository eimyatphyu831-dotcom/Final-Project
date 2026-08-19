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

//   Get max capacity from venues for this event
$capResult = $conn->query("SELECT MAX(capacity) AS max_cap FROM venues");
$maxCapacity = $capResult ? (int) $capResult->fetch_assoc()['max_cap'] : 0;

//   Get average rating from reviews for this event
$ratResult = $conn->query("SELECT ROUND(AVG(rating), 1) AS avg_rating FROM reviews WHERE event_id = $id");
$avgRating = $ratResult ? (float) $ratResult->fetch_assoc()['avg_rating'] : 0;

//   Get services count for this event
$svcResult = $conn->query("SELECT COUNT(DISTINCT s.id) AS svc_count FROM services s JOIN event_package_services eps ON s.id = eps.service_id WHERE eps.event_id = $id");
$serviceCount = $svcResult ? (int) $svcResult->fetch_assoc()['svc_count'] : 0;

//   Get services for this event
$eventServices = [];
$svcRes = $conn->query("SELECT DISTINCT s.id AS service_id, s.service_name FROM services s JOIN event_package_services eps ON s.id = eps.service_id WHERE eps.event_id = $id ORDER BY s.service_name ASC");
if ($svcRes) {
    $eventServices = $svcRes->fetch_all(MYSQLI_ASSOC);
}

//   Get reviews for this event
$eventReviews = [];
$revRes = $conn->query("SELECT r.rating, r.review_text, r.created_at, u.name AS user_name, u.image AS user_image FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.event_id = $id ORDER BY r.created_at DESC");
if ($revRes) {
    $eventReviews = $revRes->fetch_all(MYSQLI_ASSOC);
}

//   Get packages for this event
$eventPackages = [];
$pkgRes = $conn->query("SELECT DISTINCT p.id, p.name, p.description, p.discount FROM packages p JOIN event_package_services eps ON p.id = eps.package_id WHERE eps.event_id = $id ORDER BY FIELD(p.name, 'Silver', 'Gold', 'Diamond'), p.name ASC");
if ($pkgRes) {
    $eventPackages = $pkgRes->fetch_all(MYSQLI_ASSOC);
}

$packageStyles = [
    'Silver' => ['icon' => 'medal', 'ring' => 'bg-gray-100 text-gray-500', 'badge' => 'bg-gray-100 text-gray-600', 'border' => 'border-gray-300', 'gradient' => 'bg-gradient-to-r from-gray-300 to-gray-400'],
    'Gold' => ['icon' => 'crown', 'ring' => 'bg-orange-100 text-orange-400', 'badge' => 'bg-orange-100 text-orange-600', 'border' => 'border-orange-300', 'gradient' => 'bg-gradient-to-r from-yellow-400 to-orange-500'],
    'Diamond' => ['icon' => 'gem', 'ring' => 'bg-blue-100 text-blue-400', 'badge' => 'bg-blue-100 text-blue-600', 'border' => 'border-blue-300', 'gradient' => 'bg-gradient-to-r from-sky-400 to-blue-500'],
];
$defaultPackageStyle = ['icon' => 'gift', 'ring' => 'bg-purple-100 text-purple-500', 'badge' => 'bg-purple-100 text-purple-600', 'border' => 'border-purple-300', 'gradient' => 'bg-gradient-to-r from-purple-400 to-fuchsia-500'];



?>



<?php include '../includes/header.php'; ?>

<section class="max-w-7xl mx-auto px-6 py-12">

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

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
                    <h3 class="text-3xl font-bold text-purple-500">
                        <?= $maxCapacity ? number_format($maxCapacity) . '+' : 'N/A' ?>
                    </h3>
                    <p class="text-gray-500 text-sm mt-2">Max Guests</p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-5 text-center">
                    <h3 class="text-3xl font-bold text-purple-500">
                        <?= $avgRating ? str_repeat('★', floor($avgRating)) . ($avgRating - floor($avgRating) >= 0.5 ? '½' : '') : '☆☆☆☆☆' ?>
                    </h3>
                    <p class="text-gray-500 text-sm mt-2">
                        <?= $avgRating ? number_format($avgRating, 1) . ' / 5' : 'No ratings' ?>
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-5 text-center">
                    <h3 class="text-3xl font-bold text-purple-500"><?= $serviceCount ? $serviceCount . '+' : 'N/A' ?></h3>
                    <p class="text-gray-500 text-sm mt-2">Services</p>
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

<!-- Event Services & Reviews -->
<?php if (!empty($eventServices) || !empty($eventReviews) || !empty($eventPackages)): ?>
    <section class="max-w-7xl mx-auto px-6 pb-12">

        <!-- <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-bold text-purple-400">
                Services &amp; Reviews
            </h2>

            <span class="text-gray-500">
                <?php echo count($eventServices); ?> Services &middot; <?php echo count($eventReviews); ?> Reviews
            </span>
        </div> -->

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">

            <!-- Services -->
            <div>
                <h3 class="text-3xl font-bold text-purple-400 mb-5">Services Included</h3>
                <?php
                $categoryKeywords = [
                    'Decoration' => ['floral', 'stage', 'decoration', 'lighting', 'seating', 'podium', 'backdrop', 'vip', 'lounge', 'meeting room'],
                    'Catering' => ['catering', 'drink', 'buffet', 'refreshment', 'snack', 'dessert'],
                    'Media' => ['photo', 'video', 'projector', 'screen', 'led', 'display', 'av', 'sound', 'mic', 'audio'],
                    'Entertainment' => ['host', 'mc', 'band', 'dj', 'music', 'live'],
                ];
                $groupedServices = [];
                $others = [];
                $categoryIcons = [
                    'Decoration' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M12 22a10 10 0 1 0-10-10c0 2.2 1.8 4 4 4h2.2c1.1 0 2 .9 2 2 0 .5.2 1 .6 1.3.3.3.8.6 1.2.7z"/><circle cx="7.5" cy="11.5" r="0.5"/><circle cx="12" cy="7.5" r="0.5"/><circle cx="16.5" cy="11.5" r="0.5"/></svg>',
                    'Catering' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M4 2v20M4 2h6c0 5-2 6-6 6M4 8c3 0 6 1 6 5s-3 5-6 5M16 21V8M16 2c3 0 5 3 5 6 0 2-2 3-2 3h-3"/></svg>',
                    'Media' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M23 7 16 12l7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
                    'Entertainment' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M20.2 6 3 11l-.9-2.4c-.3-1.1.3-2.2 1.3-2.5l13.5-4c1.1-.3 2.2.3 2.5 1.3z"/><path d="m6.2 5.3 3.1 3.9M12.4 3.4l3.1 4M3 11h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>',
                ];
                foreach ($eventServices as $svc) {
                    $name = strtolower($svc['service_name']);
                    $matched = false;
                    foreach ($categoryKeywords as $cat => $keywords) {
                        foreach ($keywords as $kw) {
                            if (strpos($name, $kw) !== false) {
                                $groupedServices[$cat][] = $svc;
                                $matched = true;
                                break 2;
                            }
                        }
                    }
                    if (!$matched) {
                        $others[] = $svc;
                    }
                }
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($categoryKeywords as $cat => $keywords): ?>
                        <?php if (!empty($groupedServices[$cat])): ?>
                            <div
                                class="bg-slate-50 rounded-2xl border border-slate-200 p-5 shadow-sm">
                                <h4 class="flex items-center gap-2 font-bold text-purple-600 mb-3">
                                    <span class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center text-purple-500">
                                        <?= $categoryIcons[$cat] ?? '' ?>
                                    </span>
                                    <?= $cat ?>
                                </h4>
                                <ul class="space-y-2">
                                    <?php foreach ($groupedServices[$cat] as $svc): ?>
                                        <li class="flex items-center gap-2 text-sm text-gray-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-purple-400 shrink-0"></span>
                                            <?= htmlspecialchars($svc['service_name']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!empty($others)): ?>
                        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-5 shadow-sm">
                            <h4 class="flex items-center gap-2 font-bold text-purple-600 mb-3">
                                <span class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center text-purple-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                </span>
                                Other Services
                            </h4>
                            <ul class="space-y-2">
                                <?php foreach ($others as $svc): ?>
                                    <li class="flex items-center gap-2 text-sm text-gray-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-purple-400 shrink-0"></span>
                                        <?= htmlspecialchars($svc['service_name']) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-10">
            <!-- Packages -->
            <div>
                <h3 class="text-3xl font-bold text-purple-400 mb-5 ">Packages</h3>
                <?php if (!empty($eventPackages)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($eventPackages as $pkg): ?>
                            <?php
                            $style = $packageStyles[$pkg['name']] ?? $defaultPackageStyle;
                            $discount = (float) ($pkg['discount'] ?? 0);
                            ?>
                            <div
                                class="group relative overflow-hidden rounded-2xl border-2 <?= $style['border'] ?> bg-white shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 p-4 flex flex-col">
                                <div class="absolute inset-x-0 top-0 h-1 <?= $style['gradient'] ?>"></div>
                                <div class="w-10 h-10 rounded-lg <?= $style['ring'] ?> flex items-center justify-center mb-3 transition-transform duration-300 group-hover:scale-110">
                                    <i data-lucide="<?= $style['icon'] ?>" class="w-5 h-5"></i>
                                </div>
                                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                                    <h4 class="text-base font-bold text-slate-800">
                                        <?= htmlspecialchars($pkg['name']) ?>
                                    </h4>
                                    <?php if ($discount > 0): ?>
                                        <span
                                            class="inline-flex items-center gap-1 <?= $style['badge'] ?> px-2 py-0.5 rounded-full text-[11px] font-bold">
                                            <i data-lucide="tag" class="w-3 h-3"></i>
                                            <?= rtrim(rtrim(number_format($discount, 2), '0'), '.') ?>% OFF
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($pkg['description'])): ?>
                                    <p class="text-xs text-slate-500 leading-relaxed">
                                        <?= htmlspecialchars($pkg['description']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-slate-500 ml-14">No packages available for this event yet.</p>
                <?php endif; ?>
            </div>

            <!-- Reviews -->
            <div>
                <h3 class="text-3xl font-bold text-purple-400 mb-5 ">Reviews</h3>
                <?php if (!empty($eventReviews)): ?>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="prevReview()" aria-label="Previous review"
                            class="w-10 h-10 rounded-full border border-purple-200 bg-white text-purple-500 hover:bg-purple-50 shadow-sm flex items-center justify-center transition cursor-pointer shrink-0">
                            &lt;
                        </button>
                        <div class="flex-1 min-w-0">
                            <div class="relative overflow-hidden">
                                <div id="reviewCarousel" class="flex transition-transform duration-500 ease-out">
                                    <?php foreach ($eventReviews as $rev): ?>
                                        <div
                                            class="w-full shrink-0 bg-slate-50 rounded-2xl shadow-md border border-slate-200 p-6">
                                            <div class="flex items-center gap-3 mb-3">
                                                <div
                                                    class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-500 font-bold text-sm shrink-0">
                                                    <?= htmlspecialchars(strtoupper(substr($rev['user_name'], 0, 2))) ?>
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-gray-800 text-sm">
                                                        <?= htmlspecialchars($rev['user_name']) ?>
                                                    </p>
                                                    <div class="flex gap-0.5 mt-0.5">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <svg class="w-3.5 h-3.5 <?= $i <= $rev['rating'] ? 'text-yellow-400' : 'text-gray-200' ?>"
                                                                fill="currentColor" viewBox="0 0 20 20">
                                                                <path
                                                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                            </svg>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-sm text-slate-600 leading-relaxed">
                                                &ldquo;<?= htmlspecialchars($rev['review_text']) ?>&rdquo;
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="flex justify-center gap-1.5 mt-4" id="reviewDots"></div>
                        </div>
                        <button type="button" onclick="nextReview()" aria-label="Next review"
                            class="w-10 h-10 rounded-full border border-purple-200 bg-white text-purple-500 hover:bg-purple-50 shadow-sm flex items-center justify-center transition cursor-pointer shrink-0">
                            &gt;
                        </button>
                    </div>
                    <script>
                        const reviewCount = <?= count($eventReviews) ?>;
                        let reviewIndex = 0;
                        const carousel = document.getElementById('reviewCarousel');
                        const dotsWrap = document.getElementById('reviewDots');

                        function renderDots() {
                            dotsWrap.innerHTML = '';
                            for (let i = 0; i < reviewCount; i++) {
                                const dot = document.createElement('button');
                                dot.type = 'button';
                                dot.className = 'w-2 h-2 rounded-full transition cursor-pointer ' + (i === reviewIndex ? 'bg-purple-500' : 'bg-purple-200');
                                dot.onclick = function () { goToReview(i); };
                                dotsWrap.appendChild(dot);
                            }
                        }

                        function goToReview(i) {
                            reviewIndex = (i + reviewCount) % reviewCount;
                            carousel.style.transform = 'translateX(-' + reviewIndex * 100 + '%)';
                            renderDots();
                        }

                        function nextReview() { goToReview(reviewIndex + 1); }

                        function prevReview() { goToReview(reviewIndex - 1); }

                        renderDots();
                    </script>
                <?php else: ?>
                    <p class="text-sm text-slate-500">No reviews for this event yet.</p>
                <?php endif; ?>
            </div>

        </div>

        </div>

    </section>
<?php endif; ?>

<!-- Custom Alert Modal -->
<div id="alertModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">

    <div class="bg-white w-full max-w-md mx-4 rounded-3xl shadow-2xl p-8 text-center">

        <!-- Icon -->
        <div id="modalIcon" class="w-16 h-16 mx-auto rounded-full bg-purple-100 flex items-center justify-center">
            <i data-lucide="info" class="w-8 h-8 text-purple-600"></i>
        </div>


        <h2 id="modalTitle" class="text-2xl font-bold text-slate-800 mt-5">
        </h2>


        <p id="modalText" class="text-slate-500 mt-3">
        </p>


        <div class="flex justify-center gap-4 mt-8">

            <button id="modalCancel" onclick="closeModal()"
                class="px-6 py-2 rounded-xl border border-gray-300 text-gray-600 hover:bg-gray-100">
                Cancel
            </button>


            <button id="modalConfirm" class="px-6 py-2 rounded-xl bg-purple-600 text-white hover:bg-purple-700">
                Continue
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


        document.getElementById('alertModal')
            .classList.remove('hidden');

        document.getElementById('alertModal')
            .classList.add('flex');


        document.getElementById('modalCancel').style.display =
            showCancel ? 'block' : 'none';


        confirmAction = callback;
    }



    document.getElementById('modalConfirm')
        .addEventListener('click', () => {

            if (confirmAction) {
                confirmAction();
            }

            closeModal();

        });



    function closeModal() {

        document.getElementById('alertModal')
            .classList.remove('flex');

        document.getElementById('alertModal')
            .classList.add('hidden');

    }


function handleBooking(url) {
    //  If not logged in, show Login Required modal first
    if (!isLoggedIn) {
        const bookingUrl = encodeURIComponent('select_venue.php?event_id=<?= $id ?>');
        showModal(
            'Login Required',
            'Please login or register to book this event.',
            'Login Now',
            function () {
                window.location.href = '../auth/login.php?redirect=' + bookingUrl;
            },
            true
        );
        return;
    }

    const params = new URLSearchParams(url.split('?')[1] || '');
    const hasVenueAndPackage = params.get('venue_id') && params.get('package_id');

    //  If no venue/package is selected, go straight to venue selection
    if (!hasVenueAndPackage) {
        window.location.href = 'select_venue.php?event_id=<?= $id ?>';
        return;
    }

    // If everything is fine, go straight to booking form
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