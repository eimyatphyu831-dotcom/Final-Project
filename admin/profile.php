<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$message = '';
$error = '';

$stmt = $conn->prepare("SELECT id, name, email, profile_image FROM admins WHERE id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];

        if (empty($name) || empty($email)) {
            $error = "Name and email are required.";
        } elseif (!empty($password) && $password !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admins SET name=?, email=?, password=? WHERE id=?");
                $stmt->bind_param("sssi", $name, $email, $hashed, $_SESSION['user_id']);
            } else {
                $stmt = $conn->prepare("UPDATE admins SET name=?, email=? WHERE id=?");
                $stmt->bind_param("ssi", $name, $email, $_SESSION['user_id']);
            }

            if ($stmt->execute()) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $admin['name'] = $name;
                $admin['email'] = $email;
                $message = "Profile updated successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if (isset($_POST['upload_image'])) {
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = "Only JPG, PNG, GIF, WEBP files are allowed.";
            } else {
                $filename = 'admin_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
                $target = __DIR__ . '/uploads/profile/' . $filename;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
                    if ($admin['profile_image'] && file_exists(__DIR__ . '/uploads/profile/' . $admin['profile_image'])) {
                        unlink(__DIR__ . '/uploads/profile/' . $admin['profile_image']);
                    }

                    $stmt = $conn->prepare("UPDATE admins SET profile_image=? WHERE id=?");
                    $stmt->bind_param("si", $filename, $_SESSION['user_id']);
                    $stmt->execute();
                    $stmt->close();

                    $admin['profile_image'] = $filename;
                    $message = "Profile image updated successfully!";
                } else {
                    $error = "Failed to upload image.";
                }
            }
        } else {
            $error = "Please select an image to upload.";
        }
    }

    if (isset($_POST['remove_image'])) {
        if ($admin['profile_image'] && file_exists(__DIR__ . '/uploads/profile/' . $admin['profile_image'])) {
            unlink(__DIR__ . '/uploads/profile/' . $admin['profile_image']);
        }
        $stmt = $conn->prepare("UPDATE admins SET profile_image=NULL WHERE id=?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        $admin['profile_image'] = null;
        $message = "Profile image removed.";
    }
}

$avatar = $admin['profile_image']
    ? 'uploads/profile/' . $admin['profile_image']
    : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Playfair Display', serif;
        }

        .bg-sidebar {
            background-color: #ffffff;
        }

        .bg-sidebar-active {
            background-color: #C3B1E1;
            color: #ffffff;
        }

        .text-purple-brand {
            color: #9966cc;
        }

        .bg-purple-brand {
            background-color: #C3B1E1;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen overflow-hidden">

    <div class="flex h-screen">

        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col ml-64">

            <?php include 'admin_header.php'; ?>

            <main class="flex-1 p-8 overflow-y-auto">

                <!-- <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">My Profile</h2>
                    <p class="text-sm text-gray-500 mt-1">Manage your admin account and profile image</p>
                </div> -->

                <?php if ($message): ?>
                    <div class="bg-green-100 text-green-700 px-4 py-3 rounded-xl text-sm mb-4 border border-green-200">
                        <?= $message ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-100 text-red-700 px-4 py-3 rounded-xl text-sm mb-4 border border-red-200">
                        <?= $error ?></div>
                <?php endif; ?>

                <div class="max-w-md mx-auto">

                    <!-- Combined Profile Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-4 flex flex-col items-center text-center border-b border-gray-100">
                            <div class="relative group mb-2">
                                <div
                                    class="w-14 h-14 rounded-full overflow-hidden border-4 border-gray-100 <?= $avatar ? '' : 'bg-purple-100 flex items-center justify-center' ?>">
                                    <?php if ($avatar): ?>
                                        <img src="<?= $avatar ?>?t=<?= time() ?>" alt="Profile"
                                            class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fa-solid fa-user text-purple-500 text-lg"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex flex-col items-center gap-1.5">
                                <form method="POST" enctype="multipart/form-data" id="imageForm">
                                    <label
                                        class="px-3 py-1.5 bg-purple-600 text-white rounded-xl text-xs hover:bg-purple-700 cursor-pointer transition-all inline-block">
                                        <i class="fa-solid fa-camera mr-1"></i> Choose Image
                                        <input type="file" name="profile_image" accept="image/*" class="hidden"
                                            onchange="document.getElementById('imageForm').submit()">
                                    </label>
                                    <input type="hidden" name="upload_image" value="1">
                                </form>
                                <?php if ($avatar): ?>
                                    <form method="POST" onsubmit="return confirm('Remove profile image?')">
                                        <button type="submit" name="remove_image" value="1"
                                            class="text-xs text-red-500 hover:text-red-700 transition-all">
                                            <i class="fa-solid fa-trash-can mr-1"></i> Remove Image
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <p class="text-[10px] text-gray-400">JPG, PNG, GIF, WEBP. Max 2MB.</p>
                            </div>
                        </div>

                        <div class="p-4">
                            <h3 class="text-sm font-semibold text-gray-800 mb-1">Account Details</h3>
                            <form method="POST" class="space-y-1.5">
                                <div>
                                    <label class="block text-[10px] font-medium text-gray-700">Full Name</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($admin['name']) ?>"
                                        required
                                        class="w-full px-2.5 py-1 border border-gray-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-medium text-gray-700">Email Address</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>"
                                        required
                                        class="w-full px-2.5 py-1 border border-gray-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-400">
                                </div>
                                <!-- <hr class="border-gray-200 my-0.5"> -->
                                <div>
                                    <label class="block text-[10px] font-medium text-gray-700">New Password <span
                                            class="text-gray-400 font-normal">(leave blank to keep
                                            current)</span></label>
                                    <input type="password" name="password" placeholder="••••••••"
                                        class="w-full px-2.5 py-1 border border-gray-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-medium text-gray-700">Confirm New
                                        Password</label>
                                    <input type="password" name="confirm_password" placeholder="••••••••"
                                        class="w-full px-2.5 py-1 border border-gray-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-400">
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" name="update_profile" value="1"
                                        class="px-3 py-1 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition-all text-xs font-medium">Save
                                        Changes</button>
                                    <a href="dashboard.php"
                                        class="px-3 py-1 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-all text-xs font-medium">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>

            </main>

        </div>

    </div>

</body>

</html>