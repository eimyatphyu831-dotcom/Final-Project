<?php
session_start();
require_once '../config/db.php';

$sql = "SELECT
            r.review_text,
            r.rating,
            u.name AS user_name,
            e.event_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN events e ON r.event_id = e.id
        ORDER BY r.created_at DESC";

$result = mysqli_query($conn, $sql);

$reviews = [];

while ($row = mysqli_fetch_assoc($result)) {
    $reviews[] = $row;
}

$allReviews = array_merge($reviews, $reviews);
?>

<?php include '../includes/header.php'; ?>

<!-- REVIEWS / TESTIMONIALS -->

    <section class="scroll-animate animate-scale max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 bg-[#f6f3fa]">
        <div class="text-center max-w-2xl mx-auto mb-14">
            <span
                class="inline-block px-4 py-1 rounded-full bg-brand-200/60 text-brand-900 text-xs font-semibold mb-4">Testimonials</span>
            <h2 class="text-3xl md:text-4xl font-bold text-brand-600 tracking-tight">What Our Customers Say</h2>
            <p class="text-md text-slate-500 mt-3">Real feedback from the events we've had the privilege to organize.</p>
        </div>
        <div class="marquee-wrapper overflow-hidden w-full">
            <div class="marquee-track flex gap-8" style="animation: marquee 40s linear infinite; width: max-content;">
                <?php
                $allReviews = array_merge($reviews, $reviews);
                foreach ($allReviews as $rev):
                    $initials = '';
                    $parts = explode(' ', $rev['user_name']);
                    foreach ($parts as $p)
                        $initials .= strtoupper($p[0] ?? '');
                    $initials = substr($initials, 0, 2);
                    $colors = ['from-purple-500 to-indigo-500', 'from-pink-500 to-rose-500', 'from-blue-500 to-cyan-500', 'from-emerald-500 to-teal-500', 'from-orange-500 to-amber-500', 'from-violet-500 to-purple-500'];
                    $gradient = $colors[array_rand($colors)];
                    ?>
                    <div
                        class="w-80 shrink-0 group relative bg-white rounded-3xl p-7 border border-slate-100 shadow-sm hover:shadow-xl transition-all duration-500 hover:-translate-y-2">
                        <div class="flex items-start gap-4">
                            <div
                                class="w-12 h-12 rounded-full bg-gradient-to-br <?= $gradient ?> flex items-center justify-center text-white text-sm font-bold shrink-0 shadow-sm mt-0.5">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-brand-900">
                                    <?= htmlspecialchars($rev['user_name']) ?>
                                </p>
                                <p class="text-[10px] text-slate-400 mb-2">
                                    <?= htmlspecialchars($rev['event_name']) ?>
                                </p>
                                <p class="text-sm text-slate-600 leading-relaxed">&ldquo;
                                    <?= htmlspecialchars($rev['review_text']) ?>&rdquo;
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-1 mt-2 pt-2 items-center justify-center">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg class="w-4 h-4 <?= $i <= $rev['rating'] ? 'text-yellow-400' : 'text-gray-200' ?>"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

<?php include '../includes/footer.php'; ?>

<style>
    /* Scroll Animation Base Styles */
    
    /* Stagger children */
    .scroll-animate.visible .stagger-child {
        opacity: 1;
        transform: translateY(0);
    }
    .stagger-child {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.6s ease-out, transform 0.6s ease-out;
    }
    @keyframes marquee {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }
    .marquee-wrapper:hover .marquee-track {
        animation-play-state: paused;
    }
</style>

<script>
    // Intersection Observer for scroll animations
    function initScrollAnimations() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    // Stagger children with delay
                    const children = entry.target.querySelectorAll('.stagger-child');
                    children.forEach((child, i) => {
                        child.style.transitionDelay = `${i * 0.15}s`;
                    });
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });

        document.querySelectorAll('.scroll-animate').forEach(el => observer.observe(el));
    }

</script>