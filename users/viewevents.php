<?php
session_start();
require_once '../config/db.php';

$filterType = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'all';

$sql = "SELECT e.*, GROUP_CONCAT(DISTINCT v.name SEPARATOR ', ') AS venue_name FROM events e LEFT JOIN venues v ON v.event_id = e.id";
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
    'music' => 'bg-blue-500/60 text-white',
];
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

        <!-- Filter Buttons -->
        <div class="mt-8 flex flex-wrap justify-center gap-3">

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

                <div class="px-2 pb-2 flex gap-2">
                    <a href="viewdetails.php?id=<?= $event['id']; ?>"
                        class="flex-1 block text-center bg-brand-600 hover:bg-brand-700 text-white border border-slate-200 font-semibold text-sm py-3 rounded-xl transition duration-200 shadow-sm">
                        View Details
                    </a>

                </div>
            </div>
        <?php endforeach; ?>
    </div>


</section>
<?php
include '../includes/footer.php';
?>

<script>
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