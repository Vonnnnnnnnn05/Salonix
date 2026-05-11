<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$statusReport = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM appointments GROUP BY status");
$revenueReport = mysqli_query(
    $conn,
    "SELECT SUM(s.price) AS total_revenue
     FROM appointments a
     INNER JOIN services s ON a.service_id = s.service_id
     WHERE a.status = 'completed'"
);
$revenue = 0;
if ($revenueReport) {
    $revRow = mysqli_fetch_assoc($revenueReport);
    $revenue = isset($revRow['total_revenue']) ? (float) $revRow['total_revenue'] : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/adminsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Reports</h1>
            <p class="mt-1 text-sm text-gray-500">Operational summary and appointment insights.</p>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2">
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
                <p class="text-sm text-gray-500">Completed Service Revenue</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]">PHP <?php echo number_format($revenue, 2); ?></p>
            </article>
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
                <p class="text-sm text-gray-500">Report Coverage</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]">Appointments</p>
                <p class="mt-1 text-sm text-gray-500">Grouped by workflow status.</p>
            </article>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h2 class="text-lg font-semibold">Appointment Status Breakdown</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Status</th>
                            <th class="rounded-r-xl px-4 py-3">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($statusReport && mysqli_num_rows($statusReport) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($statusReport)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars(str_replace("_", " ", $row['status'])); ?></td>
                                <td class="px-4 py-3"><?php echo (int) $row['total']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="px-4 py-6 text-center text-gray-500">No report data available.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>

