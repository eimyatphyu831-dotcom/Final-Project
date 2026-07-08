<?php
include "../config/db.php";

$name = "Admin";
$email = "admin@gmail.com";
$passwordPlain = "admin123";

$stmt = $conn->prepare("SELECT id FROM admins WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    die("Admin already exists in admins table!");
}

$passwordHashed = password_hash($passwordPlain, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $passwordHashed);

if ($stmt->execute()) {
    echo "Admin inserted into admins table successfully!";
} else {
    echo "Error: " . $stmt->error;
}
?>
