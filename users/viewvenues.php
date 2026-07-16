<?php
session_start();
require_once '../config/db.php';
$isLoggedIn = isset($_SESSION['user_id']);

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

if ($eventId > 0) {
    $result = $conn->query("SELECT v.*, e.event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id WHERE v.event_id = $eventId ORDER BY v.name ASC");
    $venues = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $result = $conn->query("SELECT v.*, e.event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id ORDER BY v.name ASC");
    $venues = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
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
                <div onclick="window.location.href='venue_packages.php?venue_id=<?= $v['id'] ?>&event_id=<?= $v['event_id'] ?>'"
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




<?php include '../includes/footer.php'; ?>

<script>
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