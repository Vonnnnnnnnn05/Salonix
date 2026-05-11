<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$adminProfile = null;
if (isset($_SESSION['user_id'])) {
    $adminId = (int) $_SESSION['user_id'];
    $profileQuery = mysqli_query($conn, "SELECT full_name, username, email, contact_number FROM users WHERE user_id = {$adminId} LIMIT 1");
    if ($profileQuery && mysqli_num_rows($profileQuery) === 1) {
        $adminProfile = mysqli_fetch_assoc($profileQuery);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/adminsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Settings</h1>
            <p class="mt-1 text-sm text-gray-500">Account and system preference overview.</p>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="grid gap-4 sm:grid-cols-2">
                <article class="rounded-xl border border-[#E5E7EB] bg-[#FDF4F5] p-4">
                    <p class="text-xs font-semibold uppercase text-gray-500">Full Name</p>
                    <p class="mt-1 font-medium"><?php echo htmlspecialchars($adminProfile['full_name'] ?? 'Administrator'); ?></p>
                </article>
                <article class="rounded-xl border border-[#E5E7EB] bg-[#FDF4F5] p-4">
                    <p class="text-xs font-semibold uppercase text-gray-500">Username</p>
                    <p class="mt-1 font-medium"><?php echo htmlspecialchars($adminProfile['username'] ?? 'admin'); ?></p>
                </article>
                <article class="rounded-xl border border-[#E5E7EB] bg-[#FDF4F5] p-4">
                    <p class="text-xs font-semibold uppercase text-gray-500">Email</p>
                    <p class="mt-1 font-medium"><?php echo htmlspecialchars($adminProfile['email'] ?? '-'); ?></p>
                </article>
                <article class="rounded-xl border border-[#E5E7EB] bg-[#FDF4F5] p-4">
                    <p class="text-xs font-semibold uppercase text-gray-500">Contact Number</p>
                    <p class="mt-1 font-medium"><?php echo htmlspecialchars($adminProfile['contact_number'] ?? '-'); ?></p>
                </article>
            </div>
        </section>
    </main>
</body>
</html>

