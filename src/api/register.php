<?php
require 'config.php';

// Get raw JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Collect input
$name     = $data['name']     ?? '';
$email    = $data['email']    ?? '';
$phone    = $data['phone']    ?? '';
$password = $data['password'] ?? '';
$role     = $data['role']     ?? 'customer';

// Validate input
if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($role)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All fields are required."]);
    exit;
}

// Check for duplicates
$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
$checkStmt->bind_param("ss", $email, $phone);
$checkStmt->execute();
$checkStmt->store_result();
if ($checkStmt->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "Email or phone already exists."]);
    exit;
}
$checkStmt->close();

// Insert user
$user_guid = bin2hex(random_bytes(16));
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (user_guid, name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $user_guid, $name, $email, $phone, $hashedPassword, $role);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Registration successful."]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
