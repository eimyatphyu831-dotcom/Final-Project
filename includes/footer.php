<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<!-- =========================================================================
         8. GLOBAL FOOTER PLATFORM MAP
         ========================================================================= -->
<footer class="bg-[#f6f3fa] border-t pt-10">
    <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-10">

        <!-- Company -->
        <div>
            <h3 class="text-2xl font-bold text-brand-600 mb-4">
                EventPro
            </h3>

            <p class="text-gray-500 text-sm mb-8">
                We create unforgettable event celebrations and
                luxury experiences with professional planning
                and exceptional service.
            </p>

            <a href="contact.php"
                class="inline-flex items-center justify-center px-12 py-2 rounded-xl border-2 border-brand-600 text-brand-600 font-semibold hover:bg-brand-600 hover:text-white transition">
                CONTACT US
                <i class="fa-solid fa-envelope ml-3"></i>
            </a>
        </div>

        <!-- Useful Links -->
        
        <div class="ml-14">
            <h3 class="text-lg font-bold text-brand-600 mb-4">
                USEFUL LINKS
            </h3>

            <ul class="space-y-5 text-gray-500 text-sm">
                <li>
                    <a href="index.php" class="hover:text-brand-600">Home</a>
                </li>

                <li>
                    <a href="about.php" class="hover:text-brand-600">About</a>
                </li>

                <li>
                    <a href="events.php" class="hover:text-brand-600">Events</a>
                </li>

                <li>
                    <a href="contact.php" class="hover:text-brand-600">Contact</a>
                </li>
            </ul>
        </div>

        <!-- Contact -->
        <div>
            <h3 class="text-lg font-bold text-brand-600 mb-4">
                CONTACT
            </h3>

            <div class="space-y-6 text-gray-500 text-sm">

                <div class="flex gap-2">
                    <!-- <i class="fa-solid fa-house text-brand-600 text-lg mt-2"></i> -->
                    <i data-lucide="map-pin" class="w-5 h-5 mt-2 shrink-0 text-brand-600"></i>
                    <p class="leading-8">
                        No.67,Rose Road, Pyawbwe Township,
                        Mandalay, Myanmar
                    </p>
                </div>

                <div class="flex gap-2">
                    <!-- <i class="fa-solid fa-envelope text-brand-600 text-lg mt-1"></i> -->
                    <i data-lucide="mail" class="w-5 h-5  shrink-0 text-brand-600"></i>
                    <p>eventpro@gmail.com</p>
                </div>

                <div class="flex gap-2">
                    <!-- <i class="fa-solid fa-phone text-brand-600 text-lg mt-1"></i> -->
                    <i data-lucide="phone" class="w-5 h-5  shrink-0 text-brand-600"></i>
                    <p>+95 9 950 305004</p>
                </div>

            </div>
        </div>

    </div>

    <!-- Copyright -->
    <div class="border-t mt-8 py-6">
        <p class="text-center text-gray-500 text-sm">
            &copy; 2026
            <span class="text-brand-600 font-bold text-md">EventPro</span>.
            All rights reserved.
        </p>
    </div>
</footer>

<script>
    function renderLucideIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            try { window.lucide.createIcons(); return; } catch (e) { }
        }
        const fallbackIcons = {
            sparkles: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"></path><path d="M19 15l.7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15z"></path><path d="M5 15l.7 2.3L8 18l-2.3.7L5 21l-.7-2.3L2 18l2.3-.7L5 15z"></path></svg>',
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
    } else { renderLucideIcons(); }
    window.addEventListener('load', renderLucideIcons);
</script>
</body>

</html>