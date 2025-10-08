<?php
/**
 * SMS Gateway Service
 * MDRRMO-GLAN Incident Reporting and Response Coordination System
 */

require_once 'config/database.php';
require 'vendor/autoload.php';

use AndroidSmsGateway\Client;
use AndroidSmsGateway\Encryptor;
use AndroidSmsGateway\Domain\Message;

function sendSMS($number, $message) {
    try {
        // Debug: Log the incoming parameters
        error_log("SMS Service Called - Number: {$number}, Message: " . substr($message, 0, 50) . "...");
        
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            throw new Exception('Database connection failed');
        }
        
        // Get SMS settings from database
        $query = "SELECT username, password, is_active FROM sms_settings WHERE is_active = 1 LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $sms_settings = $stmt->fetch();
        
        if (!$sms_settings) {
            throw new Exception('SMS settings not configured or disabled');
        }
        
        if (empty($sms_settings['username']) || empty($sms_settings['password'])) {
            throw new Exception('SMS credentials not configured');
        }
        
        // Validate and format the number
        error_log("SMS Service - Original number: {$number}");
        
        // If number starts with 09, convert to +63 format
        if (preg_match('/^09\d{9}$/', $number)) {
            $number = "+63" . substr($number, 1); // Remove 0, add +63
            error_log("SMS Service - Converted 09 format to: {$number}");
        } elseif (preg_match('/^9\d{9}$/', $number)) {
            $number = "+63" . $number; // Add +63 prefix
            error_log("SMS Service - Converted 9 format to: {$number}");
        } else {
            error_log("SMS Service - Invalid number format: {$number}");
            throw new Exception('Philippine mobile number must start with 09 (format: 09XXXXXXXXX) and be 11 digits long');
        }
        
        // Final validation for +63 format
        if (!preg_match('/^\+639\d{9}$/', $number)) {
            error_log("SMS Service - Final validation failed for: {$number}");
            throw new Exception('Invalid Philippine mobile number format');
        }
        
        error_log("SMS Service - Final formatted number: {$number}");
        
        $client = new Client($sms_settings['username'], $sms_settings['password']);
        $messageObj = new Message($message, [$number]);

        $messageState = $client->Send($messageObj);
        return [
            'success' => true,
            'message_id' => $messageState->ID(),
            'message' => 'SMS sent successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Handle direct API calls (for testing)
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && isset($_POST['number'])) {
    $number = $_POST['number'];
    $message = $_POST['message'];
    
    // Add debug logging
    error_log("SMS Test Request - Number: $number, Message: $message");
    
    $result = sendSMS($number, $message);
    
    if ($result['success']) {
        echo "Message sent with ID: " . $result['message_id'] . PHP_EOL;
        error_log("SMS Test Success - Message ID: " . $result['message_id']);
    } else {
        echo "Error sending message: " . $result['error'] . PHP_EOL;
        error_log("SMS Test Error: " . $result['error']);
        http_response_code(400);
    }
} else if (basename($_SERVER['PHP_SELF']) === 'sms.php') {
    // Only show this message if sms.php is being called directly
    echo 'Please provide message and number parameters';
}