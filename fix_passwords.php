<?php
/**
 * Password Fix Script - Web Version
 * This script will update the placeholder passwords with proper hashes
 */

require_once 'config/config.php';

// Check if PDO MySQL is available
if (!extension_loaded('pdo_mysql')) {
    die('<h2>Error: PDO MySQL extension not loaded</h2><p>Please ensure WAMP64 is running and MySQL service is started.</p>');
}

$message = '';
$error = '';

if (isset($_POST['fix_passwords'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            throw new Exception('Could not connect to database. Please check your WAMP64 setup.');
        }
        
        // Define the correct passwords for each user
        $users_to_fix = [
            'admin@incidentmgmt.com' => 'admin123',
            'sarah.johnson@cityhospital.com' => 'org123',
            'michael.brown@metropd.com' => 'org123',
            'lisa.davis@cityfire.com' => 'org123',
            'tom.wilson@university.edu' => 'org123',
            'john.smith@email.com' => 'resp123',
            'jane.doe@email.com' => 'resp123',
            'bob.johnson@email.com' => 'resp123',
            'alice.brown@email.com' => 'resp123'
        ];
        
        $updated_count = 0;
        $results = [];
        
        foreach ($users_to_fix as $email => $password) {
            // Check if user exists
            $query = "SELECT id, name FROM users WHERE email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update the password
                $update_query = "UPDATE users SET password = ? WHERE email = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$hashed_password, $email]);
                
                $results[] = "✓ Updated password for: {$user['name']} ({$email})";
                $updated_count++;
            } else {
                $results[] = "✗ User not found: {$email}";
            }
        }
        
        $message = "Password fix completed! Updated {$updated_count} users.<br><br>" . implode('<br>', $results);
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Passwords - Incident Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }
        .fix-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .fix-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .fix-body {
            padding: 2rem;
        }
        .btn-fix {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-fix:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="fix-card">
                    <div class="fix-header">
                        <i class="fas fa-key fa-3x mb-3"></i>
                        <h3>Password Fix Tool</h3>
                        <p class="mb-0">Fix placeholder passwords in the database</p>
                    </div>
                    <div class="fix-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $message; ?>
                            </div>
                            
                            <div class="mt-4 p-3 bg-light rounded">
                                <h5>Demo Login Credentials:</h5>
                                <ul class="mb-0">
                                    <li><strong>Admin:</strong> admin@incidentmgmt.com / admin123</li>
                                    <li><strong>Organization:</strong> sarah.johnson@cityhospital.com / org123</li>
                                    <li><strong>Responder:</strong> john.smith@email.com / resp123</li>
                                </ul>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="auth/login.php" class="btn btn-primary btn-fix">
                                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                This tool will fix the placeholder passwords in your database by replacing them with proper password hashes.
                            </div>
                            
                            <div class="text-center">
                                <a href="test_login.php" class="btn btn-info btn-fix me-3">
                                    <i class="fas fa-check-circle me-2"></i>Test Login System
                                </a>
                                
                                <form method="POST" style="display: inline;">
                                    <button type="submit" name="fix_passwords" class="btn btn-primary btn-fix">
                                        <i class="fas fa-wrench me-2"></i>Fix Passwords
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
