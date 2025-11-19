<?php 
  // --- (This block should be at the top of every page) ---
  if (session_status() == PHP_SESSION_NONE) {
      session_start();
  }
  if (!isset($conn)) {
      // This assumes config.php is in the parent directory
      include_once(dirname(__DIR__) . "/config.php");
  }
  // --- End of block ---

  $currentPage = $currentPage ?? ''; // For the sidebar
  $pageTitle = $pageTitle ?? 'Dashboard'; // For the header
  $pageSubtitle = $pageSubtitle ?? 'Welcome'; // For the header

  // --- NEW: Fetch Notifications ---
  $unread_count = 0;
  $notifications = [];
  
  if (isset($_SESSION['admin_id']) && $conn) {
      // 1. Get count of unread applications
      $count_res = mysqli_query($conn, "SELECT COUNT(id) as unread_count FROM applications WHERE admin_read = 0");
      if($count_res) {
          $unread_count = mysqli_fetch_assoc($count_res)['unread_count'];
      }

      // 2. Get the 5 most recent unread applications
      $notify_query = "
          SELECT a.id, s.name, a.submitted_at 
          FROM applications a
          JOIN scholarship_students s ON a.student_id = s.id
          WHERE a.admin_read = 0
          ORDER BY a.submitted_at DESC
          LIMIT 5
      ";
      $notify_res = mysqli_query($conn, $notify_query);
      if($notify_res) {
          while($row = mysqli_fetch_assoc($notify_res)) {
              $notifications[] = $row;
          }
      }
  }
  // --- END NEW ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo htmlspecialchars($pageTitle); ?> - Admin Portal</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/style.css">

</head>
<body>
    
    <?php 
    // Include the sidebar
    include('sidebar.php'); 
    ?>
    
    <main class="main-content">
        
        <header class="main-header">
            <div class="header-left">
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p><?php echo htmlspecialchars($pageSubtitle); ?></p>
            </div>
            
            <div class="header-right">

                <div class="header-actions">
                    <div class="theme-switcher-container">
                        <label class="theme-switcher">
                            <input type="checkbox" id="themeToggle">
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-dropdown" id="notification-dropdown">
                        <button type="button" class="action-icon" id="notification-toggle-btn" title="Notifications">
                            <i class="fa-solid fa-bell"></i>
                            
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-dot" id="notification-dot"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <div class="dropdown-menu">
                            <div class="dropdown-header">
                                <h3>Notifications</h3>
                            </div>
                            <div class="notification-list" id="notification-list">
                                <?php if (empty($notifications)): ?>
                                    <div class="notification-item-empty">
                                        You're all caught up!
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $note): ?>
                                    <a href="edit_application.php?id=<?= $note['id'] ?>" class="notification-item">
                                        <div class="notification-icon">
                                            <i class="fa-solid fa-file-invoice"></i>
                                        </div>
                                        <div class="notification-content">
                                            <p>New application from <strong><?= htmlspecialchars($note['name']) ?></strong></p>
                                            <small><?= date("d M Y, h:i A", strtotime($note['submitted_at'])) ?></small>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-footer">
                                <a href="view_applications.php">View All Applications</a>
                            </div>
                        </div>
                    </div>
                    </div>

                <div class="user-profile-dropdown" id="user-profile-dropdown">
                    <button type="button" class="user-profile-toggle" id="user-profile-toggle-btn">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['admin_username'] ?? 'A'); ?>&background=4338ca&color=fff&rounded=true" alt="User Avatar">
                        <div class="user-info">
                            <span><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                            <small><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'User'); ?></small>
                        </div>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    
                    <div class="dropdown-menu">
                        <a href="settings.php">
                            <i class="fa-solid fa-gear"></i>
                            <span>Account Settings</span>
                        </a>
                        <a href="logout.php" class="logout-link">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

    