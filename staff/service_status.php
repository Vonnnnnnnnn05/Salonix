<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit;
}

$staffId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$statusQuery = mysqli_query(
    $conn,
    "SELECT a.status, COUNT(*) AS total
     FROM appointments a
     " . ($staffId > 0 ? "WHERE a.staff_id = {$staffId}" : "") . "
     GROUP BY a.status
     ORDER BY total DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Status | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/staffsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Service Status</h1>
            <p class="mt-1 text-sm text-gray-500">Status distribution of assigned appointments.</p>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Status</th>
                            <th class="rounded-r-xl px-4 py-3">Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($statusQuery && mysqli_num_rows($statusQuery) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($statusQuery)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars(str_replace("_", " ", $row['status'])); ?></td>
                                <td class="px-4 py-3"><?php echo (int) $row['total']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="px-4 py-6 text-center text-gray-500">No status data found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>

