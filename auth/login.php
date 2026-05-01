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

                try {
                    log_audit('LOGIN', 'users', $user['id']);
                } catch (Exception $e) {
                    error_log("Audit logging failed: " . $e->getMessage());
                }

                switch ($user['role']) {
                    case 'Admin':
                        redirect('dashboard/admin.php');
                        break;
                    case 'Organization Account':
                        redirect('dashboard/organization.php');
                        break;
                    case 'Organization Member':
                        redirect('dashboard/responder.php');
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
    </style>
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

    <div class="min-h-screen grid lg:grid-cols-2">

        <div class="hidden lg:flex relative flex-col justify-between p-10 bg-slate-900 text-slate-100 overflow-hidden">
            <div class="absolute inset-0 opacity-30 pointer-events-none"
                 style="background-image: radial-gradient(circle at 20% 20%, rgba(99,102,241,0.35), transparent 40%), radial-gradient(circle at 80% 80%, rgba(56,189,248,0.25), transparent 40%);"></div>

            <div class="relative z-10 flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-white/10 ring-1 ring-white/15 flex items-center justify-center overflow-hidden">
                    <img src="<?php echo BASE_URL; ?>assets/images/mdrmlogo.png" alt="MDRRMO Logo" class="h-7 w-auto">
                </div>
                <span class="text-sm font-semibold tracking-tight">MDRRMO-GLAN</span>
            </div>

            <div class="relative z-10 max-w-md">
                <h2 class="text-3xl font-semibold leading-tight tracking-tight">
                    Coordinate response.<br>
                    <span class="text-slate-300">Resolve incidents faster.</span>
                </h2>
                <p class="mt-4 text-sm text-slate-400">
                    A unified workspace for the Municipal Disaster Risk Reduction and Management Office of GLAN — track,
                    triage and resolve incident reports in real time.
                </p>
            </div>

            <div class="relative z-10 text-xs text-slate-500">
                &copy; <?php echo date('Y'); ?> MDRRMO-GLAN. All rights reserved.
            </div>
        </div>

        <div class="flex items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-sm">

                <div class="lg:hidden flex items-center justify-center gap-2 mb-8">
                    <div class="h-10 w-10 rounded-lg bg-slate-900 ring-1 ring-slate-200 flex items-center justify-center overflow-hidden">
                        <img src="<?php echo BASE_URL; ?>assets/images/mdrmlogo.png" alt="MDRRMO Logo" class="h-7 w-auto">
                    </div>
                    <span class="text-sm font-semibold tracking-tight text-slate-900">MDRRMO-GLAN</span>
                </div>

                <div class="text-center mb-8">
                    <h1 class="text-2xl font-semibold tracking-tight">Sign in to your account</h1>
                    <p class="mt-2 text-sm text-slate-500">
                        Enter your email below to access the incident management dashboard.
                    </p>
                </div>

                <?php if ($error_message): ?>
                <div class="mb-5 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">
                    <i class="fas fa-exclamation-triangle mt-0.5"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
                        <input type="email" id="email" name="email"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:border-slate-900 transition"
                            placeholder="you@example.com"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            required>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                            <a href="#" class="text-xs text-slate-500 hover:text-slate-900">Forgot password?</a>
                        </div>
                        <input type="password" id="password" name="password"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:border-slate-900 transition"
                            placeholder="••••••••" required>
                    </div>

                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2 transition">
                        <i class="fas fa-sign-in-alt"></i>
                        Sign in
                    </button>
                </form>

                <div class="my-6 flex items-center gap-3">
                    <div class="flex-1 h-px bg-slate-200"></div>
                    <span class="text-xs uppercase tracking-wider text-slate-400">Or</span>
                    <div class="flex-1 h-px bg-slate-200"></div>
                </div>

                <a href="../dashboard/responder.php"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <i class="fas fa-user-friends"></i>
                    Continue as Guest
                </a>
                <p class="mt-2 text-center text-xs text-slate-500">
                    Submit incident reports without creating an account.
                </p>

                <div class="mt-8 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] uppercase tracking-wider font-semibold text-slate-500 mb-1.5">Demo credentials</p>
                    <ul class="text-xs text-slate-600 space-y-0.5">
                        <li><span class="font-medium text-slate-700">Admin:</span> admin@incidentmgmt.com / admin123</li>
                        <li><span class="font-medium text-slate-700">Organization:</span> sarah.johnson@cityhospital.com / org123</li>
                    </ul>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
