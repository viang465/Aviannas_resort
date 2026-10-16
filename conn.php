<?php
$servername = "sql103.infinityfree.com";   // ← paste exact host from panel
$username   = "if0_42809740";               // ← paste exact username from panel
$password   = "HqVOXcARDc";    // ← paste the password you just reset
$dbname     = "if0_42809740_avianna_resort";         // ← paste EXACT full db name from panel

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>