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
$username = "";
$successMessage = isset($_GET["registered"]) ? "Account created successfully. You can now log in." : "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedToken = $_POST["csrf_token"] ?? "";
    if (!hash_equals($_SESSION["csrf_token"], $postedToken)) {
        $errors[] = "Invalid request. Please refresh and try again.";
    }

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $errors[] = "Username and password are required.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, username, password, role FROM users WHERE username = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user["password"])) {
                $_SESSION["user_id"] = (int) $user["user_id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];
                redirect_by_role($user["role"]);
            } else {
                $errors[] = "Invalid username or password.";
            }
        } else {
            $errors[] = "Login failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SALONIX</title>
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
                        <i class="fa-solid fa-spa"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold">SALONIX</h1>
                        <p class="text-sm text-gray-500">Salon Management System</p>
                    </div>
                </div>
                <h2 class="mt-12 text-3xl font-semibold leading-tight">Welcome back to your salon workspace.</h2>
                <p class="mt-4 text-sm text-gray-600">Manage appointments, services, and client operations with a clean professional dashboard.</p>
            </article>

            <article class="p-6 sm:p-10">
                <div class="mb-8">
                    <h2 class="text-2xl font-semibold">Login</h2>
                    <p class="mt-1 text-sm text-gray-500">Sign in to continue to your account.</p>
                </div>

                <?php if ($successMessage !== ""): ?>
                    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

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
                        <label for="username" class="mb-2 block text-sm font-medium">Username</label>
                        <div class="flex items-center rounded-xl border border-[#E5E7EB] px-3 focus-within:border-[#B76E79] focus-within:ring-2 focus-within:ring-[#F5D0D7]">
                            <i class="fa-solid fa-user text-gray-400"></i>
                            <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($username); ?>" class="w-full rounded-xl border-0 px-3 py-3 text-sm focus:outline-none" placeholder="Enter username" required>
                        </div>
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium">Password</label>
                        <div class="flex items-center rounded-xl border border-[#E5E7EB] px-3 focus-within:border-[#B76E79] focus-within:ring-2 focus-within:ring-[#F5D0D7]">
                            <i class="fa-solid fa-lock text-gray-400"></i>
                            <input id="password" name="password" type="password" class="w-full rounded-xl border-0 px-3 py-3 text-sm focus:outline-none" placeholder="Enter password" required>
                        </div>
                    </div>

                    <button type="submit" class="mt-2 inline-flex w-full items-center justify-center rounded-xl bg-[#B76E79] px-4 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-[#a9606b]">
                        <i class="fa-solid fa-right-to-bracket mr-2"></i>
                        Login
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-gray-600">
                    No account yet?
                    <a href="register.php" class="font-medium text-[#B76E79] hover:underline">Create one</a>
                </p>
            </article>
        </section>
    </main>
</body>
</html>
