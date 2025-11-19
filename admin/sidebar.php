<?php
// This check prevents an error if a page doesn't set $currentPage
if (!isset($currentPage)) {
    $currentPage = '';
}

// Get the user's role from the session. Default to 'guest' if not set.
$userRole = $_SESSION['admin_role'] ?? 'guest';

// Get the user's name to display
$admin_username = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin User');
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Scholar<span class="text-primary">Portal</span></h2>
    </div>

    <ul class="nav-links">
        
        <li class="nav-category">Main Menu</li>
        <li>
            <a href="dashboard.php" class="<?= ($currentPage === 'dashboard') ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="view_applications.php" class="<?= ($currentPage === 'applications') ? 'active' : '' ?>">
                <i class="fa-solid fa-file-lines"></i>
                <span>View Applications</span>
            </a>
        </li>
        <li>
            <a href="reports.php" class="<?= ($currentPage === 'reports') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </li>

        <?php if ($userRole === 'Admin'): ?>
            <li class="nav-category">Administration</li>
            <li>
                <a href="upload_students.php" class="<?= ($currentPage === 'fresh_upload') ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Fresh Applications</span>
                </a>
            </li>
            <li>
                <a href="college.php" class="<?= ($currentPage === 'colleges') ? 'active' : '' ?>">
                    <i class="fa-solid fa-school"></i>
                    <span>Manage Colleges</span>
                </a>
            </li>
            <li>
                <a href="programs.php" class="<?= ($currentPage === 'programs') ? 'active' : '' ?>">
                    <i class="fa-solid fa-book"></i>
                    <span>Manage Programs</span>
                </a>
            </li>
            <li>
                <a href="map_programs.php" class="<?= ($currentPage === 'mapping') ? 'active' : '' ?>">
                    <i class="fa-solid fa-route"></i>
                    <span>Program Mapping</span>
                </a>
            </li>
            <li>
                <a href="scholarships.php" class="<?= ($currentPage === 'scholarships') ? 'active' : '' ?>">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span>Manage Scholarships</span>
                </a>
            </li>
            <li>
                <a href="academic_year_management.php" class="<?= ($currentPage === 'academic_year_management') ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Academic Year Management</span>
                </a>
            </li>
             
            <li>
                <a href="bulk_upload_applications.php" class="<?= ($currentPage === 'bulk_upload') ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-upload"></i>
                    <span>Bulk Upload</span>
                </a>
            </li>
            <li>
                <a href="user_management.php" class="<?= ($currentPage === 'users') ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-cog"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            <?php endif; ?>
        
    </ul>
    
    </aside>