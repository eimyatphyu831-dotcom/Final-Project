<?php
$pendingCount = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
$reviewCount = $conn->query("SELECT COUNT(*) c FROM reviews")->fetch_assoc()['c'] ?? 0;
$recentReviewCount = $conn->query("SELECT COUNT(*) c FROM reviews WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetch_assoc()['c'] ?? 0;

$stmt = $conn->prepare("SELECT profile_image FROM admins WHERE id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$adminImg = $stmt->get_result()->fetch_assoc()['profile_image'] ?? null;
$stmt->close();
$adminAvatar = $adminImg ? 'uploads/profile/' . $adminImg : null;

$pageTitles = [
    'dashboard.php' => ['title' => 'Dashboard', 'subtitle' => 'Welcome back, Admin! Here\'s what\'s happening today.'],
    'events.php'    => ['title' => 'Events', 'subtitle' => 'Manage your events and schedules.'],
    'venues.php'    => ['title' => 'Venues', 'subtitle' => 'Manage your event venues.'],
    'packages.php'  => ['title' => 'Packages', 'subtitle' => 'Manage your service packages.'],
    'services.php'  => ['title' => 'Services', 'subtitle' => 'Manage your available services.'],
    'teams.php'     => ['title' => 'Teams', 'subtitle' => 'Manage your event teams and assignments.'],
    'bookings.php'  => ['title' => 'Bookings', 'subtitle' => 'View and manage all bookings.'],
    'reviews.php'  => ['title' => 'Reviews', 'subtitle' => "View all customer reviews ($reviewCount total)."],
    'customers.php' => ['title' => 'Customers', 'subtitle' => 'View and manage your customers.'],
    'contact_messages.php' => ['title' => 'Messages', 'subtitle' => 'View contact messages from customers.'],
    'profile.php'   => ['title' => 'Profile', 'subtitle' => 'Manage your profile.'],
    'reports.php'   => ['title' => 'Reports & Analytics', 'subtitle' => 'Revenue, bookings, and event popularity insights.'],
    'notifications.php' => ['title' => 'Notifications', 'subtitle' => 'View all notifications.'],
];

$currentPage = basename($_SERVER['PHP_SELF']);
$pageInfo = $pageTitles[$currentPage] ?? ['title' => 'Admin', 'subtitle' => ''];
?>
<style>
    .custom-scroll::-webkit-scrollbar {
        width: 4px;
    }

    .custom-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-scroll::-webkit-scrollbar-thumb {
        background: #c4b5fd;
        border-radius: 9999px;
    }

    .custom-scroll::-webkit-scrollbar-thumb:hover {
        background: #a78bfa;
    }

    .custom-scroll {
        scrollbar-width: thin;
        scrollbar-color: #c4b5fd transparent;
    }
</style>
<header class="bg-white border-b border-gray-100 px-4 sm:px-8 py-3 flex items-center justify-between sticky top-0 z-20">
    <div class="flex items-center gap-3 flex-1">
        <button onclick="toggleSidebar()" class="lg:hidden text-gray-500 hover:text-gray-700 mr-2">
            <i class="fa-solid fa-bars text-xl"></i>
        </button>
        <div>
            <h1 class="text-xl font-bold text-gray-800 leading-tight"><?= htmlspecialchars($pageInfo['title']) ?></h1>
            <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($pageInfo['subtitle']) ?></p>
        </div>
    </div>
    <div class="flex items-center gap-6">
        <!-- Notification Bell -->
        <div class="relative" id="adminNotifDropdown">
            <button id="adminNotifBtn" class="relative text-gray-500 hover:text-gray-700 cursor-pointer">
                <i class="fa-regular fa-bell text-xl"></i>
                <span id="adminNotifBadge"
                    class="absolute -top-1 -right-1 bg-purple-brand text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center hidden">
                </span>
            </button>
            <div id="adminNotifMenu"
                class="hidden absolute right-0 mt-3 w-80 max-w-[90vw] bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden z-50">
                <div class="p-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">Notifications</h3>
                    <button id="adminMarkAllRead"
                        class="text-xs text-purple-600 hover:text-purple-800 cursor-pointer">Mark all read</button>
                </div>
                <div id="adminNotifList" class="max-h-60 overflow-y-auto custom-scroll">
                    <div class="p-6 text-center text-sm text-gray-400">Loading...</div>
                </div>
                <a href="notifications.php"
                    class="block p-3 text-center text-sm text-purple-600 hover:bg-purple-50 font-medium border-t border-gray-100">View
                    All Notifications</a>
            </div>
        </div>

        <script>
            (function () {
                const toggle = document.getElementById('adminNotifBtn');
                const menu = document.getElementById('adminNotifMenu');
                const list = document.getElementById('adminNotifList');
                const badge = document.getElementById('adminNotifBadge');
                const markAllBtn = document.getElementById('adminMarkAllRead');

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
                        list.innerHTML = '<div class="p-6 text-center text-sm text-gray-400">No notifications</div>';
                    } else {
                        list.innerHTML = data.notifications.map(n => `
                        <a href="${n.link || 'notifications.php'}"
                            class="flex items-start gap-3 p-3 hover:bg-gray-50 border-b border-gray-50 transition-all"
                            data-id="${n.id}">
                            <div class="w-8 h-8 rounded-full ${n.is_read == 0 ? 'bg-purple-100 text-purple-600' : 'bg-gray-100 text-gray-400'} flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-bell text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate">${n.title}</p>
                                <p class="text-xs text-gray-500 truncate">${n.message}</p>
                                <p class="text-xs text-gray-400 mt-0.5">${timeAgo(n.created_at)}</p>
                            </div>
                            ${n.is_read == 0 ? '<span class="w-2 h-2 rounded-full bg-purple-brand flex-shrink-0 mt-2"></span>' : ''}
                        </a>
                    `).join('');

                        list.querySelectorAll('[data-id]').forEach(el => {
                            el.addEventListener('click', function (e) {
                                const id = this.dataset.id;
                                fetch('../api/admin_notifications.php?action=mark_read', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'id=' + id
                                });
                            });
                        });
                    }
                    if (data.unread > 0) {
                        badge.textContent = data.unread > 99 ? '99+' : data.unread;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }

                function fetchNotifications() {
                    fetch('../api/admin_notifications.php?action=fetch&limit=8')
                        .then(r => r.json())
                        .then(renderNotifications)
                        .catch(() => { });
                }

                function fetchUnreadCount() {
                    fetch('../api/admin_notifications.php?action=unread_count')
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

                if (toggle && menu) {
                    toggle.addEventListener('click', function (e) {
                        e.stopPropagation();
                        const isHidden = menu.classList.contains('hidden');
                        menu.classList.toggle('hidden');
                        if (isHidden) fetchNotifications();
                    });

                    document.addEventListener('click', function (e) {
                        if (!menu.contains(e.target) && e.target !== toggle && !toggle.contains(e.target)) {
                            menu.classList.add('hidden');
                        }
                    });

                    menu.addEventListener('click', function (e) {
                        e.stopPropagation();
                    });
                }

                if (markAllBtn) {
                    markAllBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        fetch('../api/admin_notifications.php?action=mark_all_read', { method: 'POST' })
                            .then(() => fetchNotifications());
                    });
                }

                fetchUnreadCount();
                setInterval(fetchUnreadCount, 30000);
            })();
        </script>

        <button id="adminThemeToggle"
            class="w-9 h-9 rounded-full flex items-center justify-center text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition"
            aria-label="Toggle theme">
            <svg class="theme-moon w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
            </svg>
            <svg class="theme-sun w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
            </svg>
        </button>
        <div class="relative">
            <button id="profileBtn" class="flex items-center gap-3 border-l pl-6 border-gray-200 cursor-pointer">
                <div
                    class="w-10 h-10 rounded-full overflow-hidden <?= $adminAvatar ? '' : 'bg-purple-100 flex items-center justify-center' ?>">
                    <?php if ($adminAvatar): ?>
                        <img src="<?= $adminAvatar ?>?t=<?= time() ?>" alt="Admin profile"
                            class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fa-solid fa-user text-purple-500 text-sm"></i>
                    <?php endif; ?>
                </div>
                <div class="text-left">
                    <h4 class="text-sm font-semibold text-gray-400 leading-none">
                        <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></h4>
                </div>
            </button>
            <div id="profileDropdown"
                class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden z-50">
                <a href="profile.php"
                    class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-all"><i
                        class="fa-solid fa-user text-purple-500"></i> My Profile</a>
                <a href="../auth/logout.php"
                    class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-all"><i
                        class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </div>
</header>

<script>
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', function () {
            if (!profileDropdown.classList.contains('hidden')) {
                profileDropdown.classList.add('hidden');
            }
        });

        profileDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    const adminToggle = document.getElementById('adminThemeToggle');
    if (adminToggle) {
        const moon = adminToggle.querySelector('.theme-moon');
        const sun = adminToggle.querySelector('.theme-sun');
        const updateAdminIcon = () => {
            const isDark = document.documentElement.classList.contains('dark');
            moon.classList.toggle('hidden', isDark);
            sun.classList.toggle('hidden', !isDark);
        };
        updateAdminIcon();
        adminToggle.addEventListener('click', function () {
            const html = document.documentElement;
            const isDark = html.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateAdminIcon();
        });
    }
</script>