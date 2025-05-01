<?php
session_start();
require_once 'config.php';
$conn = require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data from database
$user_id = $_SESSION['user_id'];
$user_query = "SELECT name, role, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
if(!$stmt) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param("i", $user_id);
if(!$stmt->execute()) {
    die('Execute failed: ' . $stmt->error);
}
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | LUMEN - Virtual Classroom</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
            :root {
                --sidebar-width: 280px;
                --bg-primary: #FDFDFD;
                --bg-secondary: #F4F4F5;
                --accent-primary: #D6174B;
                --accent-secondary: #5EC4A3;
                --text-primary: #1E1E1E;
                --text-secondary: #6B7280;
                --highlight: #FFF1F4;
                --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
                --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
                --shadow-lg: 0 15px 30px rgba(0, 0, 0, 0.12);
                --border-radius: 12px;
                --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            }

            /* Profile Details Styles */
            .profile-details {
                padding: 1rem 0;
            }

            .detail-group {
                margin-bottom: 1.5rem;
            }

            .detail-group label {
                font-weight: 500;
                color: var(--text-secondary);
                margin-bottom: 0.5rem;
                display: block;
            }

            .detail-value {
                font-size: 1.1rem;
                color: var(--text-primary);
                margin: 0;
            }

            .enrolled-courses {
                margin-top: 1rem;
            }

            .course-item {
                background: var(--bg-secondary);
                border-radius: var(--border-radius);
                padding: 1.25rem;
                margin-bottom: 1rem;
            }

            .course-item h4 {
                margin: 0 0 0.5rem 0;
                color: var(--text-primary);
            }

            .course-item p {
                color: var(--text-secondary);
                font-size: 0.9rem;
                margin-bottom: 1rem;
            }

            .progress-bar {
                height: 8px;
                background: rgba(0, 0, 0, 0.1);
                border-radius: 4px;
                overflow: hidden;
                margin-bottom: 0.5rem;
            }

            .progress {
                height: 100%;
                background: var(--accent-primary);
                border-radius: 4px;
                transition: width 0.3s ease;
            }

            .progress-text {
                font-size: 0.9rem;
                color: var(--text-secondary);
            }
        
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            }
        
            @font-face {
                font-family: 'Inter';
                font-style: normal;
                font-weight: 400;
                src: url(https://fonts.gstatic.com/s/inter/v12/UcCO3FwrK3iLTeHuS_fvQtMwCp50KnMw2boKoduKmMEVuLyfAZ9hiA.woff2) format('woff2');
            }
        
            body {
                background-color: var(--bg-primary);
                color: var(--text-primary);
                line-height: 1.6;
                display: flex;
                min-height: 100vh;
            }
        
            /* Sidebar Styles - Same as home page */
            .sidebar {
                width: var(--sidebar-width);
                background: white;
                padding: 2rem 0;
                position: fixed;
                height: 100vh;
                overflow-y: auto;
                transition: transform 0.3s ease;
                z-index: 1000;
                box-shadow: var(--shadow-md);
                border-right: 1px solid rgba(0, 0, 0, 0.05);
            }
        
            .logo-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 0 1.5rem 2rem;
                margin-bottom: 1rem;
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            }
        
            .logo-img {
                width: 160px;
                margin-bottom: 0.5rem;
            }
        
            .logo-subtitle {
                font-size: 0.85rem;
                color: var(--text-secondary);
                text-align: center;
                font-weight: 400;
                letter-spacing: 0.5px;
            }
        
            .nav-links {
                display: flex;
                flex-direction: column;
                padding: 0 1rem;
            }
        
            .nav-link {
                padding: 0.9rem 1.25rem;
                color: var(--text-secondary);
                text-decoration: none;
                display: flex;
                align-items: center;
                transition: var(--transition);
                border-radius: var(--border-radius);
                margin: 0.25rem 0;
                position: relative;
            }
        
            .nav-link:hover {
                background-color: var(--highlight);
                color: var(--accent-primary);
            }
        
            .nav-link.active {
                background-color: var(--highlight);
                color: var(--accent-primary);
                font-weight: 500;
            }
        
            .nav-link.active::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                height: 100%;
                width: 4px;
                background-color: var(--accent-primary);
                border-radius: 0 var(--border-radius) var(--border-radius) 0;
            }
        
            .nav-icon {
                margin-right: 0.75rem;
                font-size: 1.1rem;
                width: 24px;
                text-align: center;
            }
        
            /* Main Content Styles */
            .main-content {
                flex: 1;
                margin-left: var(--sidebar-width);
                padding: 2.5rem;
                transition: margin 0.3s ease;
            }
        
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 2.5rem;
                flex-wrap: wrap;
                gap: 1.5rem;
            }
        
            .page-title {
                font-size: 1.8rem;
                font-weight: 700;
                color: var(--text-primary);
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
        
            .page-title i {
                color: var(--accent-primary);
            }
        
            .user-profile {
                display: flex;
                align-items: center;
                gap: 1rem;
                background-color: white;
                padding: 0.5rem 1rem 0.5rem 0.5rem;
                border-radius: 50px;
                box-shadow: var(--shadow-sm);
                transition: var(--transition);
            }
        
            .user-profile:hover {
                box-shadow: var(--shadow-md);
                transform: translateY(-2px);
            }
        
            .user-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid var(--highlight);
            }
        
            .user-info {
                display: flex;
                flex-direction: column;
            }
        
            .user-name {
                font-weight: 600;
                color: var(--text-primary);
            }
        
            .user-role {
                font-size: 0.8rem;
                color: var(--text-secondary);
            }
        
            /* Settings Tabs */
            .settings-tabs {
                display: flex;
                gap: 0.5rem;
                margin-bottom: 2rem;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
                padding-bottom: 0.5rem;
                overflow-x: auto;
                scrollbar-width: none;
            }
        
            .settings-tabs::-webkit-scrollbar {
                display: none;
            }
        
            .settings-tab {
                padding: 0.75rem 1.25rem;
                border-radius: var(--border-radius);
                cursor: pointer;
                font-weight: 500;
                color: var(--text-secondary);
                transition: var(--transition);
                white-space: nowrap;
            }
        
            .settings-tab:hover {
                background-color: var(--highlight);
                color: var(--accent-primary);
            }
        
            .settings-tab.active {
                background-color: var(--highlight);
                color: var(--accent-primary);
                font-weight: 600;
            }
        
            /* Settings Sections */
            .settings-section {
                display: none;
            }
        
            .settings-section.active {
                display: block;
            }
        
            /* Settings Cards */
            .settings-card {
                background-color: white;
                border-radius: var(--border-radius);
                padding: 1.75rem;
                box-shadow: var(--shadow-sm);
                transition: var(--transition);
                margin-bottom: 1.5rem;
                border: 1px solid rgba(0, 0, 0, 0.03);
            }
        
            .settings-card:hover {
                transform: translateY(-3px);
                box-shadow: var(--shadow-md);
            }
        
            .settings-title {
                font-size: 1.3rem;
                font-weight: 600;
                margin-bottom: 1.5rem;
                color: var(--text-primary);
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
        
            .settings-title i {
                color: var(--accent-primary);
            }
        
            /* Form Styles */
            .settings-form {
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
            }
        
            .form-group {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
        
            .form-group label {
                font-weight: 500;
                color: var(--text-primary);
                font-size: 0.95rem;
            }
        
            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 0.85rem 1rem;
                border-radius: var(--border-radius);
                border: 1px solid rgba(0, 0, 0, 0.1);
                font-size: 0.95rem;
                transition: var(--transition);
                background-color: var(--bg-primary);
            }
        
            .form-group input:focus,
            .form-group textarea:focus,
            .form-group select:focus {
                outline: none;
                border-color: var(--accent-primary);
                box-shadow: 0 0 0 3px rgba(214, 23, 75, 0.1);
            }
        
            .form-group textarea {
                resize: vertical;
                min-height: 100px;
            }
        
            .form-actions {
                display: flex;
                gap: 1rem;
                justify-content: flex-end;
                margin-top: 1rem;
            }
        
            /* Button Styles */
            .btn {
                padding: 0.6rem 1.25rem;
                border-radius: 6px;
                font-size: 0.85rem;
                font-weight: 500;
                cursor: pointer;
                transition: var(--transition);
                border: none;
            }
        
            .btn-primary {
                background: linear-gradient(90deg, var(--accent-primary), #e83a6d);
                color: white;
                box-shadow: 0 2px 8px rgba(214, 23, 75, 0.3);
            }
        
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(214, 23, 75, 0.4);
            }
        
            .btn-outline {
                background-color: transparent;
                border: 1px solid var(--accent-secondary);
                color: var(--accent-secondary);
            }
        
            .btn-outline:hover {
                background-color: rgba(94, 196, 163, 0.1);
            }
        
            .btn-danger {
                background-color: transparent;
                border: 1px solid #ef4444;
                color: #ef4444;
            }
        
            .btn-danger:hover {
                background-color: rgba(239, 68, 68, 0.1);
            }
        
            /* Toggle Switches */
            .toggle-switch {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 24px;
            }
        
            .toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
        
            .slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: var(--transition);
                border-radius: 24px;
            }
        
            .slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: var(--transition);
                border-radius: 50%;
            }
        
            input:checked + .slider {
                background-color: var(--accent-primary);
            }
        
            input:checked + .slider:before {
                transform: translateX(26px);
            }
        
            /* Notification/Privacy Toggles */
            .notification-toggle,
            .privacy-toggle,
            .connection-toggle {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem 0;
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            }
        
            .notification-toggle:last-child,
            .privacy-toggle:last-child,
            .connection-toggle:last-child {
                border-bottom: none;
            }
        
            .toggle-info h3 {
                font-size: 1rem;
                font-weight: 500;
                color: var(--text-primary);
                margin-bottom: 0.25rem;
            }
        
            .toggle-info p {
                font-size: 0.85rem;
                color: var(--text-secondary);
            }
        
            /* Radio Options */
            .frequency-options {
                display: flex;
                gap: 1.5rem;
                margin-top: 1rem;
            }
        
            .radio-option {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                cursor: pointer;
            }
        
            .radio-option input {
                width: 16px;
                height: 16px;
                accent-color: var(--accent-primary);
            }
        
            .radio-label {
                font-size: 0.9rem;
                color: var(--text-primary);
            }
        
            /* Theme Options */
            .theme-selector {
                display: flex;
                gap: 1.5rem;
                margin-top: 1rem;
            }
        
            .theme-option {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 0.75rem;
                cursor: pointer;
                transition: var(--transition);
                padding: 0.5rem;
                border-radius: var(--border-radius);
            }
        
            .theme-option:hover {
                background-color: var(--highlight);
            }
        
            .theme-option.active {
                background-color: var(--highlight);
            }
        
            .theme-preview {
                width: 80px;
                height: 60px;
                border-radius: 8px;
                border: 2px solid rgba(0, 0, 0, 0.1);
                transition: var(--transition);
            }
        
            .theme-option.active .theme-preview {
                border-color: var(--accent-primary);
            }
        
            .light-theme {
                background: linear-gradient(135deg, #ffffff 50%, #f4f4f5 50%);
            }
        
            .dark-theme {
                background: linear-gradient(135deg, #1e1e1e 50%, #2d2d2d 50%);
            }
        
            .system-theme {
                background: linear-gradient(135deg, #ffffff 50%, #1e1e1e 50%);
            }
        
            .theme-name {
                font-size: 0.9rem;
                font-weight: 500;
            }
        
            /* Layout Options */
            .layout-buttons {
                display: flex;
                gap: 0.75rem;
                margin-top: 1rem;
            }
        
            .layout-buttons button {
                padding: 0.5rem 1rem;
            }
        
            .layout-buttons button.active {
                background-color: var(--highlight);
                color: var(--accent-primary);
                border-color: var(--accent-primary);
            }
        
            /* Danger Zone */
            .danger-zone {
                border: 1px solid rgba(239, 68, 68, 0.2);
                background-color: rgba(239, 68, 68, 0.03);
            }
        
            .danger-zone .settings-title {
                color: #ef4444;
            }
        
            .danger-zone .settings-title i {
                color: #ef4444;
            }
        
            .danger-actions {
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
            }
        
            .danger-action {
                padding: 1.5rem;
                background-color: white;
                border-radius: var(--border-radius);
                box-shadow: var(--shadow-sm);
            }
        
            .danger-action h3 {
                font-size: 1.1rem;
                color: var(--text-primary);
                margin-bottom: 0.5rem;
            }
        
            .danger-action p {
                font-size: 0.9rem;
                color: var(--text-secondary);
                margin-bottom: 1rem;
            }
        
            /* Mobile Menu Button */
            .mobile-menu-btn {
                display: none;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: linear-gradient(135deg, var(--accent-primary), #e83a6d);
                color: white;
                border: none;
                width: 48px;
                height: 48px;
                border-radius: 50%;
                font-size: 1.3rem;
                cursor: pointer;
                box-shadow: var(--shadow-md);
                transition: var(--transition);
            }
        
            .mobile-menu-btn:hover {
                transform: scale(1.05);
            }
        
            /* Responsive Styles */
            @media (max-width: 1200px) {
                .settings-card {
                    padding: 1.5rem;
                }
            }
        
            @media (max-width: 992px) {
                .sidebar {
                    transform: translateX(-100%);
                }
                
                .sidebar.active {
                    transform: translateX(0);
                }
                
                .main-content {
                    margin-left: 0;
                    padding: 1.5rem;
                }
                
                .mobile-menu-btn {
                    display: block;
                }
                
                .header {
                    padding-top: 3rem;
                }
                
                .settings-tabs {
                    gap: 0.25rem;
                }
                
                .settings-tab {
                    padding: 0.5rem 0.75rem;
                    font-size: 0.9rem;
                }
            }
        
            @media (max-width: 768px) {
                .header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 1.5rem;
                }
                
                .user-profile {
                    width: 100%;
                    justify-content: flex-start;
                }
                
                .form-actions {
                    flex-direction: column;
                    width: 100%;
                }
                
                .btn {
                    width: 100%;
                    text-align: center;
                }
                
                .danger-actions {
                    grid-template-columns: 1fr;
                }
                
                .theme-selector {
                    flex-direction: column;
                    align-items: flex-start;
                }
            }
        
            @media (max-width: 576px) {
                .main-content {
                    padding: 1.25rem;
                }
                
                .page-title {
                    font-size: 1.5rem;
                }
                
                .settings-title {
                    font-size: 1.2rem;
                }
                
                .settings-card {
                    padding: 1.25rem;
                }
                
                .notification-toggle,
                .privacy-toggle,
                .connection-toggle {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 1rem;
                }
                
                .frequency-options {
                    flex-direction: column;
                    gap: 0.75rem;
                }
                
                .layout-buttons {
                    flex-direction: column;
                }
            }
        </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Navigation -->
    <nav class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="logo lumen.png" alt="LUMEN Logo" class="logo-img">
            <div class="logo-subtitle">Learning Uplift for Modern Educational Networks</div>
        </div>
        
        <div class="nav-links">
            <a href="lumen_home.php" class="nav-link">
                <i class="nav-icon fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="Lumen My learnings page.php" class="nav-link">
                <i class="nav-icon fas fa-book-open"></i>
                <span>My Learnings</span>
            </a>
            <a href="Lumen My Activity page.php" class="nav-link">
                <i class="nav-icon fas fa-chart-line"></i>
                <span>My Activity</span>
            </a>
            <a href="Lumen Tests page.php" class="nav-link">
                <i class="nav-icon fas fa-clipboard-list"></i>
                <span>Tests</span>
            </a>
            <a href="Lumen Schedule page.php" class="nav-link">
                <i class="nav-icon fas fa-calendar-alt"></i>
                <span>Schedule</span>
            </a>
            <a href="Lumen Settings page.php" class="nav-link active">
                <i class="nav-icon fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <div style="height: 1rem;"></div>
            <a href="Lumen Help center page.php" class="nav-link">
                <i class="nav-icon fas fa-question-circle"></i>
                <span>Help Center</span>
            </a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="header">
            <h1 class="page-title">
                <i class="fas fa-cog"></i>
                Settings
            </h1>
            <div class="user-profile">
                <?php
$photo_path = isset($user['profile_photo']) && !empty($user['profile_photo']) 
    ? 'uploads/' . htmlspecialchars($user['profile_photo'])
    : 'uploads/default.png';
?>
<img src="<?php echo $photo_path; ?>" alt="User Avatar" class="user-avatar">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Settings Tabs -->
        <div class="settings-tabs">
            <div class="settings-tab active" data-tab="account">Account</div>
            <!-- <div class="settings-tab" data-tab="notifications">Notifications</div> -->
            <div class="settings-tab" data-tab="privacy">Privacy</div>
            <!-- <div class="settings-tab" data-tab="appearance">Appearance</div> -->
        </div>
        
        <!-- Account Settings -->
        <div class="settings-section active" id="account-settings">
            <div class="settings-card">
                <h2 class="settings-title">
                    <i class="fas fa-user-circle"></i>
                    Profile Information
                </h2>
                
                <div class="settings-form">
                    <div class="profile-details">
                        <div class="detail-group">
                            <label>Full Name</label>
                            <p class="detail-value"><?php echo htmlspecialchars($user['name']); ?></p>
                        </div>
                        
                        <div class="detail-group">
                            <label>Email Address</label>
                            <p class="detail-value"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        </div>
                        
                        <div class="detail-group">
                            <label>Role</label>
                            <p class="detail-value"><?php echo htmlspecialchars($user['role']); ?></p>
                        </div>

                        <div class="detail-group">
                            <label>Enrolled Courses</label>
                            <div class="enrolled-courses">
                                <?php
                                // Fetch enrolled courses
                                $courses_query = "SELECT c.title, c.description, uc.progress 
                                                FROM courses c 
                                                JOIN user_courses uc ON c.id = uc.course_id 
                                                WHERE uc.user_id = ?";
                                $stmt = $conn->prepare($courses_query);
                                $stmt->bind_param("i", $_SESSION['user_id']);
                                $stmt->execute();
                                $courses_result = $stmt->get_result();
                                
                                if ($courses_result->num_rows > 0) {
                                    while ($course = $courses_result->fetch_assoc()) {
                                        echo '<div class="course-item">';
                                        echo '<h4>' . htmlspecialchars($course['title']) . '</h4>';
                                        echo '<p>' . htmlspecialchars($course['description']) . '</p>';
                                        echo '<div class="progress-bar">';
                                        echo '<div class="progress" style="width: ' . htmlspecialchars($course['progress']) . '%"></div>';
                                        echo '</div>';
                                        echo '<span class="progress-text">' . htmlspecialchars($course['progress']) . '% Complete</span>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<p>No courses enrolled yet.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            


        </div>
        
        <!-- Notification Settings -->
        <div class="settings-section" id="notification-settings">
            <div class="settings-card">
                <h2 class="settings-title">
                    <i class="fas fa-bell"></i>
                    Notification Preferences
                </h2>
                
                <div class="notification-options">
                    <div class="notification-toggle">
                        <div class="toggle-info">
                            <h3>Email Notifications</h3>
                            <p>Receive important updates via email</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-toggle">
                        <div class="toggle-info">
                            <h3>Course Announcements</h3>
                            <p>Get notified about new course materials and announcements</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-toggle">
                        <div class="toggle-info">
                            <h3>Assignment Deadlines</h3>
                            <p>Reminders for upcoming assignment due dates</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-toggle">
                        <div class="toggle-info">
                            <h3>Test Reminders</h3>
                            <p>Alerts for upcoming tests and quizzes</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-toggle">
                        <div class="toggle-info">
                            <h3>Discussion Activity</h3>
                            <p>Notifications for replies to your posts</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="settings-card">
                <h2 class="settings-title">
                    <i class="fas fa-mobile-alt"></i>
                    Push Notifications
                </h2>
                
                <div class="notification-options">
                    <div class="notification-toggle">
                        <div class="toggle-info">
                            <h3>Enable Push Notifications</h3>
                            <p>Receive instant notifications on your device</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-frequency">
                        <h3>Notification Frequency</h3>
                        <div class="frequency-options">
                            <label class="radio-option">
                                <input type="radio" name="frequency" checked>
                                <span class="radio-label">Real-time</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="frequency">
                                <span class="radio-label">Daily Digest</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="frequency">
                                <span class="radio-label">Weekly Summary</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Privacy Settings -->
        <div class="settings-section" id="privacy-settings">
            <div class="settings-card">
                <h2 class="settings-title">
                    <i class="fas fa-shield-alt"></i>
                    Account Actions
                </h2>
                <div class="settings-form">
                    <div class="danger-actions">
                        <div class="danger-action">
                            <h3>Log Out</h3>
                            <p>Sign out from your current session. You'll need to log in again to access your account.</p>
                            <form action="logout.php" method="POST">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-sign-out-alt"></i> Log Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
      
    </main>

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        
        mobileMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(e.target) && e.target !== mobileMenuBtn) {
                    sidebar.classList.remove('active');
                }
            }
        });
        
        // Settings tabs functionality
        const settingsTabs = document.querySelectorAll('.settings-tab');
        const settingsSections = document.querySelectorAll('.settings-section');
        
        settingsTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and sections
                settingsTabs.forEach(t => t.classList.remove('active'));
                settingsSections.forEach(s => s.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding section
                tab.classList.add('active');
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(`${tabId}-settings`).classList.add('active');
            });
        });
        
        // Theme selector functionality
        const themeOptions = document.querySelectorAll('.theme-option');
        themeOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove active class from all theme options
                themeOptions.forEach(opt => opt.classList.remove('active'));
                
                // Add active class to selected theme
                this.classList.add('active');
                
                // Get selected theme
                const selectedTheme = this.getAttribute('data-theme');
                
                // In a real app, this would change the theme
                alert(`Theme changed to: ${selectedTheme}\n\nIn a real application, this would update the UI theme.`);
            });
        });
        
        // Toggle switches functionality
        const toggleSwitches = document.querySelectorAll('.toggle-switch input');
        toggleSwitches.forEach(switchEl => {
            switchEl.addEventListener('change', function() {
                const settingName = this.closest('.notification-toggle, .privacy-toggle, .connection-toggle')
                                      .querySelector('h3').textContent;
                const status = this.checked ? 'enabled' : 'disabled';
                
                // In a real app, this would save the preference
                console.log(`${settingName} ${status}`);
            });
        });
        
        // Radio buttons functionality
        const radioOptions = document.querySelectorAll('.radio-option input');
        radioOptions.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    const optionName = this.nextElementSibling.textContent;
                    const settingName = this.closest('.notification-frequency').querySelector('h3').textContent;
                    
                    // In a real app, this would save the preference
                    console.log(`${settingName} set to: ${optionName}`);
                }
            });
        });
        
        // Layout buttons functionality
        const layoutButtons = document.querySelectorAll('.layout-buttons button');
        layoutButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons in this group
                const buttonGroup = this.closest('.layout-buttons');
                buttonGroup.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Get the setting type and value
                const settingType = this.closest('.layout-toggle').querySelector('h3').textContent;
                const settingValue = this.getAttribute('data-position') || this.getAttribute('data-behavior');
                
                // In a real app, this would save the preference
                console.log(`${settingType} set to: ${settingValue}`);
            });
        });
        
        // Form select elements functionality
        const formSelects = document.querySelectorAll('select');
        formSelects.forEach(select => {
            select.addEventListener('change', function() {
                const settingName = this.previousElementSibling.textContent;
                const selectedValue = this.value;
                
                // In a real app, this would save the preference
                console.log(`${settingName} changed to: ${selectedValue}`);
            });
        });
        
        // Danger zone buttons functionality
        const dangerButtons = document.querySelectorAll('.danger-zone button');
        dangerButtons.forEach(button => {
            button.addEventListener('click', function() {
                const actionType = this.textContent;
                
                // Show confirmation dialog
                const confirmed = confirm(`Are you sure you want to ${actionType}? This action may be irreversible.`);
                
                if (confirmed) {
                    // In a real app, this would perform the action
                    alert(`${actionType} request submitted.\n\nIn a real application, this would trigger the appropriate action.`);
                }
            });
        });
        
        // Initialize default active theme
        document.querySelector('.theme-option[data-theme="light"]').classList.add('active');
    </script>
</body>
</html>