<?php
require 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);

$user_guid = $input['user_guid'] ?? '';
$business_name = $input['business_name'] ?? '';
$description = $input['descrption'] ?? ''; // now belongs to businesses
$infoList = $input['info'] ?? [];

$business_guid = uniqid('', true);
$isInfo = !empty($infoList) ? 1 : 0;

// Insert into businesses (now includes Descrption)
$stmt = $conn->prepare("
    INSERT INTO businesses (business_guid, user_guid, business_name, Descrption, isInfo)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("ssssi", $business_guid, $user_guid, $business_name, $description, $isInfo);

if ($stmt->execute()) {
    // Insert into business_info (no more descrption here)
    if ($isInfo) {
        foreach ($infoList as $item) {
            $q = $item['q'] ?? '';
            $a = $item['a'] ?? '';

            $stmtInfo = $conn->prepare("
                INSERT INTO business_info (business_guid, user_guid, info_q, info_a)
                VALUES (?, ?, ?, ?)
            ");
            $stmtInfo->bind_param("ssss", $business_guid, $user_guid, $q, $a);
            $stmtInfo->execute();
            $stmtInfo->close();
        }
    }

    echo json_encode([
        "status" => "success",
        "business_guid" => $business_guid
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
