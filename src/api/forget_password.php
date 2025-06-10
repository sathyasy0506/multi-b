<?php
require 'config.php';

header("Content-Type: application/json");

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

$identifier   = $data['identifier'] ?? '';
$newPassword  = $data['new_password'] ?? '';

if (empty($identifier) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email/Phone and new password are required."]);
    exit;
}

// Find user
$stmt = $conn->prepare("SELECT id, password FROM users WHERE (email = ? OR phone = ?) AND isDeleted = 0 AND isActive = 1");
$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "User not found."]);
    exit;
}

$user = $result->fetch_assoc();

// Check if new password is same as old
if (password_verify($newPassword, $user['password'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "New password must be different from the old password."
    ]);
    exit;
}

// Update password
$newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
$updateStmt = $conn->prepare("UPDATE users SET password = ?, modifiedOn = NOW() WHERE id = ?");
$updateStmt->bind_param("si", $newHashedPassword, $user['id']);

if ($updateStmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Password reset successful."]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to reset password."]);
}

$stmt->close();
$updateStmt->close();
$conn->close();
?>
