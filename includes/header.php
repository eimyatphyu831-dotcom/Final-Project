<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$username = $_SESSION['user_name'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'EventPro' ?></title>
    <!-- Dark mode: apply class before render -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f9f7fc',
                            100: '#f0eaf7',
                            200: '#C3B1E1',
                            600: '#9966cc',
                            700: '#a020f0',
                            900: '#383242',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        html {
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
        }

        /* Page Scroll Animations */
        .page-animate {
            opacity: 0;
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        .page-animate.fade-up { transform: translateY(40px); }
        .page-animate.fade-left { transform: translateX(-60px); }
        .page-animate.fade-right { transform: translateX(60px); }
        .page-animate.scale-up { transform: scale(0.9); }
        .page-animate.visible {
            opacity: 1;
            transform: translateY(0) translateX(0) scale(1);
        }
        .page-animate.delay-1 { transition-delay: 0.15s; }
        .page-animate.delay-2 { transition-delay: 0.3s; }
        .page-animate.delay-3 { transition-delay: 0.45s; }
        .page-animate.delay-4 { transition-delay: 0.6s; }
        .page-animate.delay-5 { transition-delay: 0.75s; }

        /* Stagger Children */
        .stagger-children .stagger-child {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .stagger-children.visible .stagger-child {
            opacity: 1;
            transform: translateY(0);
        }

        /* Hero Animations */
        .hero-fade {
            opacity: 0;
            transform: translateY(30px);
            animation: heroFadeIn 0.9s ease-out forwards;
        }
        .hero-fade.d1 { animation-delay: 0.2s; }
        .hero-fade.d2 { animation-delay: 0.45s; }
        .hero-fade.d3 { animation-delay: 0.7s; }
        @keyframes heroFadeIn {
            to { opacity: 1; transform: translateY(0); }
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Playfair Display', serif;
        }

        /* Dark Mode Global Styles */
        html.dark {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --bg-card: #1e2a45;
            --text-primary: #e0e0e0;
            --text-secondary: #a0a0b0;
            --border-color: #2a3a5c;
        }

        html.dark body {
            background-color: var(--bg-primary) !important;
        }

        html.dark .bg-white,
        html.dark .bg-gray-50,
        html.dark .bg-gray-100 {
            background-color: var(--bg-card) !important;
        }

        html.dark .bg-\[\#f3f1f6\] {
            background-color: var(--bg-primary) !important;
        }

        html.dark .text-gray-800,
        html.dark .text-gray-900,
        html.dark .text-brand-900,
        html.dark .text-gray-700 {
            color: var(--text-primary) !important;
        }

        html.dark .text-gray-500,
        html.dark .text-gray-600,
        html.dark .text-gray-400 {
            color: var(--text-secondary) !important;
        }

        html.dark .border-slate-100,
        html.dark .border-gray-200,
        html.dark .border-gray-100 {
            border-color: var(--border-color) !important;
        }

        html.dark .bg-brand-50,
        html.dark .hover\:bg-brand-50:hover {
            background-color: rgba(195, 177, 225, 0.1) !important;
        }

        html.dark header,
        html.dark header.bg-\[\#f3f1f6\] {
            background-color: var(--bg-secondary) !important;
        }

        html.dark footer,
        html.dark .bg-\[\#f6f3fa\] {
            background-color: var(--bg-secondary) !important;
        }

        html.dark .shadow-sm,
        html.dark .shadow-md,
        html.dark .shadow-lg,
        html.dark .shadow-xl {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }

        html.dark .divide-gray-100,
        html.dark .divide-slate-50 {
            border-color: var(--border-color) !important;
        }

        html.dark input,
        html.dark textarea,
        html.dark select {
            background-color: #0f1a30 !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        html.dark .bg-brand-200 {
            background-color: #5a4a7a !important;
        }

        /* events / venues / viewevents dark mode */
        html.dark .bg-\[#f6f3fa\],
        html.dark .bg-purple-50 {
            background-color: var(--bg-secondary) !important;
        }
        html.dark .bg-\[#f7f5fa\],
        html.dark .venue-card,
        html.dark .event-card {
            background-color: var(--bg-card) !important;
        }
        html.dark .text-slate-500,
        html.dark .text-slate-400 {
            color: var(--text-secondary) !important;
        }
        html.dark .text-slate-700,
        html.dark .text-slate-900 {
            color: var(--text-primary) !important;
        }
        html.dark .text-brand-900 {
            color: var(--text-primary) !important;
        }
        html.dark .text-brand-600 {
            color: #b8a5d6 !important;
        }
        html.dark .border-slate-200,
        html.dark .border-slate-100,
        html.dark .border-slate-200\/60,
        html.dark .border-purple-200 {
            border-color: var(--border-color) !important;
        }
        html.dark .text-purple-500,
        html.dark .text-purple-400 {
            color: #b8a5d6 !important;
        }
        html.dark .bg-purple-400 {
            background-color: #5a4a7a !important;
        }
        html.dark .text-orange-700 { color: #fbbf24 !important; }
        html.dark .text-orange-400 { color: #f59e0b !important; }
        html.dark .text-orange-500 { color: #f59e0b !important; }
        html.dark .text-blue-700 { color: #93c5fd !important; }
        html.dark .text-blue-400 { color: #60a5fa !important; }
        html.dark .text-blue-500 { color: #60a5fa !important; }
        html.dark .text-blue-600 { color: #60a5fa !important; }
        html.dark .border-orange-400 { border-color: #b45309 !important; }
        html.dark .border-blue-400,
        html.dark .border-blue-300 { border-color: #1e40af !important; }
        html.dark .border-gray-400 { border-color: var(--border-color) !important; }
        html.dark .border-gray-300 { border-color: var(--border-color) !important; }
        html.dark .bg-gray-50 { background-color: #0f1a30 !important; }
        html.dark .bg-orange-100 { background-color: #451a03 !important; }
        html.dark .bg-blue-100 { background-color: #1e3a5f !important; }
        html.dark .bg-blue-50 { background-color: #0f1a30 !important; }
        html.dark .bg-orange-400 { background-color: #92400e !important; }
        html.dark .bg-blue-400 { background-color: #1e40af !important; }
        html.dark .text-gray-700 { color: var(--text-primary) !important; }
        html.dark .text-gray-900 { color: var(--text-primary) !important; }
        html.dark .text-gray-400 { color: var(--text-secondary) !important; }
        html.dark .text-gray-600 { color: var(--text-secondary) !important; }
        html.dark .hover\:bg-gray-700:hover { background-color: #2d3a5c !important; }
        html.dark .border-gray-400 { border-color: var(--border-color) !important; }
        html.dark .bg-white.rounded-3xl { background-color: var(--bg-card) !important; }

        /* index.php dark mode */
        html.dark .bg-slate-50 { background-color: #0f1a30 !important; }
        html.dark .text-slate-600 { color: var(--text-secondary) !important; }
        html.dark .border-slate-50\/50 { border-color: var(--border-color) !important; }
        html.dark .bg-brand-50 { background-color: rgba(195, 177, 225, 0.08) !important; }
        html.dark .text-brand-900 { color: var(--text-primary) !important; }
        html.dark .bg-\[\#f6f3fa\] { background-color: var(--bg-secondary) !important; }
    </style>
</head>

<body class="bg-[#f3f1f6]">
    <?php
    $currentPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    $navGroups = [
        'index.php' => ['index.php'],
        'events.php' => ['events.php', 'viewevents.php', 'viewdetails.php'],
        'reviews.php' => ['reviews.php'],
        'services.php' => ['services.php'],
        'venues.php' => ['venues.php', 'viewvenues.php'],
        'about.php' => ['about.php'],
        'contact.php' => ['contact.php'],
    ];

    function navLinkClass($navPage, $currentPage, $navGroups)
    {
        $pages = $navGroups[$navPage] ?? [$navPage];
        return in_array($currentPage, $pages, true)
            ? 'hover:text-brand-600 transition border-b-2 border-brand-600 text-brand-600'
            : 'hover:text-brand-600 transition border-b-2 border-transparent hover:border-brand-600';
    }

    // Ensure db connection is available for nav queries
    if (!isset($conn) || !($conn instanceof mysqli)) {
        require_once __DIR__ . '/../config/db.php';
    }

    $navEvents = [];
    try {
        if (isset($conn) && $conn instanceof mysqli) {
            $evRes = $conn->query("SELECT DISTINCT LOWER(event_name) AS event_name FROM events ORDER BY event_name ASC");
            if ($evRes) $navEvents = $evRes->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Error $e) {
        $navEvents = [];
    }
    ?>
    
    <!-- HEADER & NAVIGATION SYSTEM  -->
        
    <header class="w-full bg-[#f3f1f6] backdrop-blur-md sticky top-0 z-50 border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
            <div class="flex items-center gap-2 font-bold text-xl text-brand-900">
                <div class="w-8 h-8 rounded-lg bg-brand-200 flex items-center justify-center">
                    <i data-lucide="sparkles" class="w-4 h-4 text-white"></i>

                </div>
                <span class="text-brand-600 font-bold font-sans-serif text-2xl">EventPro</span>
            </div>

            <!-- Mobile Hamburger -->
            <button id="mobileMenuToggle" class="md:hidden text-brand-600 hover:text-brand-900 p-2" aria-label="Toggle menu">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="hidden md:flex items-center gap-8">

                <nav class="flex items-center gap-8 text-md font-medium text-brand-900">

                    <a href="../users/index.php"
                        class="<?= navLinkClass('index.php', $currentPage, $navGroups) ?>">Home</a>

                    <div class="relative group">
                        <a href="../users/events.php"
                            class="<?= navLinkClass('events.php', $currentPage, $navGroups) ?> flex items-center gap-1">
                            Events
                            <svg class="w-3.5 h-3.5 transition-transform group-hover:rotate-180" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </a>
                            <div
                                class="absolute left-0 mt-2 w-44 bg-white dark:bg-[#1e2a45] rounded-xl shadow-lg border border-slate-100 dark:border-[#2a3a5c] py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                                <?php foreach ($navEvents as $ev): $ename = htmlspecialchars(ucfirst($ev['event_name'])); ?>
                                <a href="../users/viewdetails.php?type=<?= htmlspecialchars($ev['event_name']) ?>"
                                    class="block px-4 py-2 text-sm text-brand-900 dark:text-gray-200 hover:bg-brand-50 dark:hover:bg-[#16213e] transition"><?= $ename ?></a>
                                <?php endforeach; ?>
                                <hr class="border-slate-100 dark:border-[#2a3a5c] my-1">
                                <a href="../users/viewevents.php"
                                    class="block px-4 py-2 text-sm text-brand-600 dark:text-[#b8a5d6] font-semibold hover:bg-brand-50 dark:hover:bg-[#16213e] transition">View
                                    All</a>
                            </div>
                    </div>

                    <a href="../users/services.php"
                        class="<?= navLinkClass('services.php', $currentPage, $navGroups) ?>">Services</a>

                    <a href="../users/venues.php"
                        class="<?= navLinkClass('venues.php', $currentPage, $navGroups) ?>">Venues</a>

                     <a href="../users/about.php"
                        class="<?= navLinkClass('about.php', $currentPage, $navGroups) ?>">About</a>

                    <a href="../users/reviews.php"
                        class="<?= navLinkClass('reviews.php', $currentPage, $navGroups) ?>">Reviews</a>

                    <a href="../users/contact.php"
                        class="<?= navLinkClass('contact.php', $currentPage, $navGroups) ?>">Contact</a>

                </nav>


            </div>
            <!-- <div class="hidden md:flex items-center gap-4">
                <a href="../auth/login.php" class="hover:text-brand-600 text-brand-900  transition">Sign In</a>
                <a href="../auth/register.php" class="bg-brand-200 hover:bg-brand-900 text-brand-900 hover:text-white px-5 py-2.5 rounded-full text-sm shadow-sm transition duration-200 font-semibold">Get Started</a>
            </div> -->

            <?php
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $userProfileImage = '';
            if (isset($_SESSION['user_id'])) {
                include_once __DIR__ . '/../config/db.php';
                $q = $conn->query("SELECT image FROM users WHERE id = {$_SESSION['user_id']}");
                if ($q && $row = $q->fetch_assoc()) $userProfileImage = $row['image'];
            }
            ?>

            <div class="hidden md:flex items-center gap-4">

                <!-- Dark Mode Toggle -->
                <button id="themeToggle"
                    class="w-9 h-9 rounded-full flex items-center justify-center text-gray-500 hover:text-gray-700 hover:bg-brand-50 transition"
                    aria-label="Toggle theme">
                    <svg class="theme-moon w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                    <svg class="theme-sun w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                </button>

                <?php if (isset($_SESSION['user_id']) && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin')): ?>

                    <!-- Notification Bell -->
                    <div class="relative" id="notifDropdown">
                        <button id="notifToggle"
                            class="relative p-2 rounded-full hover:bg-brand-50 transition cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-brand-900" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span id="notifBadge"
                                class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 hidden">
                            </span>
                        </button>

                        <div id="notifMenu"
                            class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-white rounded-xl shadow-lg border border-slate-100 hidden opacity-0 scale-95 transition-all duration-200 origin-top-right z-50">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                                <h4 class="text-sm font-semibold text-brand-900">Notifications</h4>
                                <button id="markAllRead"
                                    class="text-xs text-brand-600 hover:text-brand-900 transition cursor-pointer">Mark all
                                    read</button>
                            </div>
                            <div id="notifList" class="max-h-80 overflow-y-auto divide-y divide-slate-50">
                                <div class="p-6 text-center text-sm text-gray-400">Loading...</div>
                            </div>
                            <div class="border-t border-slate-100 p-2">
                                <a href="../users/notifications.php"
                                    class="block text-center text-xs text-brand-600 hover:text-brand-900 py-2 transition font-medium">View
                                    all notifications</a>
                            </div>
                        </div>
                    </div>

                    <div class="relative" id="userDropdown">
                        <button id="dropdownToggle"
                            class="flex items-center gap-3 cursor-pointer p-1 pr-3 rounded-full hover:bg-brand-50 transition">
                            <div class="w-9 h-9 rounded-full bg-brand-200 flex items-center justify-center overflow-hidden">
                                <?php if ($userProfileImage): ?>
                                    <img src="../uploads/profiles/<?= htmlspecialchars($userProfileImage) ?>?t=<?= time() ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-brand-600" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5.121 17.804A9 9 0 1118.88 17.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <span class="font-semibold text-brand-900 text-sm">
                                <?= htmlspecialchars($_SESSION['user_name']) ?>
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="w-4 h-4 text-gray-400 transition-transform duration-200" id="dropdownArrow"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div id="dropdownMenu"
                            class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-slate-100 py-2 hidden opacity-0 scale-95 transition-all duration-200 origin-top-right">
                            <div class="px-4 py-3 border-b border-slate-100">
                                <p class="text-xs text-gray-500">Signed in as</p>
                                <p class="text-sm font-semibold text-brand-900 truncate">
                                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                                </p>
                                <p class="text-xs text-gray-400 truncate">
                                    <?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>
                                </p>
                            </div>
                            <a href="../users/profile.php"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-brand-900 hover:bg-brand-50 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-brand-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                My Profile
                            </a>
                            <a href="../users/my_bookings.php"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-brand-900 hover:bg-brand-50 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-brand-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                My Bookings
                            </a>
                            <a href="../users/my_reviews.php"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-brand-900 hover:bg-brand-50 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-brand-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                                My Reviews
                            </a>
                            <hr class="border-slate-100 my-1">
                            <a href="../auth/logout.php"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Logout
                            </a>
                        </div>
                    </div>

                <?php else: ?>

                    <a href="../auth/login.php" class="hover:text-brand-600 text-brand-900 transition font-medium">
                        Sign In
                    </a>

                    <a href="../auth/register.php"
                        class="bg-brand-200 hover:bg-purple-400 text-brand-900 hover:text-white px-5 py-2.5 rounded-full text-sm shadow-sm transition duration-200 font-semibold">
                        Get Started
                    </a>

                <?php endif; ?>

            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="fixed inset-0 z-[60] hidden md:hidden">
        <div id="mobileOverlay" class="fixed inset-0 bg-black/50"></div>
        <div class="top-0 left-0 right-0 bg-white dark:bg-[#1a1a2e] shadow-2xl overflow-y-auto  pb-6" id="mobilePanel" style="top: 3.5rem;">
            <nav class="p-4 space-y-1">
                <a href="../users/index.php" class="block px-4 py-3 rounded-xl text-sm font-medium text-brand-900 hover:bg-brand-50 dark:hover:bg-[#16213e] transition">Home</a>
                <a href="../users/events.php" class="block px-4 py-3 rounded-xl text-sm font-medium text-brand-900 hover:bg-brand-50 dark:hover:bg-[#16213e] transition">Events</a>
                <a href="../users/services.php" class="block px-4 py-3 rounded-xl text-sm font-medium text-brand-900 hover:bg-brand-50 dark:hover:bg-[#16213e] transition">Services</a>
                <a href="../users/venues.php" class="block px-4 py-3 rounded-xl text-sm font-medium text-brand-900 hover:bg-brand-50 dark:hover:bg-[#16213e] transition">Venues</a>
                <a href="../users/about.php" class="block px-4 py-3 rounded-xl text-sm font-medium text-brand-900 hover:bg-brand-50 dark:hover:bg-[#16213e] transition">About</a>
                <a href="../users/contact.php" class="block px-4 py-3 rounded-xl text-sm font-medium text-brand-900 hover:bg-brand-50 dark:hover:bg-[#16213e] transition">Contact</a>
            </nav>
            <hr class="border-slate-100 dark:border-[#2a3a5c] mx-4">
            <div class="p-4 space-y-3">
                <?php if (isset($_SESSION['user_id']) && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin')): ?>
                    <a href="../users/my_bookings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-brand-900 hover:bg-brand-50 dark:hover:bg-[#16213e] transition">
                        <svg class="w-4 h-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        My Bookings
                    </a>
                    <a href="../users/profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-brand-900 hover:bg-brand-50 dark:hover:bg-[#16213e] transition">
                        <svg class="w-4 h-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        My Profile
                    </a>
                    <a href="../users/reviews.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-brand-900 hover:bg-brand-50 dark:hover:bg-[#16213e] transition">
                        <svg class="w-4 h-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                        My Reviews
                    </a>
                    <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-[#2a1020] transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Logout
                    </a>
                <?php else: ?>
                    <a href="../auth/login.php" class="block w-full text-center px-4 py-3 rounded-xl text-sm font-semibold text-brand-900 border border-slate-200 hover:bg-brand-50 dark:hover:bg-[#16213e] transition">Sign In</a>
                    <a href="../auth/register.php" class="block w-full text-center px-4 py-3 rounded-xl text-sm font-semibold bg-brand-200 hover:bg-purple-400 text-brand-900 hover:text-white transition">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>


            (function () {
                const toggle = document.getElementById('notifToggle');
                const menu = document.getElementById('notifMenu');
                const list = document.getElementById('notifList');
                const badge = document.getElementById('notifBadge');
                const markAllBtn = document.getElementById('markAllRead');
                let pollInterval = null;

                function timeAgo(dateStr) {
                    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
                    if (diff < 60) return 'Just now';
                    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
                    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
                    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
                    return new Date(dateStr).toLocaleDateString();
                }

                function renderNotifications(data) {
                    if (!data.notifications || data.notifications.length === 0) {
                        list.innerHTML = '<div class="p-6 text-center text-sm text-gray-400">No notifications yet</div>';
                    } else {
                        list.innerHTML = data.notifications.map(n => `
                                    <div class="px-4 py-3 hover:bg-brand-50 transition cursor-pointer ${n.is_read == 0 ? 'bg-brand-50/50' : ''}"
                                         data-id="${n.id}" data-link="${n.link || ''}">
                                        <div class="flex items-start gap-3">
                                            <div class="mt-0.5 shrink-0">
                                                ${n.is_read == 0
                                ? '<span class="block w-2 h-2 rounded-full bg-brand-600"></span>'
                                : '<span class="block w-2 h-2 rounded-full bg-gray-200"></span>'}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-brand-900 leading-tight">${n.title}</p>
                                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">${n.message}</p>
                                                <p class="text-[11px] text-gray-400 mt-1">${timeAgo(n.created_at)}</p>
                                            </div>
                                        </div>
                                    </div>
                                `).join('');

                        list.querySelectorAll('[data-id]').forEach(el => {
                            el.addEventListener('click', function () {
                                const id = this.dataset.id;
                                const link = this.dataset.link;
                                fetch('../api/notifications.php?action=mark_read', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'id=' + id
                                }).then(() => {
                                    if (link) window.location.href = link;
                                    else fetchNotifications();
                                });
                            });
                        });
                    }
                    // Update badge
                    if (data.unread > 0) {
                        badge.textContent = data.unread > 99 ? '99+' : data.unread;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }

                function fetchNotifications() {
                    fetch('../api/notifications.php?action=fetch&limit=8')
                        .then(r => r.json())
                        .then(renderNotifications)
                        .catch(() => { });
                }

                function fetchUnreadCount() {
                    fetch('../api/notifications.php?action=unread_count')
                        .then(r => r.json())
                        .then(data => {
                            if (data.unread > 0) {
                                badge.textContent = data.unread > 99 ? '99+' : data.unread;
                                badge.classList.remove('hidden');
                            } else {
                                badge.classList.add('hidden');
                            }
                        })
                        .catch(() => { });
                }

                // Toggle dropdown
                if (toggle && menu) {
                    toggle.addEventListener('click', function (e) {
                        e.stopPropagation();
                        const isHidden = menu.classList.contains('hidden');
                        menu.classList.toggle('hidden');
                        menu.classList.toggle('opacity-0');
                        menu.classList.toggle('scale-95');
                        if (isHidden) fetchNotifications();
                    });

                    document.addEventListener('click', function (e) {
                        if (!menu.contains(e.target) && e.target !== toggle && !toggle.contains(e.target)) {
                            menu.classList.add('hidden');
                            menu.classList.add('opacity-0');
                            menu.classList.add('scale-95');
                        }
                    });
                }

                // Mark all read
                if (markAllBtn) {
                    markAllBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        fetch('../api/notifications.php?action=mark_all_read', { method: 'POST' })
                            .then(() => fetchNotifications());
                    });
                }

                // Poll every 30 seconds
                fetchUnreadCount();
                pollInterval = setInterval(function () {
                    fetchUnreadCount();
                    if (!menu.classList.contains('hidden')) {
                        fetchNotifications();
                    }
                }, 30000);
            })();
        (function () {
            const toggle = document.getElementById('dropdownToggle');
            const menu = document.getElementById('dropdownMenu');
            const arrow = document.getElementById('dropdownArrow');

            if (!toggle || !menu) return;

            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const isHidden = menu.classList.contains('hidden');
                menu.classList.toggle('hidden');
                menu.classList.toggle('opacity-0');
                menu.classList.toggle('scale-95');
                if (arrow) {
                    arrow.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            });

            document.addEventListener('click', function (e) {
                if (!menu.classList.contains('hidden')) {
                    menu.classList.add('hidden');
                    menu.classList.add('opacity-0');
                    menu.classList.add('scale-95');
                    if (arrow) {
                        arrow.style.transform = 'rotate(0deg)';
                    }
                }
            });
        })();

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
                sparkles: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"></path><path d="M19 15l.7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15z"></path><path d="M5 15l.7 2.3L8 18l-2.3.7L5 21l-.7-2.3L2 18l2.3-.7L5 15z"></path></svg>'
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

        // Mobile Menu Toggle
        (function () {
            const toggle = document.getElementById('mobileMenuToggle');
            const menu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileOverlay');

            function openMenu() { if (menu) menu.classList.remove('hidden'); }
            function closeMenu() { if (menu) menu.classList.add('hidden'); }

            if (toggle) toggle.addEventListener('click', openMenu);
            if (overlay) overlay.addEventListener('click', closeMenu);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
        })();

        // Scroll Animations
        document.addEventListener('DOMContentLoaded', function () {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        var children = entry.target.querySelectorAll('.stagger-child');
                        children.forEach(function (child, i) {
                            child.style.transitionDelay = (i * 0.15) + 's';
                        });
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });

            document.querySelectorAll('.page-animate, .stagger-children').forEach(function (el) {
                observer.observe(el);
            });
        });

        // Dark Mode Toggle
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('themeToggle');
            if (!toggle) return;
            const moon = toggle.querySelector('.theme-moon');
            const sun = toggle.querySelector('.theme-sun');

            function updateIcon() {
                const isDark = document.documentElement.classList.contains('dark');
                if (moon) moon.classList.toggle('hidden', isDark);
                if (sun) sun.classList.toggle('hidden', !isDark);
            }

            updateIcon();

            toggle.addEventListener('click', function () {
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                updateIcon();
            });
        });
    </script>
</body>

</html>