<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

$message = "";
$error = "";
$customerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $error = "Invalid request token.";
    } else {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $appointmentDate = $_POST['appointment_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');

        if ($customerId <= 0 || $serviceId <= 0 || $staffId <= 0 || $appointmentDate === '' || $startTime === '') {
            $error = "Please complete all required booking fields.";
        } else {
            $durationStmt = mysqli_prepare($conn, "SELECT duration_minutes FROM services WHERE service_id = ? LIMIT 1");
            if ($durationStmt) {
                mysqli_stmt_bind_param($durationStmt, "i", $serviceId);
                mysqli_stmt_execute($durationStmt);
                $durationResult = mysqli_stmt_get_result($durationStmt);
                $durationRow = $durationResult ? mysqli_fetch_assoc($durationResult) : null;
                mysqli_stmt_close($durationStmt);
                $durationMinutes = $durationRow ? (int) $durationRow['duration_minutes'] : 0;

                if ($durationMinutes <= 0) {
                    $error = "Selected service is invalid.";
                } else {
                    $endTime = date('H:i:s', strtotime($startTime . " +{$durationMinutes} minutes"));
                    $insertStmt = mysqli_prepare($conn, "INSERT INTO appointments (customer_id, staff_id, service_id, appointment_date, start_time, end_time, status, remarks) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
                    if ($insertStmt) {
                        mysqli_stmt_bind_param($insertStmt, "iiissss", $customerId, $staffId, $serviceId, $appointmentDate, $startTime, $endTime, $remarks);
                        if (mysqli_stmt_execute($insertStmt)) {
                            $message = "Appointment booked successfully.";
                        } else {
                            $error = "Failed to book appointment.";
                        }
                        mysqli_stmt_close($insertStmt);
                    } else {
                        $error = "Failed to prepare booking request.";
                    }
                }
            } else {
                $error = "Failed to prepare service lookup.";
            }
        }
    }
}

$services = mysqli_query($conn, "SELECT service_id, service_name, duration_minutes, price, description FROM services ORDER BY service_name ASC");
$staffMembers = mysqli_query($conn, "SELECT user_id, full_name FROM users WHERE role = 'staff' ORDER BY full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/customersidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Book Appointment</h1>
            <p class="mt-1 text-sm text-gray-500">Choose your preferred salon service and schedule.</p>
        </section>

        <?php if ($message !== ''): ?>
            <div class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h2 class="text-lg font-semibold">Create Booking</h2>
            <form method="POST" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div>
                    <label class="mb-2 block text-sm font-medium">Service</label>
                    <select name="service_id" required class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]">
                        <option value="">Select service</option>
                        <?php if ($services): mysqli_data_seek($services, 0); while ($service = mysqli_fetch_assoc($services)): ?>
                            <option value="<?php echo (int) $service['service_id']; ?>"><?php echo htmlspecialchars($service['service_name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Staff</label>
                    <select name="staff_id" required class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]">
                        <option value="">Select staff</option>
                        <?php if ($staffMembers): while ($staff = mysqli_fetch_assoc($staffMembers)): ?>
                            <option value="<?php echo (int) $staff['user_id']; ?>"><?php echo htmlspecialchars($staff['full_name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Date</label>
                    <input name="appointment_date" type="date" required class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Start Time</label>
                    <input name="start_time" type="time" required class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]">
                </div>
                <div class="md:col-span-2 xl:col-span-4">
                    <label class="mb-2 block text-sm font-medium">Remarks (Optional)</label>
                    <input name="remarks" type="text" class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="Special requests">
                </div>
                <div class="md:col-span-2 xl:col-span-4">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-[#B76E79] px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-[#a9606b]">Book Appointment</button>
                </div>
            </form>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php if ($services && mysqli_num_rows($services) > 0): ?>
                <?php mysqli_data_seek($services, 0); ?>
                <?php while ($service = mysqli_fetch_assoc($services)): ?>
                    <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md transition hover:bg-[#FDF4F5]">
                        <h2 class="text-lg font-semibold"><?php echo htmlspecialchars($service['service_name']); ?></h2>
                        <p class="mt-2 text-sm text-gray-500"><?php echo htmlspecialchars($service['description'] ?: 'No description provided.'); ?></p>
                        <div class="mt-4 flex items-center justify-between text-sm">
                            <span class="rounded-xl bg-[#F5D0D7] px-3 py-1 font-medium"><?php echo (int) $service['duration_minutes']; ?> mins</span>
                            <span class="font-semibold text-[#B76E79]">PHP <?php echo number_format((float) $service['price'], 2); ?></span>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <article class="col-span-full rounded-2xl border border-[#E5E7EB] bg-white p-8 text-center text-gray-500 shadow-md">No available services.</article>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
