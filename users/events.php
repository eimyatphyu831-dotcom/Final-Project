<?php
session_start();
require_once '../config/db.php';

$result = $conn->query("SELECT e.*, GROUP_CONCAT(DISTINCT v.name SEPARATOR ', ') AS venue_name FROM events e LEFT JOIN venues v ON v.event_id = e.id GROUP BY e.id ORDER BY e.id DESC LIMIT 3");
$allevents = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$badges = [
    'corporate' => 'bg-green-600/60 text-white',
    'wedding' => 'bg-pink-500/60 text-white',
    'birthday' => 'bg-yellow-500/60 text-white',
    'music' => 'bg-blue-500/60 text-white',
    'entertainment' => 'bg-purple-500/60 text-white'
];
?>
<?php include '../includes/header.php'; ?>

<!-- FEATURED EVENTS / SERVICES GRID -->
<section id="events" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

    <!-- Section Header matching layout in image_cd005b.jpg -->
    <div class="relative flex justify-end items-start sm:items-end mb-12 min-h-[90px]">

        <!-- Center Title + Paragraph -->
        <div class="absolute left-1/2 -translate-x-1/2 text-center">
            <h2 class="text-3xl font-bold text-brand-600">Featured Events</h2>
            <p class="text-md text-slate-500 mt-2 max-w-xl mx-auto">
                Witness the excellence of our past projects and get inspired
                for your next big occasion.
            </p>
        </div>

        <!-- Right Side View All -->
        <a href="viewevents.php"
            class="text-sm font-bold text-brand-900 hover:text-brand-700 flex items-center gap-1.5 shrink-0 transition z-10">
            View All <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </a>
    </div>

    <!-- Event Card Layout Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php
        foreach ($allevents as $event):
            $etype = strtolower($event['event_name'] ?? '');
            $badgeClass = $badges[$etype] ?? 'bg-gray-500/60 text-white';
            $label = ucfirst($etype ?: 'Event');
            ?>
            <div
                class="bg-[#f7f5fa] p-4 rounded-[2rem] border border-slate-200/60 shadow-md flex flex-col justify-between hover:shadow-2xl transition duration-300">
                <div>
                    <div class="relative w-full h-52 rounded-2xl overflow-hidden mb-5">
                        <img src="<?php echo $event['image'] ?>"
                            alt="<?= htmlspecialchars($event['event_name'] ?: $label) ?>"
                            class="w-full h-full object-cover">
                        <span
                            class="absolute bottom-4 left-4 <?= $badgeClass ?> backdrop-blur-sm text-[10px] font-bold px-3 py-1.5 rounded-lg uppercase tracking-wider">
                            <?= $label ?>
                        </span>
                    </div>
                    <div class="px-2">

                        <!-- <?php if (!empty($event['venue_name'])): ?>
                            <p class="text-xs text-purple-500 font-medium mb-2 flex items-center gap-1">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($event['venue_name']) ?>
                            </p>
                        <?php endif; ?> -->
                        <p class="text-sm text-slate-500 mb-6 leading-relaxed"><?php echo $event['description'] ?></p>
                    </div>
                </div>
                <div class="px-2 pb-2 flex gap-2">
                    <a href="viewdetails.php?id=<?= $event['id']; ?>"
                        class="flex-1 block text-center bg-white hover:bg-brand-600 text-slate-900 border border-slate-200 font-semibold text-sm py-3 rounded-xl transition duration-200 shadow-sm hover:text-white">
                        View Details
                    </a>

                </div>
            </div>
        <?php endforeach; ?>



    </div>

</section>
<?php include '../includes/footer.php'; ?>
<script>

    function renderLucideIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            try {
                window.lucide.createIcons();
                return;
            } catch (error) {
                console.warn('Lucide init failed, using fallback icons.', error);
            }
        }

        const fallbackIcons = {
            sparkles: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"></path><path d="M19 15l.7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15z"></path><path d="M5 15l.7 2.3L8 18l-2.3.7L5 21l-.7-2.3L2 18l2.3-.7L5 15z"></path></svg>',
            palette: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M12 3a9 9 0 0 0 0 18c1.5 0 2.9-.4 4.1-1.1"></path><path d="M19 16.5A2.5 2.5 0 0 1 16.5 19"></path><circle cx="7" cy="7" r="1"></circle><circle cx="10" cy="5" r="1"></circle><circle cx="15" cy="7" r="1"></circle><circle cx="17" cy="11" r="1"></circle></svg>',
            camera: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M4 7h3l2-3h6l2 3h3v11H4z"></path><circle cx="12" cy="13" r="4"></circle></svg>',
            utensils: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M8 3v7"></path><path d="M8 10v11"></path><path d="M4 3v4"></path><path d="M4 7h4"></path><path d="M16 3v7"></path><path d="M16 10v11"></path></svg>',
            clapperboard: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M20 6H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"></path><path d="M8 6l2 4"></path><path d="M16 6l-2 4"></path></svg>',
            'arrow-right': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M5 12h14"></path><path d="M13 5l7 7-7 7"></path></svg>',
            'arrow-up-right': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M7 17L17 7"></path><path d="M7 7h10v10"></path></svg>',
            'map-pin': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M12 21s7-5.7 7-12a7 7 0 1 0-14 0c0 6.3 7 12 7 12z"></path><circle cx="12" cy="9" r="2.5"></circle></svg>',
            phone: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.08 4.18 2 2 0 0 1 4.06 2h3a2 2 0 0 1 2 1.72c.12.9.34 1.77.66 2.61a2 2 0 0 1-.45 2.11L7.4 8.4a16 16 0 0 0 6.2 6.2l1.96-1.87a2 2 0 0 1 2.11-.45c.84.32 1.71.54 2.61.66A2 2 0 0 1 22 16.92z"></path></svg>',
            mail: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"></path><path d="M22 6l-10 7L2 6"></path></svg>',
            facebook: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>',
            instagram: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><rect x="3" y="3" width="18" height="18" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.5" y2="6.5"></line></svg>',
            twitter: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-3.8 4-7.1 7-4.7 1-.9 1.7-2.1 2.3-3.4z"></path></svg>'
        };

        document.querySelectorAll('[data-lucide]').forEach((el) => {
            if (el.querySelector('svg')) return;
            const name = el.getAttribute('data-lucide');
            if (!name || !fallbackIcons[name]) return;
            el.innerHTML = fallbackIcons[name];
            el.setAttribute('aria-hidden', 'true');
            el.classList.add('inline-flex', 'items-center', 'justify-center');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderLucideIcons);
    } else {
        renderLucideIcons();
    }

    window.addEventListener('load', renderLucideIcons);
</script>