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

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar</title>

    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

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
    </style>
</head>

<body>

    <aside class="w-64 bg-sidebar text-gray-500 flex flex-col h-screen fixed left-0 top-0 z-10">

        <!-- TOP WRAPPER -->
        <div class="flex flex-col flex-1">

            <!-- HEADER -->
            <div class="p-3 flex items-center gap-3 border-b border-gray-200">
                <div class="w-8 h-8 bg-purple-brand rounded-lg flex items-center justify-center text-white font-bold">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>
                    <h1 class="text-gray-900 font-bold text-lg leading-tight">
                        EventPro
                    </h1>
                    <span class="text-xs text-gray-500">
                        Admin Panel
                    </span>
                </div>
            </div>

            <!-- NAVIGATION -->
            <nav class="mt-6 px-3 space-y-1 flex-1 overflow-y-auto">

                <!-- Dashboard -->
                <a href="dashboard.php"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition-all <?= activeMenu('dashboard.php') ?>">
                    <i class="fa-solid fa-house w-5 text-purple-brand"></i>
                    Dashboard
                </a>

                <!-- Events -->
                <a href="events.php"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition-all <?= activeMenu('events.php') ?>">
                    <i class="fa-solid fa-calendar-days w-5 text-purple-brand"></i>
                    Events
                </a>

                <!-- Venues -->
                <a href="venues.php"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition-all <?= activeMenu('venues.php') ?>">
                    <i class="fa-solid fa-hotel w-5 text-purple-brand"></i>
                    Venues
                </a>

                <!-- Packages -->
                <a href="packages.php"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition-all <?= activeMenu('packages.php') ?>">
                    <i class="fa-solid fa-gift w-5 text-purple-brand"></i>
                    Packages
                </a>

                <!-- Services -->
                <a href="services.php"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition-all <?= activeMenu('services.php') ?>">
                    <i class="fa-solid fa-concierge-bell w-5 text-purple-brand"></i>
                    Services
                </a>

                <!-- Bookings -->
                <a href="bookings.php"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition-all <?= activeMenu('bookings.php') ?>">
                    <i class="fa-solid fa-clipboard-list w-5 text-purple-brand"></i>
                    Bookings
                </a>

                <!-- Customers -->
                <a href="customers.php"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition-all <?= activeMenu('customers.php') ?>">
                    <i class="fa-solid fa-users w-5 text-purple-brand"></i>
                    Customers
                </a>

                <!-- Messages -->
                <a href="contact_messages.php"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition-all <?= activeMenu('contact_messages.php') ?>">
                    <i class="fa-solid fa-message w-5 text-purple-brand"></i>
                    Messages
                </a>

                <!-- Notifications -->
                <!-- <a href="notifications.php"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition-all <?= activeMenu('notifications.php') ?>">
                    <i class="fa-solid fa-bell w-5 text-purple-400"></i>
                    Notifications
                </a> -->

                <!-- Profile -->
                <!-- <a href="profile.php"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition-all <?= activeMenu('profile.php') ?>">
                    <i class="fa-solid fa-user-gear w-5 text-green-400"></i>
                    Profile
                </a> -->

            </nav>
        </div>

        <!-- BOTTOM -->
        <div class="p-4 border-t border-gray-200">

            <a href="../auth/logout.php"
                class="flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-red-400 hover:text-red-600 transition-all text-sm font-medium border border-red-200/40 bg-red-400 text-white">
                <i class="fa-solid fa-right-from-bracket w-5"></i>
                Log Out
            </a>

        </div>

    </aside>

</body>

</html>