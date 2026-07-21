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
$capResult = $conn->query("SELECT MAX(capacity) AS max_cap FROM venues WHERE event_id = $id");
$maxCapacity = $capResult ? (int) $capResult->fetch_assoc()['max_cap'] : 0;

//   Get average rating from reviews for this event
$ratResult = $conn->query("SELECT ROUND(AVG(rating), 1) AS avg_rating FROM reviews WHERE event_id = $id");
$avgRating = $ratResult ? (float) $ratResult->fetch_assoc()['avg_rating'] : 0;



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
        const params = new URLSearchParams(url.split('?')[1] || '');
        const hasVenueAndPackage = params.get('venue_id') && params.get('package_id');

        if (!hasVenueAndPackage) {
            window.location.href = 'select_venue.php?event_id=<?= $id ?>';
            return;
        }

        if (!isLoggedIn) {
            const bookingUrl = encodeURIComponent(url);
            showModal(
                'Login Required',
                'Please register or login to book this event.',
                'Login Now',
                function () {
                    window.location.href = '../auth/login.php?redirect=' + bookingUrl;
                },
                true
            );
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