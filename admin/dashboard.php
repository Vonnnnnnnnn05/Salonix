<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

function count_rows($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_row($result);
    return isset($row[0]) ? (int) $row[0] : 0;
}

$appointmentCount = count_rows($conn, "SELECT COUNT(*) FROM appointments");
$customerCount = count_rows($conn, "SELECT COUNT(*) FROM users WHERE role = 'customer'");
$staffCount = count_rows($conn, "SELECT COUNT(*) FROM users WHERE role = 'staff'");
$serviceCount = count_rows($conn, "SELECT COUNT(*) FROM services");

$todayAppointments = mysqli_query(
    $conn,
    "SELECT a.appointment_date, a.start_time, a.status, u.full_name, s.service_name
     FROM appointments a
     INNER JOIN users u ON a.customer_id = u.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     ORDER BY a.appointment_date DESC, a.start_time DESC
     LIMIT 8"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/adminsidebar.php"; ?>

    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="mb-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Dashboard Overview</h1>
            <p class="mt-1 text-sm text-gray-500">Monitor daily operations and salon performance.</p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                <p class="text-sm text-gray-500">Total Appointments</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]"><?php echo $appointmentCount; ?></p>
            </article>
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                <p class="text-sm text-gray-500">Customers</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]"><?php echo $customerCount; ?></p>
            </article>
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                <p class="text-sm text-gray-500">Staff</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]"><?php echo $staffCount; ?></p>
            </article>
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                <p class="text-sm text-gray-500">Services</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]"><?php echo $serviceCount; ?></p>
            </article>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h2 class="text-lg font-semibold">Latest Appointments</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5] text-[#2D2D2D]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3 font-semibold">Customer</th>
                            <th class="px-4 py-3 font-semibold">Service</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="rounded-r-xl px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($todayAppointments && mysqli_num_rows($todayAppointments) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($todayAppointments)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['service_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['appointment_date'] . " " . date("g:i A", strtotime($row['start_time']))); ?></td>
                                <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars(str_replace("_", " ", $row['status'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No appointments found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
