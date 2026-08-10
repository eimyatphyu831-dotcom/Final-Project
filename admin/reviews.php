<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$search = $_GET['search'] ?? '';

// Fetch all reviews
$reviews = [];
$searchFilter = $search !== '' ? "WHERE u.name LIKE '%" . $conn->real_escape_string($search) . "%' OR u.email LIKE '%" . $conn->real_escape_string($search) . "%' OR e.event_name LIKE '%" . $conn->real_escape_string($search) . "%' OR r.review_text LIKE '%" . $conn->real_escape_string($search) . "%'" : '';
$query = "SELECT r.id, r.rating, r.review_text, r.created_at,
                 u.name AS user_name, u.email AS user_email, u.image AS user_image,
                 e.event_name, b.event_date
          FROM reviews r
          JOIN users u ON r.user_id = u.id
          JOIN events e ON r.event_id = e.id
          JOIN bookings b ON r.booking_id = b.id
          $searchFilter
          ORDER BY r.created_at DESC";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $reviews = $result->fetch_all(MYSQLI_ASSOC);
}

// Pagination
$rTotal = count($reviews);
if ($search !== '') {
    $rTotalPages = 1;
    $rOffset = 0;
    $paginatedReviews = $reviews;
} else {
    $rPage = isset($_GET['r_page']) ? max(1, (int)$_GET['r_page']) : 1;
    $rPerPage = 8;
    $rTotalPages = ceil($rTotal / $rPerPage);
    $rOffset = ($rPage - 1) * $rPerPage;
    $paginatedReviews = array_slice($reviews, $rOffset, $rPerPage);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Reviews</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Playfair Display', serif; }
        .bg-sidebar { background-color: #ffffff; }
        .bg-sidebar-active { background-color: #C3B1E1; color: #ffffff; }
        .text-purple-brand { color: #9966cc; }
        .bg-purple-brand { background-color: #C3B1E1; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #c4b5fd; border-radius: 9999px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #a78bfa; }
        .custom-scroll { scrollbar-width: thin; scrollbar-color: #c4b5fd transparent; }
    </style>
</head>

<body class="bg-gray-50 min-h-screen overflow-hidden">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>
        <div class="flex-1 flex flex-col lg:ml-56">
            <?php include 'admin_header.php'; ?>
            <main class="flex-1 p-6 overflow-y-auto custom-scroll">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <form method="GET" class="relative" id="searchForm">
                        <button type="submit" aria-label="Search"
                            class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-purple-500">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </button>
                        <input type="text" id="reviewSearch" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search reviews..."
                            class="w-72 pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-400 bg-white">
                    </form>
                    <span id="totalBadge" class="bg-purple-100 text-purple-700 px-3 py-1.5 rounded-full text-xs font-semibold"><?= count($reviews) ?> total</span>
                </div>

                <?php if (count($reviews) === 0 && $search === ''): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-14 text-center max-w-lg mx-auto mt-10">
                        <div class="mx-auto w-16 h-16 bg-purple-50 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-500">No reviews yet</h3>
                        <p class="text-sm text-gray-400 mt-1">Reviews from customers will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                                        <th class="p-4 text-center w-10">No.</th>
                                        <th class="p-4 text-left">Customer</th>
                                        <th class="p-4 text-left">Event</th>
                                        <th class="p-4 text-left">Rating</th>
                                        <th class="p-4 text-left">Review</th>
                                        <th class="p-4 text-left">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php $rIndex = $rOffset; ?>
                                    <?php foreach ($paginatedReviews as $r): $rIndex++; ?>
                                        <tr class="hover:bg-gray-50/50 transition">
                                            <td class="p-4 text-center text-gray-500"><?= $rIndex ?></td>
                                            <td class="p-4">
                                                <div class="flex items-center gap-3">
                                                    <?php
                                                        $img = $r['user_image'] ? '../uploads/profiles/' . $r['user_image'] : null;
                                                        $initials = strtoupper(substr($r['user_name'], 0, 2));
                                                    ?>
                                                    <?php if ($img): ?>
                                                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($r['user_name']) ?>"
                                                            class="w-9 h-9 rounded-full object-cover border-2 border-white shadow-sm shrink-0"
                                                            onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-purple-500 to-indigo-500 items-center justify-center text-white text-xs font-bold shrink-0" style="display:none">
                                                            <?= $initials ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-purple-500 to-indigo-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                                            <?= $initials ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($r['user_name']) ?></div>
                                                        <div class="text-[11px] text-gray-400"><?= htmlspecialchars($r['user_email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <div class="font-medium text-gray-800"><?= htmlspecialchars($r['event_name']) ?></div>
                                                <div class="text-[11px] text-gray-400"><?= date('M j, Y', strtotime($r['event_date'])) ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="flex items-center gap-0.5">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <svg class="w-4 h-4 <?= $i <= $r['rating'] ? 'text-yellow-400' : 'text-gray-200' ?>" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="text-[11px] text-gray-400 mt-0.5 block"><?= $r['rating'] ?>/5</span>
                                            </td>
                                            <td class="p-4 max-w-xs">
                                                <p class="text-sm text-gray-600 leading-relaxed line-clamp-2"><?= htmlspecialchars($r['review_text']) ?></p>
                                            </td>
                                            <td class="p-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-500"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                                                <div class="text-[11px] text-gray-400"><?= date('g:i A', strtotime($r['created_at'])) ?></div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="no-results <?= empty($reviews) ? '' : 'hidden' ?>">
                                        <td colspan="6" class="p-6 text-center text-gray-400 text-sm">No reviews matching your search.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-3 text-sm text-gray-500 border-t border-gray-100">
                            Total: <span class="font-semibold text-gray-700" id="totalCount"><?= $rTotal ?></span> reviews
                        </div>

                        <?php if ($rTotalPages > 1): ?>
                        <div id="pagination" class="flex justify-center items-center gap-2 px-6 py-4 border-t border-gray-100">
                            <?php $rQueryStr = $search !== '' ? '&search=' . urlencode($search) : ''; ?>
                            <a href="?r_page=1<?= $rQueryStr ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $rPage <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                <i class="fa-solid fa-angles-left mr-1"></i> First
                            </a>
                            <a href="?r_page=<?= max(1, $rPage-1) ?><?= $rQueryStr ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $rPage <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                            <span class="text-xs text-gray-500 font-medium">Page <?= $rPage ?> of <?= $rTotalPages ?></span>
                            <a href="?r_page=<?= min($rTotalPages, $rPage+1) ?><?= $rQueryStr ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $rPage >= $rTotalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                            <a href="?r_page=<?= $rTotalPages ?><?= $rQueryStr ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $rPage >= $rTotalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                Last <i class="fa-solid fa-angles-right ml-1"></i>
                            </a>
                            <form method="GET" class="flex items-center gap-1 ml-2">
                                <label class="text-xs text-gray-500 font-medium">Page:</label>
                                <input type="number" name="r_page" min="1" max="<?= $rTotalPages ?>" value="<?= $rPage ?>"
                                    class="w-14 px-2 py-1 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-purple-500">
                                <?php if ($search !== ''): ?>
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

<script>
    (function () {
        const form = document.getElementById('searchForm');
        const input = document.getElementById('reviewSearch');
        const totalEl = document.getElementById('totalCount');
        const totalBadge = document.getElementById('totalBadge');
        const pagination = document.getElementById('pagination');
        if (!form || !input) return;

        let timer;

        function doSearch() {
            const q = input.value.trim();
            const tbody = document.querySelector('table tbody');

            fetch('reviews.php' + (q ? '?search=' + encodeURIComponent(q) : ''))
                .then(r => r.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const nt = doc.querySelector('table tbody');
                    const ntc = doc.getElementById('totalCount');
                    const np = doc.getElementById('pagination');
                    const tbody2 = document.querySelector('table tbody');
                    if (nt && tbody2) tbody2.innerHTML = nt.innerHTML;
                    if (ntc && totalEl) totalEl.textContent = ntc.textContent;
                    if (ntc && totalBadge) totalBadge.textContent = ntc.textContent + ' total';
                    if (np) { if (pagination) { pagination.innerHTML = np.innerHTML; pagination.style.display = ''; } }
                    else if (pagination) pagination.style.display = 'none';
                })
                .catch(() => {});
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(doSearch, 400);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            doSearch();
        });
    })();
</script>
</body>
</html>
