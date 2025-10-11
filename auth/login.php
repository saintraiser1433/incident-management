<?php
/**
 * Login Page
 * MDRRMO-GLAN Incident Reporting and Response Coordination System
 */

require_once '../config/config.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                throw new Exception('Database connection failed. Please check your WAMP64 setup.');
            }
            
            $query = "SELECT u.*, o.org_name FROM users u 
                      LEFT JOIN organizations o ON u.organization_id = o.id 
                      WHERE u.email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['organization_id'] = $user['organization_id'];
                $_SESSION['organization_name'] = $user['org_name'];
                $_SESSION['login_time'] = time();
                
                // Log login action (non-blocking)
                try {
                    log_audit('LOGIN', 'users', $user['id']);
                } catch (Exception $e) {
                    // Don't fail login if audit logging fails
                    error_log("Audit logging failed: " . $e->getMessage());
                }
                
                // Redirect to appropriate dashboard
                switch ($user['role']) {
                    case 'Admin':
                        redirect('dashboard/admin.php');
                        break;
                    case 'Organization Account':
                        redirect('dashboard/organization.php');
                        break;
                    default:
                        redirect('dashboard/index.php');
                }
            } else {
                $error_message = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $error_message = 'Login failed: ' . $e->getMessage();
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
    }

    .login-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .login-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        text-align: center;
    }

    .login-body {
        padding: 2rem;
    }

    .form-control {
        border-radius: 10px;
        border: 2px solid #e9ecef;
        padding: 12px 15px;
    }

    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .btn-login {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 10px;
        padding: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .guest-access-section {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 10px;
        padding: 1.5rem;
        margin: 1rem 0;
    }

    .btn-outline-primary {
        border-color: #667eea;
        color: #667eea;
        transition: all 0.3s ease;
    }

    .btn-outline-primary:hover {
        background-color: #667eea;
        border-color: #667eea;
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <img src="<?php echo BASE_URL; ?>assets/images/mdrmlogo.png" alt="MDRRMO Logo" style="height: 80px; width: auto; margin-bottom: 1rem;">
                        <h3><?php echo APP_NAME; ?></h3>
                        <p class="mb-0">Sign in to your account</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error_message; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    required>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </form>

                        <!-- Guest Access Link -->
                        <div class="guest-access-section">
                            <div class="text-center">
                                <p class="text-muted mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Don't have an account?
                                </p>
                                <a href="../dashboard/responder.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user-friends me-2"></i>Continue as Guest
                                </a>
                                <p class="small text-muted mt-3 mb-0">
                                    <i class="fas fa-check-circle me-1"></i>
                                    Submit incident reports without creating an account
                                </p>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Demo Credentials:<br>
                                Admin: admin@incidentmgmt.com / admin123<br>
                                Organization: sarah.johnson@cityhospital.com / org123
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>