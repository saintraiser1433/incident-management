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
        
        // Validate the number
        $number = "+63" . $number;
        
        // Check if the number starts with +639 and has correct length (Philippines mobile numbers are 10 digits after +63)
        if (!preg_match('/^\+639\d{9}$/', $number)) {
            throw new Exception('Philippine mobile number must start with 9 (format: 9XXXXXXXXX) and be 10 digits long');
        }
        
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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && isset($_POST['number'])) {
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
} else {
    echo 'Please provide message and number parameters';
}