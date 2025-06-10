<?php
require 'config.php';

// Get raw JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Collect input
$identifier = $data['identifier'] ?? ''; // email or phone
$password   = $data['password']   ?? '';

// Validate input
if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email/Phone and password are required."]);
    exit;
}

// Lookup user
$stmt = $conn->prepare("SELECT * FROM users WHERE (email = ? OR phone = ?) AND isDeleted = 0 AND isActive = 1");
$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        echo json_encode([
            "status" => "success",
            "message" => "Login successful.",
            "role" => $user['role']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid password."]);
    }
} else {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "User not found."]);
}

$stmt->close();
$conn->close();
?>
