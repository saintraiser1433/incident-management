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
    <link href="<?php echo BASE_URL; ?>assets/shadcn.css" rel="stylesheet">
    <!-- Load Chart.js early so inline dashboard scripts can access it -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script>
      (function() {
        try {
          var saved = localStorage.getItem('theme') || 'dark';
          document.documentElement.setAttribute('data-theme', saved);
        } catch (e) {}
      })();
    </script>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>">
                <img src="<?php echo BASE_URL; ?>assets/images/mdrmlogo.png" alt="MDRRMO Logo" style="height: 40px; width: auto; margin-right: 10px;">
                <?php echo APP_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-2">
                        <button id="themeToggle" class="btn ui-btn-ghost btn-sm" type="button" title="Toggle theme">
                            <i class="fas fa-moon me-1" id="themeIcon"></i>
                            <span id="themeLabel">Dark</span>
                        </button>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i>
                            <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
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
          var current = document.documentElement.getAttribute('data-theme') || 'dark';
          if (current === 'light') { icon.className = 'fas fa-sun me-1'; label.textContent = 'Light'; }
          else { icon.className = 'fas fa-moon me-1'; label.textContent = 'Dark'; }
        }
        btn && btn.addEventListener('click', function() {
          var current = document.documentElement.getAttribute('data-theme') || 'dark';
          var next = current === 'dark' ? 'light' : 'dark';
          document.documentElement.setAttribute('data-theme', next);
          try { localStorage.setItem('theme', next); } catch (e) {}
          syncUI();
        });
        syncUI();
      })();
    </script>
