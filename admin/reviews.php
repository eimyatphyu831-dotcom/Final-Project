<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

// Fetch all reviews
$reviews = [];
$query = "SELECT r.id, r.rating, r.review_text, r.created_at,
                 u.name AS user_name, u.email AS user_email, u.image AS user_image,
                 e.event_name, b.event_date
          FROM reviews r
          JOIN users u ON r.user_id = u.id
          JOIN events e ON r.event_id = e.id
          JOIN bookings b ON r.booking_id = b.id
          ORDER BY r.created_at DESC";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $reviews = $result->fetch_all(MYSQLI_ASSOC);
}

// Pagination
$rPage = isset($_GET['r_page']) ? max(1, (int)$_GET['r_page']) : 1;
$rPerPage = 8;
$rTotal = count($reviews);
$rTotalPages = ceil($rTotal / $rPerPage);
$rOffset = ($rPage - 1) * $rPerPage;
$paginatedReviews = array_slice($reviews, $rOffset, $rPerPage);

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
        <div class="flex-1 flex flex-col lg:ml-64">
            <?php include 'admin_header.php'; ?>
            <main class="flex-1 p-6 overflow-y-auto custom-scroll">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="reviewSearch" placeholder="Search reviews..."
                            class="w-72 pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-400 bg-white">
                    </div>
                    <span class="bg-purple-100 text-purple-700 px-3 py-1.5 rounded-full text-xs font-semibold"><?= count($reviews) ?> total</span>
                </div>

                <?php if (count($reviews) === 0): ?>
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
                                    <tr class="no-results hidden">
                                        <td colspan="6" class="p-6 text-center text-gray-400 text-sm">No reviews matching your search.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-3 text-sm text-gray-500 border-t border-gray-100">
                            Total: <span class="font-semibold text-gray-700"><?= $rTotal ?></span> reviews
                        </div>

                        <?php if ($rTotalPages > 1): ?>
                        <div class="flex justify-center items-center gap-2 px-6 py-4 border-t border-gray-100">
                            <span class="text-xs text-gray-500 font-medium mr-2">Page: <?= $rPage ?> of <?= $rTotalPages ?></span>
                            <a href="?r_page=<?= max(1, $rPage-1) ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $rPage <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                            </a>
                            <?php for ($i = 1; $i <= $rTotalPages; $i++): ?>
                            <a href="?r_page=<?= $i ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $i == $rPage ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            <a href="?r_page=<?= min($rTotalPages, $rPage+1) ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg <?= $rPage >= $rTotalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                Next <i class="fa-solid fa-chevron-right ml-1"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <script>
        document.getElementById('reviewSearch').addEventListener('input', function () {
            const q = this.value.toLowerCase();
            let visible = 0;
            document.querySelectorAll('table tbody tr').forEach(row => {
                if (row.classList.contains('no-results')) return;
                const match = row.textContent.toLowerCase().includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.querySelector('.no-results')?.classList.toggle('hidden', visible > 0);
        });
    </script>
</body>
</html>
