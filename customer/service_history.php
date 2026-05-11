<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

$customerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$history = mysqli_query(
    $conn,
    "SELECT a.appointment_date, a.status, s.service_name, s.price
     FROM appointments a
     INNER JOIN services s ON a.service_id = s.service_id
     WHERE a.status = 'completed' " . ($customerId > 0 ? "AND a.customer_id = {$customerId}" : "") . "
     ORDER BY a.appointment_date DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service History | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/customersidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Service History</h1>
            <p class="mt-1 text-sm text-gray-500">Completed services and billing overview.</p>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Service</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="rounded-r-xl px-4 py-3">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($history && mysqli_num_rows($history) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($history)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['service_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['appointment_date']); ?></td>
                                <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars($row['status']); ?></td>
                                <td class="px-4 py-3 font-medium text-[#B76E79]">PHP <?php echo number_format((float) $row['price'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No completed services found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>

