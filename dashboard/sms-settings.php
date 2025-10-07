<?php
/**
 * SMS Settings Management (Admin Only)
 * MDRRMO-GLAN Incident Reporting and Response Coordination System
 */

require_once '../config/config.php';
require_role(['Admin']);

$page_title = 'SMS Settings - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // Don't sanitize password as it might contain special characters
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($username) || empty($password)) {
        $error_message = 'Username and password are required.';
    } else {
        try {
            // Check if SMS settings already exist
            $query = "SELECT id FROM sms_settings LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing settings
                $query = "UPDATE sms_settings SET username = ?, password = ?, is_active = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$username, $password, $is_active, $existing['id']]);
            } else {
                // Insert new settings
                $query = "INSERT INTO sms_settings (username, password, is_active) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$username, $password, $is_active]);
            }
            
            log_audit('UPDATE', 'sms_settings', $existing['id'] ?? $db->lastInsertId());
            $success_message = 'SMS settings updated successfully!';
        } catch (Exception $e) {
            $error_message = 'Error updating SMS settings: ' . $e->getMessage();
        }
    }
}

// Get current SMS settings
$sms_settings = null;
try {
    $query = "SELECT * FROM sms_settings LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $sms_settings = $stmt->fetch();
} catch (Exception $e) {
    // Table might not exist yet
    $error_message = 'SMS settings table not found. Please contact system administrator.';
}
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-sms me-2"></i>SMS Settings
                </h1>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- SMS Settings Form -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-cog me-2"></i>SMS Gateway Configuration
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">SMS Gateway Username *</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($sms_settings['username'] ?? ''); ?>" required>
                                <div class="form-text">Enter your SMS gateway username</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">SMS Gateway Password *</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       value="<?php echo htmlspecialchars($sms_settings['password'] ?? ''); ?>" required>
                                <div class="form-text">Enter your SMS gateway password</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           <?php echo ($sms_settings['is_active'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Enable SMS Notifications
                                    </label>
                                    <div class="form-text">When enabled, SMS notifications will be sent for incident reports and updates</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn ui-btn-primary">
                                    <i class="fas fa-save me-2"></i>Save SMS Settings
                                </button>
                                <button type="button" class="btn ui-btn-ghost ms-2" onclick="testSMS()">
                                    <i class="fas fa-paper-plane me-2"></i>Test SMS
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- SMS Test Modal -->
            <div class="modal fade" id="testSMSModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Test SMS</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="testSMSForm">
                                <div class="mb-3">
                                    <label for="test_number" class="form-label">Test Phone Number *</label>
                                    <input type="text" class="form-control" id="test_number" name="test_number" 
                                           placeholder="9XXXXXXXXX" required>
                                    <div class="form-text">Enter a Philippine mobile number (format: 9XXXXXXXXX)</div>
                                </div>
                                <div class="mb-3">
                                    <label for="test_message" class="form-label">Test Message *</label>
                                    <textarea class="form-control" id="test_message" name="test_message" rows="3" required>This is a test SMS from MDRRMO-GLAN Incident Reporting System.</textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn ui-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn ui-btn-primary" onclick="sendTestSMS()">Send Test SMS</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function testSMS() {
    const modal = new bootstrap.Modal(document.getElementById('testSMSModal'));
    modal.show();
}

function sendTestSMS() {
    const number = document.getElementById('test_number').value;
    const message = document.getElementById('test_message').value;
    
    if (!number || !message) {
        alert('Please fill in all fields');
        return;
    }
    
    // Validate Philippine mobile number
    if (!/^9\d{9}$/.test(number)) {
        alert('Please enter a valid Philippine mobile number (format: 9XXXXXXXXX)');
        return;
    }
    
    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    
    // Send test SMS
    fetch('../sms.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `number=${encodeURIComponent(number)}&message=${encodeURIComponent(message)}&username=${encodeURIComponent('<?php echo $sms_settings['username'] ?? ''; ?>')}&password=${encodeURIComponent('<?php echo $sms_settings['password'] ?? ''; ?>')}`
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('Message sent with ID')) {
            alert('Test SMS sent successfully!');
            bootstrap.Modal.getInstance(document.getElementById('testSMSModal')).hide();
        } else {
            alert('Error sending SMS: ' + data);
        }
    })
    .catch(error => {
        alert('Error sending SMS: ' + error.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>

<?php include '../views/footer.php'; ?>
