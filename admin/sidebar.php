<?php
 $currentPage = basename($_SERVER['PHP_SELF']);

function activeMenu($page)
{
    global $currentPage;

    return ($currentPage == $page)
        ? 'bg-sidebar-active text-white font-medium'
        : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900';
}
?>

<?php if (!defined('_SIDEBAR_INCLUDED')): ?>
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <?php define('_SIDEBAR_INCLUDED', true); endif; ?>

<style>
    .dark body,
    .dark {
        --admin-bg: #0f0f1a;
        --admin-sidebar: #12122a;
        --admin-card: #1a1a2e;
        --admin-elevated: #252545;
        --admin-text: #e2e0e8;
        --admin-text-sec: #a8a6b8;
        --admin-text-muted: #7a7890;
        --admin-border: #2a2a4a;
        --admin-active: #4a3070;
    }

    .dark {
        background-color: var(--admin-bg);
        color: var(--admin-text);
    }

    .dark .bg-sidebar {
        background-color: var(--admin-sidebar) !important;
    }

    .dark .bg-sidebar-active {
        background-color: var(--admin-active) !important;
    }

    .dark .bg-purple-brand {
        background-color: #4a3070 !important;
    }

    .dark .text-purple-brand {
        color: #b08ad0 !important;
    }

    .dark .bg-white {
        background-color: var(--admin-card) !important;
    }

    .dark .bg-gray-50 {
        background-color: var(--admin-bg) !important;
    }

    .dark .bg-gray-100 {
        background-color: var(--admin-bg) !important;
    }

    .dark .bg-gray-50\/50 {
        background-color: var(--admin-bg) !important;
    }

    .dark .text-gray-900 {
        color: var(--admin-text) !important;
    }

    .dark .text-gray-800 {
        color: var(--admin-text) !important;
    }

    .dark .text-gray-700 {
        color: var(--admin-text-sec) !important;
    }

    .dark .text-gray-600 {
        color: var(--admin-text-sec) !important;
    }

    .dark .text-gray-500 {
        color: var(--admin-text-muted) !important;
    }

    .dark .text-gray-400 {
        color: var(--admin-text-muted) !important;
    }

    .dark .border-gray-200 {
        border-color: var(--admin-border) !important;
    }

    .dark .border-gray-100 {
        border-color: var(--admin-border) !important;
    }

    .dark .border-gray-50 {
        border-color: var(--admin-border) !important;
    }

    .dark .hover\:bg-gray-50:hover {
        background-color: var(--admin-elevated) !important;
    }

    .dark .hover\:bg-gray-100:hover {
        background-color: var(--admin-elevated) !important;
    }

    .dark .hover\:bg-gray-200:hover {
        background-color: var(--admin-elevated) !important;
    }

    .dark .hover\:bg-red-50:hover {
        background-color: #2a1020 !important;
    }

    .dark .hover\:bg-red-600:hover {
        background-color: #4a1525 !important;
    }

    .dark .shadow-sm,
    .dark .shadow-md,
    .dark .shadow-lg,
    .dark .shadow-xl {
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.5) !important;
    }

    .dark .divide-gray-100>* {
        border-color: var(--admin-border) !important;
    }

    .dark .divide-y>* {
        border-color: var(--admin-border) !important;
    }

    .dark hr {
        border-color: var(--admin-border) !important;
    }

    .dark .bg-purple-100 {
        background-color: #2a1a40 !important;
    }

    .dark .text-purple-600 {
        color: #b08ad0 !important;
    }

    .dark .text-purple-700 {
        color: #c09ae0 !important;
    }

    .dark .modal-content {
        background-color: var(--admin-card) !important;
    }

    .dark .modal-overlay {
        background-color: rgba(0, 0, 0, 0.7) !important;
    }

    .dark .bg-yellow-100 {
        background-color: #3a2a10 !important;
    }

    .dark .text-yellow-700 {
        color: #e0b050 !important;
    }

    .dark .bg-green-100 {
        background-color: #103a20 !important;
    }

    .dark .text-green-700 {
        color: #50e080 !important;
    }

    .dark .bg-red-100 {
        background-color: #3a1010 !important;
    }

    .dark .text-red-700 {
        color: #e05050 !important;
    }

    .dark .text-red-600 {
        color: #e06060 !important;
    }

    .dark .bg-purple-50 {
        background-color: #2a1a40 !important;
    }

    .dark .text-purple-500 {
        color: #b08ad0 !important;
    }

    .dark .hover\:bg-purple-50:hover {
        background-color: #3a2a50 !important;
    }

    .dark .bg-indigo-50 {
        background-color: #1a2040 !important;
    }

    .dark .text-indigo-600 {
        color: #8090e0 !important;
    }

    .dark .bg-emerald-50 {
        background-color: #103a20 !important;
    }

    .dark .text-emerald-600 {
        color: #50e080 !important;
    }

    .dark .bg-blue-50 {
        background-color: #102040 !important;
    }

    .dark .text-blue-600 {
        color: #6090e0 !important;
    }

    .dark .bg-rose-50 {
        background-color: #3a1020 !important;
    }

    .dark .text-rose-600 {
        color: #e06070 !important;
    }

    .dark .bg-amber-50 {
        background-color: #3a2a10 !important;
    }

    .dark .text-amber-600 {
        color: #e0b050 !important;
    }

    .dark input,
    .dark select,
    .dark textarea {
        background-color: var(--admin-elevated) !important;
        border-color: var(--admin-border) !important;
        color: var(--admin-text) !important;
    }

    .dark input:focus,
    .dark select:focus,
    .dark textarea:focus {
        border-color: #6a5090 !important;
    }

    .dark table thead {
        background-color: var(--admin-bg) !important;
    }

    .dark table tbody tr:hover {
        background-color: var(--admin-elevated) !important;
    }

    .dark .bg-red-800 {
        background-color: #4a1020 !important;
    }

    .dark .bg-red-500 {
        background-color: #6a2020 !important;
    }

    .dark .bg-green-500 {
        background-color: #206a30 !important;
    }

    .dark .bg-yellow-500 {
        background-color: #6a5020 !important;
    }

    .dark .bg-blue-500 {
        background-color: #20406a !important;
    }

    .dark .border-red-200 {
        border-color: #4a2020 !important;
    }

    .dark .border-green-200 {
        border-color: #204a30 !important;
    }

    .dark img {
        opacity: 0.85;
        transition: opacity 0.3s;
    }

    .dark img:hover {
        opacity: 1;
    }

    .nav-icon {
        transition: transform 0.3s ease, color 0.3s ease;
    }

    .sidebar-link:hover .nav-icon {
        transform: scale(1.15) rotate(-3deg);
    }

    .sidebar-link.bg-sidebar-active .nav-icon {
        transform: scale(1.1);
    }

    .sidebar-icon {
        transition: transform 0.3s ease, color 0.3s ease;
    }

    .sidebar-icon:hover {
        transform: scale(1.2) rotate(5deg);
        animation: iconPulse 0.6s ease infinite alternate;
    }

    @keyframes iconPulse {
        from {
            filter: drop-shadow(0 0 2px rgba(147, 51, 234, 0.3));
        }

        to {
            filter: drop-shadow(0 0 8px rgba(147, 51, 234, 0.6));
        }
    }

    .sidebar-icon.active {
        color: #9333ea;
        transform: scale(1.15);
    }

    .nav-icon-swap {
        animation: iconFlip 0.4s ease;
    }

    @keyframes iconFlip {
        0% {
            transform: scale(1) rotate(0deg);
            opacity: 1;
        }

        50% {
            transform: scale(1.3) rotate(15deg);
            opacity: 0.5;
        }

        100% {
            transform: scale(1) rotate(0deg);
            opacity: 1;
        }
    }

    /* ---- SIDEBAR COLLAPSE ---- */
    #sidebar {
        width: 14rem;
        transition: width 0.3s ease-in-out, transform 0.3s ease-in-out;
    }

    body.sidebar-collapsed #sidebar {
        width: 5rem !important;
    }

    .sidebar-collapsed .sidebar-text,
    .sidebar-collapsed .sidebar-header-text {
        width: 0;
        opacity: 0;
        overflow: hidden;
        display: none !important; /* Completely removed from flow so logo can center */
        transition: opacity 0.2s ease, width 0.3s ease;
    }

    .sidebar-collapsed .sidebar-header-title {
        display: flex;
        flex-direction: column;
    }

    .sidebar-collapsed #sidebar .logo-area {
        justify-content: center;
        padding: 0.625rem 0;
        gap: 0 !important; /* Remove gaps so logo is perfectly centered */
    }

    /* Hide toggle icon when collapsed to allow logo to be perfectly centered */
    @media (min-width: 1024px) {
        .sidebar-collapsed #sidebar .header-tools {
            display: none !important;
            margin-left: 0 !important;
        }

        /* Hide scrollbar visually but allow scrolling if needed */
        .sidebar-collapsed #sidebarNav {
            overflow-y: auto !important;
            scrollbar-width: none;
            /* Firefox */
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .sidebar-collapsed #sidebarNav::-webkit-scrollbar {
            display: none;
            /* Chrome, Safari, Edge */
        }
    }

    .sidebar-collapsed .nav-group {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .sidebar-collapsed .sidebar-link {
        justify-content: center;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        gap: 0;
        position: relative;
        width: 100%;
    }

    .sidebar-collapsed .sidebar-link i {
        margin: 0;
    }

    .sidebar-collapsed #sidebar .bottom-area {
        display: flex;
        justify-content: center;
        padding: 0.75rem 0 !important;
    }

    .sidebar-collapsed #sidebar .bottom-area a {
        justify-content: center;
        padding: 0.5rem;
        gap: 0;
        width: 100%;
    }

    .sidebar-collapsed #sidebar .bottom-area a i {
        margin: 0;
    }

    .sidebar-collapsed .collapse-icon {
        transform: rotate(180deg);
    }

    /* Tooltip for collapsed sidebar */
    .sidebar-collapsed .sidebar-link::after,
    .sidebar-collapsed .bottom-area a::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        margin-left: 0.75rem;
        padding: 0.375rem 0.625rem;
        background: #1f2937;
        color: #fff;
        font-size: 0.75rem;
        line-height: 1.2;
        border-radius: 0.375rem;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
        z-index: 100;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);
    }

    .sidebar-collapsed .sidebar-link:hover::after,
    .sidebar-collapsed .bottom-area a:hover::after {
        opacity: 1;
    }

    .dark .sidebar-collapsed .sidebar-link::after,
    .dark .sidebar-collapsed .bottom-area a::after {
        background: #374151;
        color: #f3f4f6;
    }

    /* Main content margin adjustment via body class */
    body.sidebar-collapsed .lg\:ml-56 {
        margin-left: 5rem !important;
    }

    /* Collapse toggle button */
    .collapse-btn {
        transition: transform 0.3s ease, background 0.2s ease;
    }

    .collapse-btn:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .dark .collapse-btn:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    /* Hide search bar when collapsed */
    body.sidebar-collapsed #sidebarSearch {
        display: none !important;
    }

    /* Ensure nav spans don't affect layout when collapsed */
    .sidebar-collapsed .sidebar-link .sidebar-text {
        display: inline-block;
    }
</style>

<aside id="sidebar"
    class="bg-sidebar text-gray-500 flex flex-col h-screen fixed left-0 top-0 z-50 -translate-x-full lg:translate-x-0">

    <!-- OVERLAY for mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- TOP WRAPPER -->
    <div class="flex flex-col flex-1 overflow-hidden">

        <!-- HEADER -->
        <!-- Added flex-shrink-0 to prevent logo area from scrolling -->
        <div class="p-2.5 flex items-center gap-3 border-b border-gray-200 logo-area flex-shrink-0">

            <!-- Added onclick toggle so users can click the logo to expand the sidebar back -->
            <div onclick="toggleCollapse()" title="Toggle Sidebar"
                class="w-8 h-8 bg-purple-brand rounded-lg flex items-center justify-center text-white font-bold flex-shrink-0 cursor-pointer">
                <i class="fa-solid fa-calendar-days"></i>
            </div>

            <div class="sidebar-header-text sidebar-header-title min-w-0">
                <h1 class="text-gray-900 font-bold text-lg leading-tight">
                    EventPro
                </h1>
                <span class="text-xs text-gray-500">
                    Admin Panel
                </span>
            </div>

            <div class="flex items-center gap-3 text-gray-500 text-base ml-auto header-tools">
                <button onclick="toggleCollapse()"
                    class="collapse-btn hidden lg:flex items-center justify-center w-7 h-7 rounded-lg cursor-pointer text-gray-400 hover:text-purple-600"
                    title="Toggle sidebar">
                    <i class="fa-solid fa-table-columns cursor-pointer hover:text-purple-600 sidebar-icon"></i>
                </button>
            </div>

            <button onclick="toggleSidebar()" class="lg:hidden text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- SEARCH -->
        <div id="sidebarSearch" class="hidden px-3 py-2 border-b border-gray-100 flex-shrink-0">
            <input type="text" id="sidebarSearchInput" placeholder="Search menu..."
                class="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-200 bg-gray-50 focus:outline-none focus:border-purple-400">
        </div>

        <!-- NAVIGATION -->
        <nav id="sidebarNav" class="mt-3 px-3 space-y-0.4 flex-1 overflow-y-auto">

            <div class="nav-group">
                <a href="dashboard.php" data-tooltip="Dashboard"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('dashboard.php') ?>">
                    <i class="fa-solid fa-house w-5 text-purple-brand flex-shrink-0" data-nav="dashboard"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a>

                <a href="events.php" data-tooltip="Events"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('events.php') ?>">
                    <i class="fa-solid fa-calendar-days w-5 text-purple-brand flex-shrink-0" data-nav="events"></i>
                    <span class="sidebar-text">Events</span>
                </a>

                <a href="venues.php" data-tooltip="Venues"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('venues.php') ?>">
                    <i class="fa-solid fa-hotel w-5 text-purple-brand flex-shrink-0" data-nav="venues"></i>
                    <span class="sidebar-text">Venues</span>
                </a>

                <a href="packages.php" data-tooltip="Packages"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('packages.php') ?>">
                    <i class="fa-solid fa-gift w-5 text-purple-brand flex-shrink-0" data-nav="packages"></i>
                    <span class="sidebar-text">Packages</span>
                </a>

                <a href="services.php" data-tooltip="Services"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('services.php') ?>">
                    <i class="fa-solid fa-concierge-bell w-5 text-purple-brand flex-shrink-0" data-nav="services"></i>
                    <span class="sidebar-text">Services</span>
                </a>

                <a href="teams.php" data-tooltip="Teams"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('teams.php') ?>">
                    <i class="fa-solid fa-users w-5 text-purple-brand flex-shrink-0" data-nav="teams"></i>
                    <span class="sidebar-text">Teams</span>
                </a>

                <a href="bookings.php" data-tooltip="Bookings"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('bookings.php') ?>">
                    <i class="fa-solid fa-clipboard-list w-5 text-purple-brand flex-shrink-0" data-nav="bookings"></i>
                    <span class="sidebar-text">Bookings</span>
                </a>

                <a href="reviews.php" data-tooltip="Reviews"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('reviews.php') ?>">
                    <i class="fa-solid fa-star w-5 text-purple-brand flex-shrink-0" data-nav="reviews"></i>
                    <span class="sidebar-text">Reviews</span>
                </a>

                <a href="customers.php" data-tooltip="Customers"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('customers.php') ?>">
                    <i class="fa-solid fa-user-group w-5 text-purple-brand flex-shrink-0" data-nav="customers"></i>
                    <span class="sidebar-text">Customers</span>
                </a>

                <a href="reports.php" data-tooltip="Reports"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('reports.php') ?>">
                    <i class="fa-solid fa-chart-column w-5 text-purple-brand flex-shrink-0" data-nav="reports"></i>
                    <span class="sidebar-text">Reports</span>
                </a>

                <a href="contact_messages.php" data-tooltip="Messages"
                    class="sidebar-link flex items-center gap-3 px-4 py-2 rounded-xl transition-all <?= activeMenu('contact_messages.php') ?>">
                    <i class="fa-solid fa-message w-5 text-purple-brand flex-shrink-0" data-nav="messages"></i>
                    <span class="sidebar-text">Messages</span>
                </a>
            </div>

        </nav>
    </div>

    <!-- BOTTOM -->
    <!-- Added flex-shrink-0 to prevent bottom area from scrolling -->
    <div class="p-3 border-t border-gray-200 bottom-area flex-shrink-0">
        <a href="../auth/logout.php" data-tooltip="Log Out"
            class="flex items-center gap-3 px-4 py-2 rounded-xl hover:bg-red-400 hover:text-red-600 transition-all text-sm font-medium border border-red-200/40 bg-red-400 text-white">
            <i class="fa-solid fa-right-from-bracket w-5 flex-shrink-0"></i>
            <span class="sidebar-text">Log Out</span>
        </a>
    </div>

</aside>

<script>
    // ---- Mobile Sidebar Toggle ----
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const isOpen = !sidebar.classList.contains('-translate-x-full');
        sidebar.classList.toggle('-translate-x-full', isOpen);
        if (overlay) overlay.classList.toggle('hidden', isOpen);
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.remove('-translate-x-full');
        if (overlay) overlay.classList.remove('hidden');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
                toggleSidebar();
            }
        }
    });

    // ---- Desktop Collapse ----
    function toggleCollapse() {
        // Prevent toggling on mobile view (width < 1024px)
        if (window.innerWidth < 1024) return;

        document.body.classList.toggle('sidebar-collapsed');
        const isCollapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed ? '1' : '0');
    }

    // Restore collapsed state
    if (localStorage.getItem('sidebarCollapsed') === '1') {
        document.body.classList.add('sidebar-collapsed');
    }

    // Adjust nav icon widths on collapse (remove fixed w-5 when collapsed)
    const styleCheck = document.createElement('style');
    styleCheck.textContent = `
            body.sidebar-collapsed .sidebar-link i.w-5 {
                width: auto;
            }
        `;
    document.head.appendChild(styleCheck);

    // Dynamic Search
    function toggleSearch() {
        const el = document.getElementById('sidebarSearch');
        const icon = document.querySelector('.fa-magnifying-glass');
        if (!el || !icon) return;
        el.classList.toggle('hidden');
        icon.classList.toggle('active');
        if (!el.classList.contains('hidden')) {
            document.getElementById('sidebarSearchInput').focus();
        }
    }

    const searchInput = document.getElementById('sidebarSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#sidebarNav a').forEach(link => {
                link.style.display = link.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // Dynamic Compact Mode (Legacy/Unused toggle)
    let compact = false;
    function toggleCompact() {
        const sidebar = document.getElementById('sidebar');
        const icon = document.querySelector('.fa-table-columns');
        if (!sidebar || !icon) return;
        compact = !compact;
        sidebar.classList.toggle('compact', compact);
        icon.classList.toggle('active', compact);
    }
</script>