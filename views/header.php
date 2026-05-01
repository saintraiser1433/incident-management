<?php
/**
 * Header Component
 * MDRRMO-GLAN Incident Reporting and Response Coordination System
 */

if (!isset($page_title)) {
    $page_title = APP_NAME;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        corePlugins: { preflight: false },
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif']
            },
            colors: {
              border: 'hsl(214.3 31.8% 91.4%)',
              input: 'hsl(214.3 31.8% 91.4%)',
              ring: 'hsl(222.2 84% 4.9%)',
              background: 'hsl(0 0% 100%)',
              foreground: 'hsl(222.2 84% 4.9%)',
              muted: { DEFAULT: 'hsl(210 40% 96.1%)', foreground: 'hsl(215.4 16.3% 46.9%)' },
              accent: { DEFAULT: 'hsl(210 40% 96.1%)', foreground: 'hsl(222.2 47.4% 11.2%)' }
            },
            borderRadius: { xl: '0.875rem', '2xl': '1rem' },
            boxShadow: {
              'sm-soft': '0 1px 2px 0 rgba(15, 23, 42, 0.04)',
              'card': '0 1px 3px 0 rgba(15, 23, 42, 0.06), 0 1px 2px -1px rgba(15, 23, 42, 0.06)'
            }
          }
        }
      };
    </script>

    <link href="<?php echo BASE_URL; ?>assets/shadcn.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
      (function() {
        try {
          var saved = localStorage.getItem('theme') || 'light';
          document.documentElement.setAttribute('data-theme', saved);
        } catch (e) {}
      })();
    </script>
</head>
<body class="font-sans antialiased text-slate-900">

    <nav class="navbar navbar-expand-lg navbar-light app-navbar sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand flex items-center gap-2" href="<?php echo BASE_URL; ?>">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 ring-1 ring-slate-200 overflow-hidden">
                    <img src="<?php echo BASE_URL; ?>assets/images/mdrmlogo.png" alt="MDRRMO Logo" style="height: 28px; width: auto;">
                </span>
                <span class="brand-text hidden sm:inline text-sm font-semibold tracking-tight text-slate-900">
                    MDRRMO-GLAN <span class="text-slate-400 font-normal">· Incident Management</span>
                </span>
            </a>

            <button class="navbar-toggler border border-slate-200 rounded-lg" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-2">
                    <li class="nav-item">
                        <button id="themeToggle" class="btn ui-btn-ghost btn-sm inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 transition" type="button" title="Toggle theme">
                            <i class="fas fa-moon" id="themeIcon"></i>
                            <span id="themeLabel">Light</span>
                        </button>
                    </li>
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item">
                            <a class="nav-link inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 border border-slate-200 hover:bg-slate-100 hover:border-slate-300 transition"
                               href="<?php echo BASE_URL; ?>auth/logout.php"
                               title="Sign out">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-white text-xs font-semibold">
                                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                                </span>
                                <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Account'); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-card border border-slate-200 rounded-xl">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2 text-slate-400"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2 text-slate-400"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-red-600" href="<?php echo BASE_URL; ?>auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn inline-flex items-center gap-2 bg-slate-900 text-white px-4 py-2 rounded-lg hover:bg-slate-800 transition" href="<?php echo BASE_URL; ?>auth/login.php">
                                <i class="fas fa-sign-in-alt"></i>Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <script>
      (function() {
        var btn = document.getElementById('themeToggle');
        var icon = document.getElementById('themeIcon');
        var label = document.getElementById('themeLabel');
        function syncUI() {
          var current = document.documentElement.getAttribute('data-theme') || 'light';
          if (current === 'light') { icon.className = 'fas fa-sun'; label.textContent = 'Light'; }
          else { icon.className = 'fas fa-moon'; label.textContent = 'Dark'; }
        }
        btn && btn.addEventListener('click', function() {
          var current = document.documentElement.getAttribute('data-theme') || 'light';
          var next = current === 'light' ? 'dark' : 'light';
          document.documentElement.setAttribute('data-theme', next);
          try { localStorage.setItem('theme', next); } catch (e) {}
          syncUI();
        });
        syncUI();
      })();
    </script>
