<?php
include "config/session.php";

function redirect_by_role($role) {
    if ($role === "admin") {
        header("Location: admin/dashboard.php");
        exit;
    }
    if ($role === "staff") {
        header("Location: staff/dashboard.php");
        exit;
    }
    header("Location: customer/dashboard.php");
    exit;
}

if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    redirect_by_role($_SESSION["role"]);
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$errors = [];
$form = [
    "full_name" => "",
    "username" => "",
    "email" => "",
    "contact_number" => ""
];
$defaultRole = "customer";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedToken = $_POST["csrf_token"] ?? "";
    if (!hash_equals($_SESSION["csrf_token"], $postedToken)) {
        $errors[] = "Invalid request. Please refresh and try again.";
    }

    $form["full_name"] = trim($_POST["full_name"] ?? "");
    $form["username"] = trim($_POST["username"] ?? "");
    $form["email"] = trim($_POST["email"] ?? "");
    $form["contact_number"] = trim($_POST["contact_number"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($form["full_name"] === "" || $form["username"] === "" || $password === "") {
        $errors[] = "Full name, username, and password are required.";
    }

    if ($form["email"] !== "" && !filter_var($form["email"], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Password confirmation does not match.";
    }

    if (empty($errors)) {
        $checkStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? LIMIT 1");
        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, "s", $form["username"]);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                $errors[] = "Username is already taken.";
            }
            mysqli_stmt_close($checkStmt);
        } else {
            $errors[] = "Unable to validate username at this time.";
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $insertStmt = mysqli_prepare(
            $conn,
            "INSERT INTO users (full_name, username, password, role, contact_number, email) VALUES (?, ?, ?, ?, ?, ?)"
        );

        if ($insertStmt) {
            mysqli_stmt_bind_param(
                $insertStmt,
                "ssssss",
                $form["full_name"],
                $form["username"],
                $passwordHash,
                $defaultRole,
                $form["contact_number"],
                $form["email"]
            );
            $inserted = mysqli_stmt_execute($insertStmt);
            mysqli_stmt_close($insertStmt);

            if ($inserted) {
                header("Location: login.php?registered=1");
                exit;
            }
            $errors[] = "Registration failed. Please try again.";
        } else {
            $errors[] = "Unable to create account at this time.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="min-h-screen bg-[#F9FAFB] text-[#2D2D2D]">
    <main class="mx-auto flex min-h-screen max-w-6xl items-center px-4 py-10 sm:px-6 lg:px-8">
        <section class="grid w-full overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-lg lg:grid-cols-2">
            <article class="hidden bg-[#FDF4F5] p-10 lg:block">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-[#B76E79] shadow-sm">
                        <i class="fa-solid fa-scissors"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold">SALONIX</h1>
                        <p class="text-sm text-gray-500">Salon Appointment System</p>
                    </div>
                </div>
                <h2 class="mt-12 text-3xl font-semibold leading-tight">Create your account and start managing appointments.</h2>
                <p class="mt-4 text-sm text-gray-600">Elegant, organized, and modern salon operations for customers.</p>
            </article>

            <article class="p-6 sm:p-10">
                <div class="mb-8">
                    <h2 class="text-2xl font-semibold">Register</h2>
                    <p class="mt-1 text-sm text-gray-500">Set up your SALONIX account.</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                    <div>
                        <label for="full_name" class="mb-2 block text-sm font-medium">Full Name</label>
                        <input id="full_name" name="full_name" type="text" value="<?php echo htmlspecialchars($form["full_name"]); ?>" class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="Enter your full name" required>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="username" class="mb-2 block text-sm font-medium">Username</label>
                            <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($form["username"]); ?>" class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="Choose username" required>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium">Default Role</label>
                            <div class="w-full rounded-xl border border-[#E5E7EB] bg-[#FDF4F5] px-4 py-3 text-sm font-medium text-[#B76E79]">
                                Customer
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium">Email (Optional)</label>
                            <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($form["email"]); ?>" class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="name@example.com">
                        </div>
                        <div>
                            <label for="contact_number" class="mb-2 block text-sm font-medium">Contact Number (Optional)</label>
                            <input id="contact_number" name="contact_number" type="text" value="<?php echo htmlspecialchars($form["contact_number"]); ?>" class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="09XXXXXXXXX">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium">Password</label>
                            <input id="password" name="password" type="password" class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="At least 8 characters" required>
                        </div>
                        <div>
                            <label for="confirm_password" class="mb-2 block text-sm font-medium">Confirm Password</label>
                            <input id="confirm_password" name="confirm_password" type="password" class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="Re-enter password" required>
                        </div>
                    </div>

                    <button type="submit" class="mt-2 inline-flex w-full items-center justify-center rounded-xl bg-[#B76E79] px-4 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-[#a9606b]">
                        <i class="fa-solid fa-user-plus mr-2"></i>
                        Create Account
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-gray-600">
                    Already have an account?
                    <a href="login.php" class="font-medium text-[#B76E79] hover:underline">Login here</a>
                </p>
            </article>
        </section>
    </main>
</body>
</html>
