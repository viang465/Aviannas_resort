<?php
session_start();
include "../conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Safely copy to deleted_bookings archive before deletion
    $conn->begin_transaction();
    try {
        $copyStmt = $conn->prepare("INSERT INTO deleted_bookings (name, email, contact, address, room_type, cottage_type, pax, payment_method, total_price, checkin_date, checkout_date, deletion_date) 
                                    SELECT name, email, contact, address, room_type, cottage_type, pax, payment_method, total_price, checkin_date, checkout_date, NOW() 
                                    FROM bookings WHERE id = ?");
        $copyStmt->bind_param("i", $id);
        $copyStmt->execute();
        $copyStmt->close();

        $delStmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
        $delStmt->bind_param("i", $id);
        $delStmt->execute();
        $delStmt->close();

        $conn->commit();
        header("Location: admin.php?cancel=success");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error archiving record: " . $e->getMessage();
    }
} else {
    header("Location: admin.php");
    exit();
}