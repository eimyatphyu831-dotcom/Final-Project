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
                title: 'Book This Event',
                text: 'Please select a venue and package to continue.',
                icon: 'info',
                confirmButtonColor: '#9d84c7',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'select_venue.php?event_id=<?= $id ?>';
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