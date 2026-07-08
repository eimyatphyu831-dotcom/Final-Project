    <!-- =========================================================================
         8. GLOBAL FOOTER PLATFORM MAP
         ========================================================================= -->
    <footer class="bg-white text-slate-400 pt-16 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-8 mb-12">
            <div class="col-span-2 md:col-span-1">
                <div class="flex items-center gap-2 font-bold text-xl text-white mb-4">
                    <div class="w-6 h-6 rounded bg-brand-200 flex items-center justify-center">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5 text-white font-bold"></i>
                    </div>
                    <span class="text-brand-600 font-bold text-2xl">EventPro</span>
                </div>
                <p class="text-xs text-slate-500 max-w-xs leading-relaxed">Curating luxury events and custom experiences worldwide since 2010.</p>
            </div>
            <div>
                <h5 class="text-brand-600 font-semibold text-sm mb-4">Services</h5>
                <ul class="space-y-2 text-xs text-slate-500">
                    <li><a href="#" class="hover:text-brand-700 transition">Weddings</a></li>
                    <li><a href="#" class="hover:text-brand-700 transition">Corporate</a></li>
                    <li><a href="#" class="hover:text-brand-700 transition">Conferences</a></li>
                    <li><a href="#" class="hover:text-brand-700 transition">Private Parties</a></li>
                </ul>
            </div>
            <div>
                <h5 class="text-brand-600 font-semibold text-sm mb-4">Company</h5>
                <ul class="space-y-2 text-xs text-slate-500">
                    <li><a href="#" class="hover:text-brand-700 transition">Home</a></li>
                    <li><a href="#" class="hover:text-brand-700 transition">About Us</a></li>
                    <li><a href="#" class="hover:text-brand-700 transition">Venues</a></li>
                    <li><a href="#" class="hover:text-brand-700 transition">Services</a></li>
                </ul>
            </div>
            <div>
                <h5 class="text-brand-600 font-semibold text-sm mb-4">Legal</h5>
                <ul class="space-y-2 text-xs text-slate-500">
                    <li><a href="#" class="hover:text-brand-700 transition">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-brand-700 transition">Terms of Service</a></li>
                    <li><a href="#" class="hover:text-brand-700 transition">Cookie Preferences</a></li>
                </ul>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-600">
            <p>&copy; 2026 EventPro Inc. All rights reserved.</p>
            <div class="flex gap-4">
                <a href="#" class="hover:text-white"><i data-lucide="facebook" class="w-4 h-4"></i></a>
                <a href="#" class="hover:text-white"><i data-lucide="instagram" class="w-4 h-4"></i></a>
                <a href="#" class="hover:text-white"><i data-lucide="twitter" class="w-4 h-4"></i></a>
            </div>
        </div>
    </footer>

    <script>
        function renderLucideIcons() {
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                try { window.lucide.createIcons(); return; } catch (e) {}
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
