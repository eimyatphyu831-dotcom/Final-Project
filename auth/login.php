<?php
session_start();
include "../config/db.php";

$message = "";
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
if ($redirect && strpos($redirect, 'http') !== 0 && strpos($redirect, '/') !== 0) {
    $redirect = '../users/' . ltrim($redirect, '/');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];
    $redirect = $_POST['redirect'] ?? '';
    if ($redirect && strpos($redirect, 'http') !== 0 && strpos($redirect, '/') !== 0) {
        $redirect = '../users/' . ltrim($redirect, '/');
    }

    // Check admins table first
    $stmt = $conn->prepare("SELECT id, name, email, password FROM admins WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();

        if (password_verify($password, $admin['password'])) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_name'] = $admin['name'];
            $_SESSION['user_email'] = $admin['email'];
            $_SESSION['user_role'] = 'admin';

            $_SESSION['success'] = "Welcome Admin!";
            header("Location: ../admin/dashboard.php");
            exit();
        } else {
            $message = "Invalid email or password!";
        }
    } else {
        // Fall back to users table
        $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = 'user';

                $_SESSION['success'] = "Login Successful!";
                header("Location: " . ($redirect ?: '../users/index.php'));
                exit();
            } else {
                $message = "Invalid email or password!";
            }
        } else {
            $message = "Invalid email or password!";
        }
    }
}
?>

<?php if (isset($success)): ?>

    <script>
        Swal.fire({
            icon: 'success',
            title: '<?= $success ?>',
            text: 'Welcome to Event Planning System',
            confirmButtonColor: '#9d84c7',
            timer: 2000,
            showConfirmButton: false
        });
    </script>

<?php endif; ?>

<?php include '../includes/header.php'; ?>

<style>
    .pm-card {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid transparent;
    }

    .pm-card:hover {
        border-color: #c4b5fd;
        transform: translateY(-2px);
    }

    .pm-card.selected {
        border-color: #7c3aed;
        background: #f5f3ff;
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
    }
</style>

<div class="flex-grow w-full flex items-center justify-center p-4">

    <div
        class="w-full max-w-2xl bg-white rounded-2xl overflow-hidden shadow-lg border border-slate-200/60 flex flex-col md:flex-row min-h-[440px] md:min-h-[420px]">

        <div class="w-full md:w-2/5 relative bg-purple-950 min-h-[140px] md:min-h-full">
            <img src="../assets/images/login.png" alt="Luxury Event Greenhouse Setting"
                class="absolute inset-0 w-full h-full object-cover object-center mix-blend-normal">
        </div>

        <div class="w-full md:w-3/5 bg-white flex flex-col justify-center p-5 sm:p-6 md:p-8">
            <div class="w-full max-w-[260px] mx-auto flex flex-col justify-center">


                <?php if (!empty($message)): ?>
                    <p style="color:red; font-size:11px; text-align:center; margin-bottom:8px;">
                        <?= $message ?>
                    </p>
                <?php endif; ?>
                <div class="flex flex-col items-center mb-4 text-center">
                    <div class="w-6 h-6 rounded bg-brand-200 flex items-center justify-center">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5 text-white font-bold"></i>
                    </div>
                    <!-- <span class="text-md font-bold tracking-tight text-purple-400">EventPro</span> -->
                    <h1 class="text-lg font-bold text-slate-900 tracking-tight mt-1">Welcome Back</h1>
                    <p class="text-[11px] text-slate-500 mt-0.5">Access your event dashboard</p>
                </div>

                <form action="#" method="POST" class="space-y-3">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                    <div>
                        <label for="email"
                            class="block text-[10px] font-semibold text-slate-700 tracking-wide mb-0.5">Email
                            Address</label>
                        <div class="relative">
                            <div
                                class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <input type="email" id="email" name="email" required autocomplete="email"
                                placeholder="alex@company.com" required
                                class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-600 transition-all">
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-0.5">
                            <label for="password"
                                class="block text-[10px] font-semibold text-slate-700 tracking-wide">Password</label>
                            <a href="#"
                                class="text-[10px] font-medium text-slate-400 hover:text-purple-600 transition-colors">Forgot?</a>
                        </div>
                        <div class="relative">
                            <div
                                class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input type="password" id="password" name="password" required
                                autocomplete="current-password" placeholder="••••••••"
                                class="w-full pl-8 pr-8 py-1.5 bg-white border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-600 transition-all">

                            <button type="button" id="toggle-mask-btn" tabindex="-1"
                                class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center pt-0.5">
                        <input type="checkbox" id="remember_me" name="remember_me" required
                            class="h-3 w-3 rounded border-slate-300 text-purple-600 focus:ring-purple-500/20 accent-purple-600 cursor-pointer">
                        <label for="remember_me"
                            class="ml-1.5 text-[10px] text-slate-500 select-none cursor-pointer leading-none">Remember
                            for 30 days</label>
                    </div>

                    <button type="submit"
                        class="w-full mt-1 py-1.5 px-4 bg-purple-200 hover:bg-purple-300 text-purple-950 font-semibold text-xs rounded-lg tracking-wide shadow-sm transition-all flex items-center justify-center gap-1 group">
                        Sign In
                        <svg class="w-3.5 h-3.5 transform group-hover:translate-x-0.5 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </form>

                <p class="mt-4 text-center text-[11px] text-slate-500">
                    Don't have an account?
                    <a href="../auth/register.php"
                        class="font-bold text-slate-900 hover:text-purple-700 hover:underline transition-all">Create an
                        account</a>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- <footer class="w-full py-2.5 text-center">
        <p class="text-[10px] text-slate-400 tracking-wide font-normal">
            &copy; 2026 EventPlanning. All rights reserved.
        </p>
    </footer> -->
<?php
include '../includes/footer.php';
?>

<script>
    document.getElementById('toggle-mask-btn')?.addEventListener('click', function () {
        const field = document.getElementById('password');
        if (field) {
            field.type = field.type === 'password' ? 'text' : 'password';
            field.focus();
        }
    });
</script>