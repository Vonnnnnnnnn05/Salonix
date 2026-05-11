<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit;
}

$staffId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $appointmentId = isset($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : 0;
        $status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : 'ongoing';
        $remarks = isset($_POST['remarks']) ? mysqli_real_escape_string($conn, $_POST['remarks']) : '';

        $allowedStatuses = ['pending', 'confirmed', 'ongoing', 'completed', 'cancelled', 'rescheduled'];
        if (!in_array($status, $allowedStatuses)) {
            $status = 'ongoing';
        }

        $updateStmt = mysqli_prepare($conn, "UPDATE appointments SET status = ?, remarks = ? WHERE appointment_id = ? AND staff_id = ?");
        mysqli_stmt_bind_param($updateStmt, "ssii", $status, $remarks, $appointmentId, $staffId);
        
        if (mysqli_stmt_execute($updateStmt)) {
            $success = "Appointment updated successfully.";
        } else {
            $error = "Failed to update appointment.";
        }
        mysqli_stmt_close($updateStmt);
    }
}

$where = "a.status = 'ongoing'" . ($staffId > 0 ? " AND a.staff_id = {$staffId}" : "");

$ongoing = mysqli_query(
    $conn,
    "SELECT a.appointment_id, a.appointment_date, a.start_time, a.status, a.remarks, u.full_name, s.service_name
     FROM appointments a
     INNER JOIN users u ON a.customer_id = u.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     WHERE {$where}
     ORDER BY a.appointment_date DESC, a.start_time ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ongoing Services | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/staffsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Ongoing Services</h1>
            <p class="mt-1 text-sm text-gray-500">Currently active client services. Click to edit details.</p>
        </section>

        <?php if (isset($success)): ?>
            <div class="mt-4 rounded-2xl bg-green-50 border border-green-200 p-4 text-green-700">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="mt-4 rounded-2xl bg-red-50 border border-red-200 p-4 text-red-700">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php if ($ongoing && mysqli_num_rows($ongoing) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($ongoing)): ?>
                    <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md hover:shadow-lg transition cursor-pointer" onclick="openEditModal(<?php echo $row['appointment_id']; ?>, '<?php echo htmlspecialchars($row['status']); ?>', '<?php echo htmlspecialchars($row['remarks']); ?>')">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h2 class="font-semibold"><?php echo htmlspecialchars($row['service_name']); ?></h2>
                                <p class="mt-2 text-sm text-gray-500"><?php echo htmlspecialchars($row['full_name']); ?></p>
                                <p class="mt-3 text-sm font-medium text-[#B76E79]"><?php echo htmlspecialchars($row['appointment_date'] . " " . date("g:i A", strtotime($row['start_time']))); ?></p>
                            </div>
                            <button type="button" class="text-[#B76E79] hover:text-[#964a54] ml-2" onclick="event.stopPropagation();">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <article class="col-span-full rounded-2xl border border-[#E5E7EB] bg-white p-8 text-center text-gray-500 shadow-md">No ongoing services.</article>
            <?php endif; ?>
        </section>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full">
            <h2 class="text-xl font-semibold mb-4">Edit Service Status</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="appointment_id" id="appointmentId">

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Status</label>
                    <select name="status" id="appointmentStatus" required class="w-full px-3 py-2 border border-[#E5E7EB] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#B76E79]">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="rescheduled">Rescheduled</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Remarks</label>
                    <textarea name="remarks" id="appointmentRemarks" rows="3" class="w-full px-3 py-2 border border-[#E5E7EB] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#B76E79]" placeholder="Add any notes..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeEditModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-[#B76E79] text-white rounded-lg hover:bg-[#964a54] transition">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, status, remarks) {
            document.getElementById('appointmentId').value = id;
            document.getElementById('appointmentStatus').value = status;
            document.getElementById('appointmentRemarks').value = remarks;
            document.getElementById('editModal').classList.remove('hidden');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>
</html>

