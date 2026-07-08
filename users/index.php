<?php
session_start();

require_once '../config/db.php';


//View all events (JOIN with venues)
$result = $conn->query("SELECT e.*, GROUP_CONCAT(DISTINCT v.name SEPARATOR ', ') AS venue_name FROM events e LEFT JOIN venues v ON v.event_id = e.id GROUP BY e.id ORDER BY e.id DESC LIMIT 3");
$allevents = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get venues with event name
$vResult = $conn->query("SELECT v.*, e.event_name FROM venues v LEFT JOIN events e ON v.event_id = e.id ORDER BY v.name ASC LIMIT 2");
$venues = $vResult ? $vResult->fetch_all(MYSQLI_ASSOC) : [];

// if (empty($venues)) {
//     $venues = [
//         ['name' => 'Grand Ballroom', 'address' => 'Downtown NYC', 'capacity' => 1000, 'price' => 200000.00, 'image_path' => '../assets/images/venue1.png', 'event_name' => ''],
//         ['name' => 'Lakeside Pavilion', 'address' => 'Hudson Valley', 'capacity' => 350, 'price' => 150000.00, 'image_path' => '../assets/images/venue2.png', 'event_name' => ''],
//     ];
// }

// change color on event_type
$badges = [
    'corporate' => 'bg-green-600/60 text-white',
    'wedding' => 'bg-pink-500/60 text-white',
    'birthday' => 'bg-yellow-500/60 text-white',
    'music' => 'bg-blue-500/60 text-white'
];


if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

?>

<?php include '../includes/header.php'; ?>

<!-- HERO IMMERSIVE INTRO AREA -->

<!-- Hero Section -->
<section
    class="relative h-[92vh] min-h-[500px] bg-white flex items-center justify-center text-center px-4 overflow-hidden">

    <!-- Video Slider -->
    <div class="absolute inset-0 z-0">

        <!-- Video 1 -->
        <div class="video-slide absolute inset-0 opacity-100 transition-opacity duration-1000">
            <video class="w-full h-full object-cover brightness-110 contrast-105" autoplay muted loop playsinline>
                <source src="../assets/videos/vedio1.mp4" type="video/mp4">
            </video>

            <!-- Reduced overlay -->
            <div class="absolute inset-0 bg-black/20"></div>
        </div>

        <!-- Video 2 -->
        <div class="video-slide absolute inset-0 opacity-0 transition-opacity duration-1000">
            <video class="w-full h-full object-cover brightness-110 contrast-105" muted loop playsinline>
                <source src="../assets/videos/video2.mp4" type="video/mp4">
            </video>

            <div class="absolute inset-0 bg-black/20"></div>
        </div>

        <!-- Video 3 -->
        <div class="video-slide absolute inset-0 opacity-0 transition-opacity duration-1000">
            <video class="w-full h-full object-cover brightness-110 contrast-105" muted loop playsinline>
                <source src="../assets/videos/video3.mp4" type="video/mp4">
            </video>

            <div class="absolute inset-0 bg-black/20"></div>
        </div>

    </div>

    <!-- Bottom Gradient -->
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-[#fafafa]/40 z-10"></div>

    <!-- Hero Content -->
    <div class="relative max-w-3xl mx-auto z-20 -mt-10 md:-mt-18">

        <!-- <div class="inline-block bg-brand-600 text-brand-900 px-4 py-1 rounded-full text-label-sm mb-6 animate-pulse">
                Event Solutions
            </div> -->

        <h1 class="text-4xl md:text-5xl font-bold text-white leading-tight mb-8">
            Plan Your Perfect
            <span
                class="text-[2.3rem] md:text-[3.7rem] font-extrabold bg-gradient-to-r from-fuchsia-500 via-purple-500 to-indigo-500 bg-clip-text text-transparent drop-shadow-[0_2px_10px_rgba(168,85,247,0.45)]">
                Event
            </span>
            with Us
        </h1>

        <p class="text-lg text-white max-w-xl mx-auto mb-9">
            From weddings to corporate galas, we craft elegant, seamless,
            and unforgettable experiences tailored just for you.
        </p>

        <div class="flex flex-wrap justify-center gap-4">

            <a href="viewevents.php"
                class="bg-brand-200 hover:bg-brand-900 hover:text-white text-brand-900 px-8 py-3 rounded-full font-semibold transition">
                Explore Events
            </a>

            <a href="events.php"
                class="border-2 border-white text-white px-8 py-3 rounded-full hover:bg-white hover:text-black transition">
                Book Now
            </a>

        </div>

    </div>

</section>



<!-- FEATURED EVENTS / SERVICES GRID -->
<section id="events" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">

    <!-- Section Header matching layout in image_cd005b.jpg -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-12 gap-4">
        <div>
            <h2 class="text-3xl font-sans-serif font-bold text-brand-600">Featured Events</h2>
            <p class="text-md text-slate-500 mt-2 max-w-xl">Witness the excellence of our past projects and get inspired
                for your next big occasion.</p>
        </div>
        <a href="viewevents.php"
            class="text-sm font-bold text-brand-900 hover:text-brand-700 flex items-center gap-1.5 shrink-0 transition">
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

                        <?php if (!empty($event['venue_name'])): ?>
                            <p class="text-xs text-purple-500 font-medium mb-2 flex items-center gap-1">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($event['venue_name']) ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-sm text-slate-500 mb-6 leading-relaxed"><?php echo $event['description'] ?></p>
                    </div>
                </div>
                <div class="px-2 pb-2 flex gap-2">
                    <a href="viewdetails.php?id=<?= $event['id']; ?>"
                        class="flex-1 block text-center bg-white hover:bg-brand-600 text-slate-900 border border-slate-200 font-semibold text-sm py-3 rounded-xl transition duration-200 shadow-sm">
                        View Details
                    </a>

                </div>
            </div>
        <?php endforeach; ?>



    </div>

</section>

<!-- CORE SERVICES / BENEFITS GRID -->
<section id="services" class="w-full bg-[#f6f3fa] py-20 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">

        <!-- Centered Header Section matching image_cd171b.png -->
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h2 class="text-3xl font-bold text-brand-600 tracking-tight">Our Professional Services</h2>
            <p class="text-md text-slate-500 mt-4 leading-relaxed">
                We provide end-to-end solutions to ensure every aspect of your event is handled with expert care and
                creative flair.
            </p>
        </div>

        <!-- Left-Aligned 4-Column Card Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-7xl mx-auto">

            <!-- Card 1: Decoration -->
            <div
                class="bg-white p-8 rounded-[1.75rem] border border-slate-100 shadow-md flex flex-col items-start justify-between min-h-[250px] hover:scale-105 transition duration-300">
                <div class="w-full">
                    <!-- Icon Box Wrapper -->
                    <div
                        class="w-10 h-10 bg-[#f6f3fa] text-brand-900 rounded-xl flex items-center justify-center mb-6 hover:bg-brand-600">
                        <i data-lucide="palette" class="w-5 h-5"></i>
                    </div>
                    <h3 class="font-bold text-brand-600 text-xl mb-3">Decoration</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">
                        Transforming spaces with bespoke floral arrangements, lighting, and thematic styling.
                    </p>
                </div>
            </div>

            <!-- Card 2: Photography -->
            <div
                class="bg-white p-8 rounded-[1.75rem] border border-slate-100 shadow-md flex flex-col items-start justify-between min-h-[250px] hover:scale-105 transition duration-300">
                <div class="w-full">
                    <!-- Icon Box Wrapper -->
                    <div
                        class="w-10 h-10 bg-[#f6f3fa] text-brand-900 rounded-xl flex items-center justify-center mb-6 hover:bg-brand-600">
                        <i data-lucide="camera" class="w-5 h-5"></i>
                    </div>
                    <h3 class="font-bold text-brand-600 text-xl mb-3">Photography</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">
                        Capturing every candid moment and grand highlight with cinematic precision and artistry.
                    </p>
                </div>
            </div>

            <!-- Card 3: Catering -->
            <div
                class="bg-white p-8 rounded-[1.75rem] border border-slate-100 shadow-md flex flex-col items-start justify-between min-h-[250px] hover:scale-105 transition duration-300">
                <div class="w-full">
                    <!-- Icon Box Wrapper -->
                    <div
                        class="w-10 h-10 bg-[#f6f3fa] text-brand-900 rounded-xl flex items-center justify-center mb-6 hover:bg-brand-600">
                        <i data-lucide="utensils" class="w-5 h-5"></i>
                    </div>
                    <h3 class="font-bold text-brand-600 text-xl mb-3">Catering</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">
                        Exquisite culinary experiences tailored to your taste, from gourmet galas to intimate buffets.
                    </p>
                </div>
            </div>

            <!-- Card 4: Entertainment -->
            <div
                class="bg-white p-8 rounded-[1.75rem] border border-slate-100 shadow-md flex flex-col items-start justify-between min-h-[250px] hover:scale-105 transition duration-300">
                <div class="w-full">
                    <!-- Icon Box Wrapper -->
                    <div
                        class="w-10 h-10 bg-[#f6f3fa] text-brand-900 rounded-xl flex items-center justify-center mb-6 hover:bg-brand-600">
                        <i data-lucide="clapperboard" class="w-5 h-5"></i>
                    </div>
                    <h3 class="font-bold text-brand-600 text-xl mb-3">Entertainment</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">
                        Curating world-class talent, live music, and immersive performances to captivate your guests.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- CURATED VENUES TIMELINE LIST (PERFECTED IMAGE_CD21E4.JPG UI DESIGN) -->
<section id="venues" class="w-full bg-[#faf9f6] py-16 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto">

        <!-- Left-aligned Section Header matching image_cd21e4.jpg -->
        <div class="flex justify-between">
            <div class="mb-10 pl-2">
                <h2 class="text-3xl font-bold text-brand-600 tracking-tight font-sans-serif">Exclusive Venues</h2>
            </div>
            <div class="mb-10 pl-2">
                <a href="viewvenues.php"
                    class="text-sm font-bold text-brand-900 hover:text-brand-700 flex items-center gap-1.5 shrink-0 transition">
                    View All <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>

        <!-- Venue Card Stack Container -->
        <div class="space-y-8">
            <?php foreach ($venues as $i => $v): ?>
                <?php $reverse = $i % 2 !== 0; ?>
                <div
                    class="bg-[#f3f1f6] rounded-[2rem] overflow-hidden flex flex-col md:flex-row <?= $reverse ? 'md:flex-row-reverse' : '' ?> items-stretch">
                    <div class="w-full md:w-[38%] min-h-[280px] relative">
                        <img src="<?= htmlspecialchars($v['image_path'] ?: '../assets/images/venue1.png') ?>"
                            alt="<?= htmlspecialchars($v['name']) ?>" class="absolute inset-0 w-full h-full object-cover">
                    </div>
                    <div class="p-8 md:p-12 flex-1 flex flex-col justify-center">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-4">
                            <div>
                                <h3 class="text-xl font-bold text-brand-600"><?= htmlspecialchars($v['name']) ?></h3>
                                <?php if (!empty($v['event_name'])): ?>
                                    <span
                                        class="inline-block mt-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-purple-100 text-purple-700"><?= htmlspecialchars($v['event_name']) ?></span>
                                <?php endif; ?>
                                <div class="flex items-center gap-1 text-xs text-slate-400 mt-1 font-medium">
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                                    <span><?= htmlspecialchars($v['address']) ?></span>
                                </div>
                            </div>
                            <span
                                class="sm:self-start bg-brand-600 text-white text-[11px] font-semibold px-4 py-2 rounded-xl tracking-wide whitespace-nowrap">
                                Up to <?= number_format($v['capacity']) ?> Guests
                            </span>
                        </div>
                        <p class="text-xs md:text-sm text-slate-500 mb-6 max-w-xl leading-relaxed">
                            Located at <?= htmlspecialchars($v['address']) ?> with capacity for up to
                            <?= number_format($v['capacity']) ?> guests.
                        </p>
                        <!-- <div class="mt-2">
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2a1b40] transition group">
                                <?= number_format($v['price'], 2) ?> MMK
                            </span>
                        </div> -->
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ANALYTICS & STUDIO BIOGRAPHY OVERVIEW (GEOMETRIC TRIPTYCH) -->
<section id="about" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div
        class="bg-white rounded-[2.5rem] p-8 md:p-12 shadow-sm border border-slate-100 flex flex-col lg:flex-row items-center justify-between gap-12">

        <!-- Left Side Data Content Column -->
        <div class="max-w-md w-full">
            <h2 class="text-3xl font-serif font-bold text-brand-600 leading-tight">
                Creating Unforgettable Moments Since 2010
            </h2>
            <p class="text-sm text-slate-500 mt-4 leading-relaxed">
                Our dedicated design team focuses on every minor detailed element to craft custom environments that
                truly mirror your desired ambiance guidelines.
            </p>

            <!-- Statistical Analytics Layout -->
            <div class="flex gap-12 mt-8">
                <div>
                    <span class="text-3xl font-serif font-bold text-brand-900">500+</span>
                    <p class="text-xs font-medium text-slate-400 mt-1">Events Managed</p>
                </div>
                <div>
                    <span class="text-3xl font-serif font-bold text-brand-900">15+</span>
                    <p class="text-xs font-medium text-slate-400 mt-1">Years Experience</p>
                </div>
            </div>
        </div>

        <!-- Right Side Three Geometric Images Layout System -->
        <div class="w-full lg:w-1/2 grid grid-cols-3 gap-4 items-center justify-center min-h-[280px]">

            <!-- Shape 1: Perfect Circle Frame -->
            <div class="flex flex-col items-center justify-center">
                <div class="w-28 h-28 sm:w-36 sm:h-36 rounded-full overflow-hidden shadow-md border-4 border-slate-50/50 hover:scale-105 transition duration-300 animate-pulse"
                    style="animation-duration: 4s;">
                    <img src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&q=80&w=400"
                        alt="Circle Studio Frame" class="w-full h-full object-cover">
                </div>
            </div>

            <!-- Shape 2: Custom CSS Clip-Path Heart Frame -->
            <div class="flex flex-col items-center justify-center">
                <div class="w-28 h-28 sm:w-36 sm:h-36 overflow-hidden shadow-xl bg-transparent hover:scale-105 transition duration-300 animate-pulse"
                    style="
            mask-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22black%22><path d=%22M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z%22/></svg>');
            -webkit-mask-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22black%22><path d=%22M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z%22/></svg>');
            mask-size: cover;
            -webkit-mask-size: cover;
            mask-repeat: no-repeat;
            -webkit-mask-repeat: no-repeat;
            mask-position: center;
            -webkit-mask-position: center;
            animation-duration: 4s;
         ">
                    <img src="https://images.unsplash.com/photo-1465495976277-4387d4b0b4c6?auto=format&fit=crop&q=80&w=400"
                        alt="Heart Studio Frame" class="w-full h-full object-cover scale-110 object-center">
                </div>
            </div>

            <!-- Shape 3: Geometric Rounded Square Frame -->
            <div class="flex flex-col items-center justify-center">
                <div class="w-28 h-28 sm:w-36 sm:h-36 rounded-[1.75rem] overflow-hidden shadow-md border-4 border-slate-50/50 hover:scale-105 transition duration-300 animate-pulse"
                    style="animation-duration: 4s;">
                    <img src="https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&q=80&w=400"
                        alt="Square Studio Frame" class="w-full h-full object-cover">
                </div>
            </div>

        </div>
    </div>
</section>

<!-- CONCIERGE BOOKING REQUEST SYSTEM -->
<section id="contact" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden flex flex-col md:flex-row border border-slate-100">
        <!-- Dynamic Intake Fields -->
        <div class="p-8 md:p-12 flex-1">
            <h3 class="text-2xl font-serif font-bold text-brand-600 mb-2">Ready to plan details?</h3>
            <p class="text-xs text-slate-400 mb-8">Submit details below and a personal concierge planner will reach out.
            </p>

            <form class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Full Name</label>
                        <input type="text" placeholder="John Doe"
                            class="w-full text-sm bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 focus:outline-none focus:border-brand-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Email Address</label>
                        <input type="email" placeholder="john@example.com"
                            class="w-full text-sm bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 focus:outline-none focus:border-brand-200">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Event Type</label>
                    <select
                        class="w-full text-sm bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-slate-400 focus:outline-none focus:border-brand-200">
                        <option>Select Option...</option>
                        <option>Wedding Ceremony</option>
                        <option>Corporate Seminar/Gala</option>
                        <option>Social/Private Party</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Special Requirements</label>
                    <textarea rows="3" placeholder="Tell us more about your ideas..."
                        class="w-full text-sm bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 focus:outline-none focus:border-brand-200"></textarea>
                </div>
                <button type="submit"
                    class="w-full bg-brand-200 hover:bg-brand-900 text-brand-900 hover:text-white text-sm font-semibold py-3.5 rounded-xl transition duration-200 shadow-md shadow-brand-200/20">Submit
                    Inquiry</button>
            </form>
        </div>

        <!-- Context Block Sidebar -->
        <div
            class="bg-brand-50 p-8 md:p-12 md:w-80 flex flex-col justify-between text-brand-900 border-l border-slate-100">
            <div>
                <h4 class="font-serif font-bold text-xl mb-6 text-brand-600">Contact Information</h4>
                <div class="space-y-4 text-sm text-slate-600">
                    <div class="flex items-start gap-3">
                        <i data-lucide="map-pin" class="w-4 h-4 mt-0.5 shrink-0 text-brand-600"></i>
                        <span>123 Elite Plaza, Suite 500, New York, NY</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i data-lucide="phone" class="w-4 h-4 shrink-0 text-brand-600"></i>
                        <span>+1 (555) 019-2834</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i data-lucide="mail" class="w-4 h-4 shrink-0 text-brand-600"></i>
                        <span>hello@eventplanners.com</span>
                    </div>
                </div>
            </div>
            <div class="pt-8 border-t border-slate-200 text-xs text-slate-400">
                Office Hours: Mon - Fri (9AM - 6PM EST)
            </div>
        </div>
    </div>
</section>

<?php
include '../includes/footer.php';
?>

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


    const slides = document.querySelectorAll(".video-slide");
    const videos = document.querySelectorAll(".video-slide video");

    let current = 0;

    // Play first video
    videos[0].play();

    setInterval(() => {

        slides[current].classList.remove("opacity-100");
        slides[current].classList.add("opacity-0");

        videos[current].pause();

        current = (current + 1) % slides.length;

        slides[current].classList.remove("opacity-0");
        slides[current].classList.add("opacity-100");

        videos[current].currentTime = 0;
        videos[current].play();

    }, 6000); // Change every 6 seconds
</script>