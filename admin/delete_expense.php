<?php
session_start();
include "../conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expense_id'])) {
    $expense_id = (int) $_POST['expense_id'];

    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: admin_expenses.php?expense=deleted");
exit();