<?php
session_start();
require_once '../config/db.php';

$filterType = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'all';

$sql = "SELECT e.* FROM events e";
if ($filterType !== 'all') {
    $filterTypeSafe = $conn->real_escape_string($filterType);
    $sql .= " WHERE LOWER(e.event_name) = '$filterTypeSafe'";
}
$sql .= " GROUP BY e.id ORDER BY e.id DESC";
$result = $conn->query($sql);
$allevents = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Fetch distinct event types from DB
$evTypes = [];
$tRes = $conn->query("SELECT DISTINCT LOWER(event_name) AS event_name FROM events ORDER BY event_name ASC");
if ($tRes) $evTypes = $tRes->fetch_all(MYSQLI_ASSOC);

// change color on event_type
$badges = [
    'corporate' => 'bg-green-600/60 text-white',
    'wedding' => 'bg-pink-500/60 text-white',
    'birthday' => 'bg-yellow-500/60 text-white',
    'music' => 'bg-purple-500/60 text-white',
    'educational' => 'bg-blue-500/60 text-white'
];

$isLoggedIn = isset($_SESSION['user_id']);
?>

<?php
include '../includes/header.php';
?>

<!-- TITLE + FILTER SECTION -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 bg-purple-50 ">
    
    <div class="bg-purple-50 px-6 py-10 shadow-sm  text-center">

        

        <!-- Title -->
        <h2 class="text-3xl font-bold text-brand-600">
            Explore Events
        </h2>

        <p class="text-sm text-slate-500 mt-2">
            Browse and filter events by name
        </p>

        <div class="relative mt-8">

    <!-- Center Filter Buttons -->
    <div class="flex flex-wrap justify-center gap-3">

        <?php
        function btnClass($filterType, $type)
        {
            return $filterType === $type
                ? 'bg-purple-600 text-white shadow-md'
                : 'bg-white border border-purple-200 text-slate-700 hover:bg-purple-100';
        }
        ?>

        <button onclick="filterEvents('all')"
            class="px-4 py-2 text-sm rounded-full transition <?= btnClass($filterType, 'all') ?>">
            All
        </button>

        <?php foreach ($evTypes as $ev): $ename = strtolower($ev['event_name']); ?>
            <button onclick="filterEvents('<?= htmlspecialchars($ename) ?>')"
                class="px-4 py-2 text-sm rounded-full transition <?= btnClass($filterType, $ename) ?>">
                <?= htmlspecialchars(ucfirst($ename)) ?>
            </button>
        <?php endforeach; ?>

    </div>

    <!-- Back Button -->
    <a href="javascript:history.back()"
        class="absolute right-0 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 text-sm text-brand-600 hover:text-brand-700 font-bold">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Back
    </a>

</div>
</section>

<!-- EVENT GRID -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20 bg-purple-50 ">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php
        foreach ($allevents as $event):

            ?>
            <!-- EVENT 1 -->

            <?php
            $etype = strtolower($event['event_name'] ?? '');
            $badgeClass = $badges[$etype] ?? 'bg-gray-500/60 text-white';
            $label = ucfirst($etype ?: 'Event');
            ?>
            <div class="event-card bg-[#f7f5fa] p-4 rounded-[2rem] border border-slate-200/60 shadow-sm flex flex-col justify-between hover:shadow-md transition duration-300"
                data-type="<?= htmlspecialchars($etype) ?>">

                <div>
                    <div class="relative w-full h-52 rounded-2xl overflow-hidden mb-5">
                        <img src="<?php echo $event['image'] ?>" class="w-full h-full object-cover">
                        <span
                            class="absolute bottom-4 left-4 <?= $badgeClass ?> text-[10px] font-bold uppercase tracking-wider px-3 py-1 rounded-md backdrop-blur-sm">
                            <?= $label ?>
                        </span>
                    </div>

                    <div class="px-2">

                        <!-- <?php if (!empty($event['venue_name'])): ?>
                            <p class="text-xs text-purple-500 font-medium mb-2 flex items-center gap-1">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($event['venue_name']) ?>
                            </p>
                        <?php endif; ?> -->
                        <p class="text-sm text-slate-500 mb-6">
                            <?php echo $event['description'] ?>
                        </p>
                    </div>
                </div>

                <div class="px-2 pb-2 flex gap-2.5">
                    <a href="viewdetails.php?id=<?= $event['id']; ?>"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 text-center border-2 border-brand-600 text-brand-600 hover:bg-brand-600 hover:text-white font-semibold text-sm py-2.5 rounded-xl transition duration-200">
                        <i data-lucide="eye" class="w-4 h-4"></i> View
                    </a>
<a href="javascript:void(0)" onclick="handleBooking(<?= $event['id']; ?>)"
                class="flex-1 inline-flex items-center justify-center gap-1.5 text-center bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm py-2.5 rounded-xl transition duration-200 shadow-md hover:shadow-lg hover:-translate-y-0.5">
                <i data-lucide="calendar-check" class="w-4 h-4"></i> Book
            </a>

                </div>
            </div>
        <?php endforeach; ?>
    </div>


</section>
<?php
include '../includes/footer.php';
?>

<!-- Custom Alert Modal -->
<div id="alertModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">

    <div class="bg-white w-full max-w-md mx-4 rounded-3xl shadow-2xl p-8 text-center">

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

    function handleBooking(eventId) {
        const bookingUrl = encodeURIComponent('select_venue.php?event_id=' + eventId);
        if (!isLoggedIn) {
            showModal(
                'Login Required',
                'Please login or register to book this event.',
                'Login Now',
                () => {
                    window.location.href = '../auth/login.php?redirect=' + bookingUrl;
                }
            );
            return;
        }
        window.location.href = 'select_venue.php?event_id=' + eventId;
    }

    function filterEvents(type) {
        const cards = document.querySelectorAll(".event-card");

        // Normalize the clicked type: lowercase it and replace spaces with hyphens
        const targetType = type.toLowerCase().trim().replace(/ /g, "-");

        cards.forEach(card => {
            // Normalize the card's data-type attribute safely
            const rawCardType = card.getAttribute("data-type") || "";
            const cardType = rawCardType.toLowerCase().trim();

            if (type === "all" || cardType === targetType) {
                card.style.display = "flex"; // Shows matching cards
            } else {
                card.style.display = "none"; // Hides non-matching cards
            }
        });
    }
</script>