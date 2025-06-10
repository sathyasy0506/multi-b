<?php
// add_business.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


header('Content-Type: application/json');
require 'config.php'; // your PDO connection file

function generateGUID() {
    if (function_exists('com_create_guid')) {
        return trim(com_create_guid(), '{}');
    } else {
        // fallback if not on Windows
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception('Invalid JSON input');
    }

    // Required fields (add validation as needed)
    $user_guid = $data['user_guid'] ?? null;
    $name = $data['name'] ?? null;
    $is_multi_branch = isset($data['is_multi_branch']) ? (int)$data['is_multi_branch'] : 0;
    $is_doorstep_service = isset($data['is_doorstep_service']) ? (int)$data['is_doorstep_service'] : 0;
    $address = $data['address'] ?? null;
    $phone = $data['phone'] ?? null;
    $google_map_url = $data['google_map_url'] ?? null;
    $isSlot = isset($data['isSlot']) ? (int)$data['isSlot'] : 0;

    // Business info optional fields
    $blobs_gallery = $data['blobs_gallery'] ?? null;
    $profile_image_url = $data['profile_image_url'] ?? null;
    $info_q = $data['info_q'] ?? null;
    $info_a = $data['info_a'] ?? null;
    $description = $data['description'] ?? null;

    if (!$user_guid || !$name) {
        throw new Exception('Missing required fields');
    }

    $pdo->beginTransaction();

    // 1. Insert business
    $business_guid = generateGUID();
    $stmtBusiness = $pdo->prepare("
        INSERT INTO businesses
        (business_guid, user_guid, name, is_multi_branch, is_doorstep_service, address, phone, google_map_url, isSlot)
        VALUES (:business_guid, :user_guid, :name, :is_multi_branch, :is_doorstep_service, :address, :phone, :google_map_url, :isSlot)
    ");
    $stmtBusiness->execute([
        ':business_guid' => $business_guid,
        ':user_guid' => $user_guid,
        ':name' => $name,
        ':is_multi_branch' => $is_multi_branch,
        ':is_doorstep_service' => $is_doorstep_service,
        ':address' => $address,
        ':phone' => $phone,
        ':google_map_url' => $google_map_url,
        ':isSlot' => $isSlot
    ]);

    // 2. Insert business_info
    $stmtInfo = $pdo->prepare("
        INSERT INTO business_info
        (business_guid, user_guid, blobs_gallery, profile_image_url, info_q, info_a, Descrption)
        VALUES (:business_guid, :user_guid, :blobs_gallery, :profile_image_url, :info_q, :info_a, :description)
    ");
    $stmtInfo->execute([
        ':business_guid' => $business_guid,
        ':user_guid' => $user_guid,
        ':blobs_gallery' => $blobs_gallery,
        ':profile_image_url' => $profile_image_url,
        ':info_q' => $info_q,
        ':info_a' => $info_a,
        ':description' => $description
    ]);

    $insertedBranches = [];

    // 3. Branch insertion logic based on your requirements
    if ($isSlot === 0) {
        // Create exactly ONE branch (default)
        $branch_guid = generateGUID();
        $stmtBranch = $pdo->prepare("
            INSERT INTO branches
            (branch_guid, business_guid, name, address, phone, lat, lng, google_map_url)
            VALUES (:branch_guid, :business_guid, :name, :address, :phone, NULL, NULL, NULL)
        ");
        $stmtBranch->execute([
            ':branch_guid' => $branch_guid,
            ':business_guid' => $business_guid,
            ':name' => $name . " Default Branch",
            ':address' => $address,
            ':phone' => $phone
        ]);
        $insertedBranches[] = ['branch_guid' => $branch_guid, 'name' => $name . " Default Branch"];
    } else {
        // isSlot === 1
        if ($is_multi_branch === 1 && !empty($data['branches']) && is_array($data['branches'])) {
            // Create branches as given
            foreach ($data['branches'] as $branch) {
                $branch_guid = generateGUID();
                $stmtBranch = $pdo->prepare("
                    INSERT INTO branches
                    (branch_guid, business_guid, name, address, phone, lat, lng, google_map_url)
                    VALUES (:branch_guid, :business_guid, :name, :address, :phone, :lat, :lng, :google_map_url)
                ");
                $stmtBranch->execute([
                    ':branch_guid' => $branch_guid,
                    ':business_guid' => $business_guid,
                    ':name' => $branch['name'] ?? '',
                    ':address' => $branch['address'] ?? '',
                    ':phone' => $branch['phone'] ?? '',
                    ':lat' => $branch['lat'] ?? null,
                    ':lng' => $branch['lng'] ?? null,
                    ':google_map_url' => $branch['google_map_url'] ?? null
                ]);
                $insertedBranches[] = ['branch_guid' => $branch_guid, 'name' => $branch['name'] ?? ''];
            }
        } else {
            // Create ONE default branch because is_multi_branch = 0 but isSlot = 1
            $branch_guid = generateGUID();
            $stmtBranch = $pdo->prepare("
                INSERT INTO branches
                (branch_guid, business_guid, name, address, phone, lat, lng, google_map_url)
                VALUES (:branch_guid, :business_guid, :name, :address, :phone, NULL, NULL, NULL)
            ");
            $stmtBranch->execute([
                ':branch_guid' => $branch_guid,
                ':business_guid' => $business_guid,
                ':name' => $name . " Default Branch",
                ':address' => $address,
                ':phone' => $phone
            ]);
            $insertedBranches[] = ['branch_guid' => $branch_guid, 'name' => $name . " Default Branch"];
        }
    }

    // 4. Insert slots if isSlot = 1 and slots provided
    if ($isSlot === 1 && !empty($data['slots']) && is_array($data['slots'])) {
        $stmtSlot = $pdo->prepare("
            INSERT INTO slots
            (slot_guid, service_guid, branch_guid, start_time, end_time)
            VALUES (:slot_guid, :service_guid, :branch_guid, :start_time, :end_time)
        ");

        foreach ($data['slots'] as $slot) {
            // Determine branch_guid for slot
            // If branch_guid in slot exists, use it, else use the first inserted branch_guid
            $slot_branch_guid = $slot['branch_guid'] ?? null;

            if (!$slot_branch_guid) {
                // fallback to first branch created above
                $slot_branch_guid = $insertedBranches[0]['branch_guid'];
            }

            $slot_guid = generateGUID();
            $stmtSlot->execute([
                ':slot_guid' => $slot_guid,
                ':service_guid' => $slot['service_guid'] ?? null,
                ':branch_guid' => $slot_branch_guid,
                ':start_time' => $slot['start_time'] ?? null,
                ':end_time' => $slot['end_time'] ?? null
            ]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'status' => true,
        'message' => 'Business added successfully.',
        'business_guid' => $business_guid,
        'branches' => $insertedBranches
    ]);
} catch (Exception $ex) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Error: ' . $ex->getMessage()
    ]);
}
