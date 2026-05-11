<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$message = "";
$error = "";
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editService = null;

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
            $serviceName = trim($_POST['service_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $duration = (int) ($_POST['duration_minutes'] ?? 0);
            $price = (float) ($_POST['price'] ?? 0);

            if ($serviceName === '' || $duration <= 0 || $price <= 0) {
                $error = "Service name, duration, and price are required.";
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO services (service_name, description, duration_minutes, price) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssid", $serviceName, $description, $duration, $price);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Service created successfully.";
                    } else {
                        $error = "Failed to create service.";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Failed to prepare service creation.";
                }
            }
        }

        if ($action === 'update') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $serviceName = trim($_POST['service_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $duration = (int) ($_POST['duration_minutes'] ?? 0);
            $price = (float) ($_POST['price'] ?? 0);

            if ($serviceId <= 0 || $serviceName === '' || $duration <= 0 || $price <= 0) {
                $error = "All service fields are required.";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE services SET service_name = ?, description = ?, duration_minutes = ?, price = ? WHERE service_id = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssidi", $serviceName, $description, $duration, $price, $serviceId);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Service updated successfully.";
                        $editId = 0;
                    } else {
                        $error = "Failed to update service.";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Failed to prepare service update.";
                }
            }
        }

        if ($action === 'delete') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            if ($serviceId <= 0) {
                $error = "Invalid service selected.";
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM services WHERE service_id = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $serviceId);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Service deleted successfully.";
                    } else {
                        $error = "Cannot delete service. It may be linked to appointments.";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Failed to prepare service deletion.";
                }
            }
        }
    }
}

if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT service_id, service_name, description, duration_minutes, price FROM services WHERE service_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $editService = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
    }
}

$services = mysqli_query($conn, "SELECT service_id, service_name, description, duration_minutes, price FROM services ORDER BY service_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/adminsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Manage Services</h1>
            <p class="mt-1 text-sm text-gray-500">Service catalog with duration and pricing.</p>
        </section>

        <?php if ($message !== ''): ?>
            <div class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h2 class="text-lg font-semibold"><?php echo $editService ? 'Update Service' : 'Create Service'; ?></h2>
            <form method="POST" class="mt-4 grid gap-4 md:grid-cols-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="<?php echo $editService ? 'update' : 'create'; ?>">
                <?php if ($editService): ?>
                    <input type="hidden" name="service_id" value="<?php echo (int) $editService['service_id']; ?>">
                <?php endif; ?>
                <div>
                    <label class="mb-2 block text-sm font-medium">Service Name</label>
                    <input name="service_name" type="text" required class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" value="<?php echo htmlspecialchars($editService['service_name'] ?? ''); ?>">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Duration (minutes)</label>
                    <input name="duration_minutes" type="number" min="1" required class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" value="<?php echo htmlspecialchars((string) ($editService['duration_minutes'] ?? '')); ?>">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Price (PHP)</label>
                    <input name="price" type="number" min="1" step="0.01" required class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" value="<?php echo htmlspecialchars((string) ($editService['price'] ?? '')); ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]"><?php echo htmlspecialchars($editService['description'] ?? ''); ?></textarea>
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-[#B76E79] px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-[#a9606b]">
                        <?php echo $editService ? 'Update Service' : 'Create Service'; ?>
                    </button>
                    <?php if ($editService): ?>
                        <a href="manage_services.php" class="inline-flex items-center rounded-xl border border-[#E5E7EB] px-4 py-2 text-sm font-medium hover:bg-[#FDF4F5]">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Service</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Duration</th>
                            <th class="px-4 py-3">Price</th>
                            <th class="rounded-r-xl px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                        <?php if ($services && mysqli_num_rows($services) > 0): ?>
                            <?php while ($service = mysqli_fetch_assoc($services)): ?>
                                <tr class="hover:bg-[#FDF4F5] transition">
                                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($service['service_name']); ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($service['description'] ?: '-'); ?></td>
                                    <td class="px-4 py-3"><?php echo (int) $service['duration_minutes']; ?> mins</td>
                                    <td class="px-4 py-3 text-[#B76E79] font-semibold">PHP <?php echo number_format((float) $service['price'], 2); ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <a href="manage_services.php?edit=<?php echo (int) $service['service_id']; ?>" class="rounded-lg border border-[#E5E7EB] px-3 py-1.5 text-xs font-medium hover:bg-white">Edit</a>
                                            <form method="POST" onsubmit="return confirm('Delete this service?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="service_id" value="<?php echo (int) $service['service_id']; ?>">
                                                <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No services found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>

