<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$message = "";
$error = "";
$statuses = ['pending', 'confirmed', 'ongoing', 'completed', 'late', 'rescheduled', 'cancelled'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $error = "Invalid request token.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $customerId = (int) ($_POST['customer_id'] ?? 0);
            $staffId = (int) ($_POST['staff_id'] ?? 0);
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $appointmentDate = $_POST['appointment_date'] ?? '';
            $startTime = $_POST['start_time'] ?? '';
            $status = $_POST['status'] ?? 'pending';
            $remarks = trim($_POST['remarks'] ?? '');

            if ($customerId <= 0 || $staffId <= 0 || $serviceId <= 0 || $appointmentDate === '' || $startTime === '' || !in_array($status, $statuses, true)) {
                $error = "Please fill in all required appointment fields.";
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
                        $error = "Service duration is invalid.";
                    } else {
                        $endTime = date('H:i:s', strtotime($startTime . " +{$durationMinutes} minutes"));
                        $insertStmt = mysqli_prepare($conn, "INSERT INTO appointments (customer_id, staff_id, service_id, appointment_date, start_time, end_time, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($insertStmt) {
                            mysqli_stmt_bind_param($insertStmt, "iiisssss", $customerId, $staffId, $serviceId, $appointmentDate, $startTime, $endTime, $status, $remarks);
                            if (mysqli_stmt_execute($insertStmt)) {
                                $message = "Appointment created successfully.";
                            } else {
                                $error = "Failed to create appointment.";
                            }
                            mysqli_stmt_close($insertStmt);
                        } else {
                            $error = "Failed to prepare appointment creation.";
                        }
                    }
                } else {
                    $error = "Failed to prepare service lookup.";
                }
            }
        }

        if ($action === 'update') {
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $remarks = trim($_POST['remarks'] ?? '');
            if ($appointmentId <= 0 || !in_array($status, $statuses, true)) {
                $error = "Invalid appointment update.";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE appointments SET status = ?, remarks = ? WHERE appointment_id = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssi", $status, $remarks, $appointmentId);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Appointment updated successfully.";
                    } else {
                        $error = "Failed to update appointment.";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Failed to prepare appointment update.";
                }
            }
        }

        if ($action === 'delete') {
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
            if ($appointmentId <= 0) {
                $error = "Invalid appointment selected.";
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM appointments WHERE appointment_id = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $appointmentId);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Appointment deleted successfully.";
                    } else {
                        $error = "Failed to delete appointment. It may be linked to monitoring tables.";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Failed to prepare appointment deletion.";
                }
            }
        }
    }
}

$customers = mysqli_query($conn, "SELECT user_id, full_name FROM users WHERE role = 'customer' ORDER BY full_name ASC");
$staffMembers = mysqli_query($conn, "SELECT user_id, full_name FROM users WHERE role = 'staff' ORDER BY full_name ASC");
$services = mysqli_query($conn, "SELECT service_id, service_name FROM services ORDER BY service_name ASC");

$appointments = mysqli_query(
    $conn,
    "SELECT a.appointment_id, a.appointment_date, a.start_time, a.end_time, a.status, a.remarks, cu.full_name AS customer_name, su.full_name AS staff_name, s.service_name
     FROM appointments a
     INNER JOIN users cu ON a.customer_id = cu.user_id
     INNER JOIN users su ON a.staff_id = su.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     ORDER BY a.appointment_date DESC, a.start_time DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/adminsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Manage Appointments</h1>
            <p class="mt-1 text-sm text-gray-500">Appointment scheduling and status visibility.</p>
        </section>

        <?php if ($message !== ''): ?>
            <div class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h2 class="text-lg font-semibold">Create Appointment</h2>
            <form method="POST" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="mb-2 block text-sm font-medium">Customer</label>
                    <select name="customer_id" required class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]">
                        <option value="">Select customer</option>
                        <?php if ($customers): while ($row = mysqli_fetch_assoc($customers)): ?>
                            <option value="<?php echo (int) $row['user_id']; ?>"><?php echo htmlspecialchars($row['full_name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Staff</label>
                    <select name="staff_id" required class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]">
                        <option value="">Select staff</option>
                        <?php if ($staffMembers): while ($row = mysqli_fetch_assoc($staffMembers)): ?>
                            <option value="<?php echo (int) $row['user_id']; ?>"><?php echo htmlspecialchars($row['full_name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Service</label>
                    <select name="service_id" required class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]">
                        <option value="">Select service</option>
                        <?php if ($services): while ($row = mysqli_fetch_assoc($services)): ?>
                            <option value="<?php echo (int) $row['service_id']; ?>"><?php echo htmlspecialchars($row['service_name']); ?></option>
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
                <div>
                    <label class="mb-2 block text-sm font-medium">Status</label>
                    <select name="status" class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2 xl:col-span-2">
                    <label class="mb-2 block text-sm font-medium">Remarks</label>
                    <input name="remarks" type="text" class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="Optional remarks">
                </div>
                <div class="md:col-span-2 xl:col-span-4">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-[#B76E79] px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-[#a9606b]">Create Appointment</button>
                </div>
            </form>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">#</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3">Schedule</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="rounded-r-xl px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($appointments && mysqli_num_rows($appointments) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($appointments)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3"><?php echo (int) $row['appointment_id']; ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['staff_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['service_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['appointment_date'] . " " . date("g:i A", strtotime($row['start_time'])) . " - " . date("g:i A", strtotime($row['end_time']))); ?></td>
                                <td class="px-4 py-3">
                                    <form method="POST" class="flex flex-col gap-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="appointment_id" value="<?php echo (int) $row['appointment_id']; ?>">
                                        <select name="status" class="rounded-lg border border-[#E5E7EB] px-2 py-1 text-xs">
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $row['status'] === $status ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="remarks" value="<?php echo htmlspecialchars($row['remarks'] ?? ''); ?>" class="w-40 rounded-lg border border-[#E5E7EB] px-2 py-1 text-xs" placeholder="Remarks">
                                        <button type="submit" class="rounded-lg border border-[#E5E7EB] px-2 py-1 text-xs font-medium hover:bg-white">Save</button>
                                    </form>
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" onsubmit="return confirm('Delete this appointment?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="appointment_id" value="<?php echo (int) $row['appointment_id']; ?>">
                                        <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No appointments available.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
