<?php
include "../config/db.php";

$message = "";
$name = $email = $phone = "";
$errors = ['name' => '', 'email' => '', 'phone' => '', 'password' => ''];

$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
if ($redirect && strpos($redirect, 'http') !== 0 && strpos($redirect, '/') !== 0) {
    $redirect = '../users/' . ltrim($redirect, '/');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retain inputs immediately to keep them in the form on error
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $registration_reason = trim($_POST['registration_reason'] ?? '');
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm-password'];
    $redirect = $_POST['redirect'] ?? '';

    if ($redirect && strpos($redirect, 'http') !== 0 && strpos($redirect, '/') !== 0) {
        $redirect = '../users/' . ltrim($redirect, '/');
    }

    // 1. Validate Name
    if (empty($name)) {
        $errors['name'] = "Full Name is required.";
    } elseif (!preg_match("/^[a-zA-Z-' ]*$/", $name)) {
        $errors['name'] = "Only letters and white space allowed.";
    }

    // 2. Validate Email (Must include @)
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strpos($email, '@') === false) {
        $errors['email'] = "Email must include '@' and be valid (e.g., su@gmail.com).";
    }

    // 3. Validate Phone (Max 11 digits)
    $digits_only = preg_replace('/\D/', '', $phone); // Extract only digits
    if (empty($phone)) {
        $errors['phone'] = "Phone Number is required.";
    } elseif (strlen($digits_only) > 11) {
        $errors['phone'] = "Phone number must be a maximum of 11 digits.";
    }

    // 4. Validate Passwords
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif ($password != $confirmPassword) {
        $errors['password'] = "Passwords do not match!";
    }

    if (!isset($_POST['terms'])) {
        $errors['terms'] = "You must agree to the Terms and Privacy Policy.";
    }

    // Proceed to DB ONLY if there are no validation errors
    if (empty($errors['name']) && empty($errors['email']) && empty($errors['phone']) && empty($errors['password'])) {
        $check = $conn->prepare("SELECT id FROM users WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $errors['email'] = "Email already registered!";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users(name,email,phone,password) VALUES(?,?,?,?)");
            $stmt->bind_param("ssss", $name, $email, $phone, $hashedPassword);

            if ($stmt->execute()) {
                header("Location: login.php" . ($redirect ? '?redirect=' . urlencode($redirect) : ''));
                exit();
            } else {
                $message = "Registration failed! Please try again.";
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="flex-grow w-full flex items-center justify-center p-2">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg border border-slate-200/60 p-2 sm:p-8">

        <div class="flex flex-col items-center mb-2 text-center">
            <div
                class="w-8 h-8 bg-purple-100 rounded-xl flex items-center justify-center text-purple-600 mb-1 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            </div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight mt-0.5">Get Started Now</h1>
            <p class="text-[11px] text-slate-500 mt-0.5">Create your account</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-3 p-2 rounded bg-red-100 text-red-600 text-xs">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form action="#" method="POST" class="space-y-3" novalidate>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>" required>

            <!-- FULL NAME FIELD -->
            <div>
                <label for="name" class="block text-[10px] font-semibold text-slate-700 tracking-wide mb-0.5">Full
                    Name</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <input type="text" id="name" name="name" required autocomplete="name" placeholder="Alex Morgan"
                        value="<?= htmlspecialchars($name) ?>"
                        class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-600 transition-all"
                        onkeydown="if(/[0-9]/.test(event.key) && event.key.length === 1) return false;"
                        oninput="this.value = this.value.replace(/[^a-zA-Z\s'-]/g, '')">
                </div>
                <?php if (!empty($errors['name'])): ?>
                    <span class="block text-red-500 text-[10px] mt-1 ml-1"><?= $errors['name'] ?></span>
                <?php endif; ?>
            </div>

            <!-- EMAIL FIELD -->
            <div>
                <label for="email" class="block text-[10px] font-semibold text-slate-700 tracking-wide mb-0.5">Email
                    Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <input type="email" id="email" name="email" required autocomplete="email"
                        placeholder="alex@gmail.com" value="<?= htmlspecialchars($email) ?>"
                        class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-600 transition-all">
                </div>
                <?php if (!empty($errors['email'])): ?>
                    <span class="block text-red-500 text-[10px] mt-1 ml-1"><?= $errors['email'] ?></span>
                <?php endif; ?>
            </div>

            <!-- PHONE NUMBER FIELD -->
            <div>
                <label for="phone" class="block text-[10px] font-semibold text-slate-700 tracking-wide mb-0.5">Phone
                    Number</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                    </div>
                    <!-- Added inputmode and pattern to force numeric keyboard on mobile -->
                    <input type="tel" id="phone" name="phone" required autocomplete="tel" placeholder="09..."
                        maxlength="11" inputmode="numeric" pattern="[0-9]*" value="<?= htmlspecialchars($phone) ?>"
                        class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-600 transition-all"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
                <?php if (!empty($errors['phone'])): ?>
                    <span class="block text-red-500 text-[10px] mt-1 ml-1"><?= $errors['phone'] ?></span>
                <?php endif; ?>
            </div>

            <!-- PASSWORD FIELD -->
            <div id="password-container">
                <label for="password"
                    class="block text-[10px] font-semibold text-slate-700 tracking-wide mb-0.5">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input type="password" id="password" name="password" required placeholder="••••••••"
                        class="w-full pl-8 pr-9 py-1.5 bg-white border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-600 transition-all">
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
                <?php if (!empty($errors['password'])): ?>
                    <span class="block text-red-500 text-[10px] mt-1 ml-1"><?= $errors['password'] ?></span>
                <?php endif; ?>
            </div>

            <div id="confirm-password-container">
                <label for="confirm-password"
                    class="block text-[10px] font-semibold text-slate-700 tracking-wide mb-0.5">Confirm Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <input type="password" id="confirm-password" name="confirm-password" required placeholder="••••••••"
                        class="w-full pl-8 pr-9 py-1.5 bg-white border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-600/20 focus:border-purple-600 transition-all">
                    <button type="button" id="toggle-confirm-mask-btn" tabindex="-1"
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

            <div class="flex items-start pt-0.5">
                <input type="checkbox" id="terms" name="terms" required
                    class="mt-0.5 h-3 w-3 rounded border-slate-300 text-purple-600 focus:ring-purple-500/20 accent-purple-600 cursor-pointer">
                <label for="terms" class="ml-1.5 text-[10px] text-slate-500 select-none cursor-pointer leading-tight">I
                    agree to the <a href="#" class="text-purple-600 hover:underline">Terms</a> and <a href="#"
                        class="text-purple-600 hover:underline">Privacy</a></label>
            </div>
           


            <button type="submit"
                class="w-full mt-1 py-1.5 px-4 bg-purple-200 hover:bg-purple-300 text-purple-950 font-semibold text-xs rounded-lg tracking-wide shadow-sm transition-all flex items-center justify-center gap-1 group">
                Register Account
                <svg class="w-3.5 h-3.5 transform group-hover:translate-x-0.5 transition-transform" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </button>
        </form>

        <p class="mt-3 text-center text-[11px] text-slate-500">
            Already have an account? <a href="../auth/login.php"
                class="font-bold text-slate-900 hover:text-purple-700 hover:underline transition-all" required>Sign in
                instead</a>
        </p>
    </div>
</div>

<script>
    // Toggle visibility logic
    document.getElementById('toggle-mask-btn')?.addEventListener('click', function () {
        const field = document.getElementById('password');
        field.type = field.type === 'password' ? 'text' : 'password';
    });

    document.getElementById('toggle-confirm-mask-btn')?.addEventListener('click', function () {
        const field = document.getElementById('confirm-password');
        field.type = field.type === 'password' ? 'text' : 'password';
    });

    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirm-password");

    // Containers for the output text
    let strengthBox = document.createElement("div");
    strengthBox.style.fontSize = "11px";
    strengthBox.style.marginTop = "2px";
    document.getElementById("password-container").appendChild(strengthBox);

    let matchBox = document.createElement("div");
    matchBox.style.fontSize = "11px";
    matchBox.style.marginTop = "2px";
    document.getElementById("confirm-password-container").appendChild(matchBox);

    function getStrength(value) {
        let len = value.length;
        if (len === 0) return "";
        if (len < 3) return "Weak";
        if (len <= 5) return "Fair";
        if (len <= 7) return "Good";
        return "Excellent";
    }

    password.addEventListener("input", function () {
        let result = getStrength(password.value);
        strengthBox.textContent = result;
        strengthBox.style.color = result === "Weak" ? "red" : result === "Fair" ? "orange" : result === "Good" ? "blue" : result === "Excellent" ? "green" : "";
        checkMatch();
    });

    confirmPassword.addEventListener("input", checkMatch);

    function checkMatch() {
        if (confirmPassword.value.length === 0) {
            matchBox.textContent = "";
            return;
        }
        if (password.value === confirmPassword.value) {
            matchBox.textContent = "Match";
            matchBox.style.color = "green";
        } else {
            matchBox.textContent = "Not Match";
            matchBox.style.color = "red";
        }
    }
</script>

<?php include '../includes/footer.php'; ?>