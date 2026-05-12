<?php
function salonix_table_exists($conn, $tableName) {
    $stmt = mysqli_prepare($conn, "SHOW TABLES LIKE ?");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $tableName);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $exists;
}

function salonix_delete_rows_by_appointment($conn, $tableName, $appointmentId) {
    $stmt = mysqli_prepare($conn, "DELETE FROM {$tableName} WHERE appointment_id = ?");
    if (!$stmt) {
        throw new mysqli_sql_exception("Failed to prepare {$tableName} cleanup.");
    }

    mysqli_stmt_bind_param($stmt, "i", $appointmentId);
    $deleted = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$deleted) {
        throw new mysqli_sql_exception("Failed to delete linked {$tableName} rows.");
    }
}

function salonix_delete_appointment_links($conn, $appointmentId) {
    $dependentTables = [
        'appointment_feedback',
        'late_clients',
        'redo_jobs',
        'service_monitoring',
        'notification_reads',
    ];

    foreach ($dependentTables as $tableName) {
        if (salonix_table_exists($conn, $tableName)) {
            salonix_delete_rows_by_appointment($conn, $tableName, $appointmentId);
        }
    }
}

function salonix_delete_appointment($conn, $appointmentId) {
    salonix_delete_appointment_links($conn, $appointmentId);

    $stmt = mysqli_prepare($conn, "DELETE FROM appointments WHERE appointment_id = ?");
    if (!$stmt) {
        throw new mysqli_sql_exception("Failed to prepare appointment deletion.");
    }

    mysqli_stmt_bind_param($stmt, "i", $appointmentId);
    $deleted = mysqli_stmt_execute($stmt);
    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if (!$deleted || $affectedRows < 1) {
        throw new mysqli_sql_exception("Failed to delete appointment.");
    }
}

function salonix_delete_user_appointments($conn, $userId, $role) {
    $userColumn = $role === 'staff' ? 'staff_id' : 'customer_id';
    $stmt = mysqli_prepare($conn, "SELECT appointment_id FROM appointments WHERE {$userColumn} = ?");
    if (!$stmt) {
        throw new mysqli_sql_exception("Failed to prepare appointment lookup.");
    }

    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $appointmentIds = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $appointmentIds[] = (int) $row['appointment_id'];
        }
    }

    mysqli_stmt_close($stmt);

    foreach ($appointmentIds as $appointmentId) {
        salonix_delete_appointment($conn, $appointmentId);
    }
}

function salonix_delete_user_links($conn, $userId, $role) {
    if (salonix_table_exists($conn, 'notification_reads')) {
        $stmt = mysqli_prepare($conn, "DELETE FROM notification_reads WHERE user_id = ?");
        if (!$stmt) {
            throw new mysqli_sql_exception("Failed to prepare notification cleanup.");
        }

        mysqli_stmt_bind_param($stmt, "i", $userId);
        $deleted = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$deleted) {
            throw new mysqli_sql_exception("Failed to delete linked notification rows.");
        }
    }

    if ($role === 'customer' && salonix_table_exists($conn, 'appointment_feedback')) {
        $stmt = mysqli_prepare($conn, "DELETE FROM appointment_feedback WHERE customer_id = ?");
        if (!$stmt) {
            throw new mysqli_sql_exception("Failed to prepare feedback cleanup.");
        }

        mysqli_stmt_bind_param($stmt, "i", $userId);
        $deleted = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$deleted) {
            throw new mysqli_sql_exception("Failed to delete linked feedback rows.");
        }
    }
}

function salonix_delete_user_with_appointments($conn, $userId, $role) {
    salonix_delete_user_appointments($conn, $userId, $role);
    salonix_delete_user_links($conn, $userId, $role);

    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ? AND role = ?");
    if (!$stmt) {
        throw new mysqli_sql_exception("Failed to prepare user deletion.");
    }

    mysqli_stmt_bind_param($stmt, "is", $userId, $role);
    $deleted = mysqli_stmt_execute($stmt);
    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if (!$deleted || $affectedRows < 1) {
        throw new mysqli_sql_exception("Failed to delete user.");
    }
}
?>
