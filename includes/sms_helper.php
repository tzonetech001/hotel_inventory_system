<?php
/**
 * SMS Helper Functions using Beem API
 * Simplified version - No database logging
 */

// Beem API Credentials
define('BEEM_API_KEY', '386bdc07eae64a5');
define('BEEM_SECRET_KEY', 'NWJmNmZkYTdhODRkYmFhNDY1YjQ4Mzg2NzBiNjEzNzYzMDU0OGE4MWUzOWM5Yjc2OTI5ZDAwNDZiYmQ1ZDY4NA==');
define('BEEM_SENDER_ID', 'TZONE');

/**
 * Send SMS using Beem API
 * 
 * @param string $phone Phone number (can be 0712345678 or 255712345678)
 * @param string $message Message content (max 160 chars)
 * @return array ['success' => bool, 'message' => string]
 */
function sendSMS($phone, $message) {
    // Clean phone number - remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to Beem format (255XXXXXXXXX)
    if (substr($phone, 0, 1) == '0') {
        $phone = '255' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) == '+255') {
        $phone = substr($phone, 1);
    }
    
    // Validate phone number - must be 12 digits starting with 255
    if (strlen($phone) != 12 || substr($phone, 0, 3) != '255') {
        return [
            'success' => false, 
            'message' => 'Invalid phone number: ' . $phone
        ];
    }
    
    // Limit message to 160 characters
    if (strlen($message) > 160) {
        $message = substr($message, 0, 160);
    }
    
    // Prepare data for Beem API
    $postData = [
        'source_addr' => BEEM_SENDER_ID,
        'encoding' => 0,
        'message' => $message,
        'recipients' => [
            [
                'recipient_id' => 1,
                'dest_addr' => $phone
            ]
        ]
    ];
    
    // Send to Beem API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://apisms.beem.africa/v1/send',
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode(BEEM_API_KEY . ':' . BEEM_SECRET_KEY),
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Check if sent successfully
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['successful']) && $result['successful']) {
            return [
                'success' => true, 
                'message' => 'SMS sent successfully to ' . $phone
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Beem API: ' . ($result['message'] ?? 'Unknown error')
            ];
        }
    } else {
        return [
            'success' => false, 
            'message' => 'HTTP Error: ' . $http_code . ($curl_error ? ' - ' . $curl_error : '')
        ];
    }
}

/**
 * Get user phone number by role
 * 
 * @param string $role Role name (e.g., 'Hotel Manager', 'Storekeeper')
 * @return array Array of users with their phone numbers
 */
function getUsersByRole($role) {
    global $db;
    
    $sql = "SELECT u.id, u.fullname, u.phone, u.email 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE r.role_name = ? AND u.status = 'active' AND u.phone IS NOT NULL AND u.phone != ''";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get supplier contact by ID
 * 
 * @param int $supplier_id Supplier ID
 * @return array|null Supplier contact info
 */
function getSupplierContact($supplier_id) {
    global $db;
    
    $sql = "SELECT id, company_name, contact_person, phone, email 
            FROM suppliers 
            WHERE id = ? AND status = 'active'";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get storekeeper phone numbers
 * 
 * @return array Array of storekeeper phone numbers
 */
function getStorekeepers() {
    return getUsersByRole('Storekeeper');
}

/**
 * Get hotel managers phone numbers
 * 
 * @return array Array of hotel manager phone numbers
 */
function getHotelManagers() {
    return getUsersByRole('Hotel Manager');
}

/**
 * Send PO Approval Request SMS to Hotel Manager
 * 
 * @param array $po_data Purchase order data
 * @return array Result of SMS sending
 */
function sendPOApprovalRequest($po_data) {
    $managers = getHotelManagers();
    $results = [];
    
    if (empty($managers)) {
        return ['success' => false, 'message' => 'No hotel managers found with phone numbers'];
    }
    
    $message = "🔔 TAARIFA YA UNUNUZI\n";
    $message .= "PO: {$po_data['po_number']}\n";
    $message .= "Muuzaji: {$po_data['supplier_name']}\n";
    $message .= "Thamani: TZS " . number_format($po_data['total_amount'], 0) . "\n";
    $message .= "Aliyeomba: {$po_data['created_by_name']}\n";
    $message .= "Tafadhali ingia mfumo kukubali au kukataa.\n";
    $message .= "- TZONE Hotel";
    
    foreach ($managers as $manager) {
        if (!empty($manager['phone'])) {
            $result = sendSMS($manager['phone'], $message);
            $results[] = [
                'name' => $manager['fullname'],
                'phone' => $manager['phone'],
                'result' => $result
            ];
        }
    }
    
    return ['success' => true, 'results' => $results];
}

/**
 * Send PO Approval Status SMS to Supplier
 * 
 * @param array $po_data Purchase order data
 * @param string $status 'approved' or 'rejected'
 * @param string|null $reason Rejection reason (if rejected)
 * @return array Result of SMS sending
 */
function sendPOStatusToSupplier($po_data, $status, $reason = null) {
    $supplier = getSupplierContact($po_data['supplier_id']);
    
    if (!$supplier) {
        return ['success' => false, 'message' => 'Supplier not found'];
    }
    
    if (empty($supplier['phone'])) {
        return ['success' => false, 'message' => 'Supplier phone number not found'];
    }
    
    if ($status == 'approved') {
        $message = "✅ PO IMESHAIDHINISHWA!\n\n";
        $message .= "Namba ya PO: {$po_data['po_number']}\n";
        $message .= "Tarehe: " . date('d/m/Y') . "\n";
        $message .= "Jumla: TZS " . number_format($po_data['total_amount'], 0) . "\n\n";
        $message .= "Endelea na utayarishaji wa bidhaa.\n";
        $message .= "Asante - TZONE Hotel";
    } else {
        $message = "❌ PO IMERUDIWA!\n\n";
        $message .= "Namba ya PO: {$po_data['po_number']}\n";
        $message .= "Sababu: " . ($reason ?? "Hakuna sababu iliyotolewa") . "\n\n";
        $message .= "Wasiliana na ofisi ya ununuzi kwa maelezo zaidi.\n";
        $message .= "- TZONE Hotel";
    }
    
    return sendSMS($supplier['phone'], $message);
}

/**
 * Send PO Approval Status SMS to Storekeeper
 * 
 * @param array $po_data Purchase order data
 * @param string $status 'approved' or 'rejected'
 * @param string|null $reason Rejection reason (if rejected)
 * @return array Result of SMS sending
 */
function sendPOStatusToStorekeeper($po_data, $status, $reason = null) {
    $storekeepers = getStorekeepers();
    $results = [];
    
    if (empty($storekeepers)) {
        return ['success' => false, 'message' => 'No storekeepers found with phone numbers'];
    }
    
    if ($status == 'approved') {
        $message = "✅ PO IMESHAIDHINISHWA!\n\n";
        $message .= "PO: {$po_data['po_number']}\n";
        $message .= "Muuzaji: {$po_data['supplier_name']}\n";
        $message .= "Jumla: TZS " . number_format($po_data['total_amount'], 0) . "\n\n";
        $message .= "Bidhaa zitakapowasili, thibitisha kwenye mfumo.\n";
        $message .= "- TZONE Hotel";
    } else {
        $message = "❌ PO IMERUDIWA!\n\n";
        $message .= "PO: {$po_data['po_number']}\n";
        $message .= "Muuzaji: {$po_data['supplier_name']}\n";
        $message .= "Sababu: " . ($reason ?? "Hakuna sababu") . "\n\n";
        $message .= "- TZONE Hotel";
    }
    
    foreach ($storekeepers as $storekeeper) {
        if (!empty($storekeeper['phone'])) {
            $result = sendSMS($storekeeper['phone'], $message);
            $results[] = [
                'name' => $storekeeper['fullname'],
                'phone' => $storekeeper['phone'],
                'result' => $result
            ];
        }
    }
    
    return ['success' => true, 'results' => $results];
}

/**
 * Send direct SMS to any number (utility function)
 * 
 * @param string $phone Phone number
 * @param string $message Message content
 * @return array Result of SMS sending
 */
function sendDirectSMS($phone, $message) {
    return sendSMS($phone, $message);
}
?>