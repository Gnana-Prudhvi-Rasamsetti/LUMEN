<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user data
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch user's courses with lesson count and last lesson
$stmt = $conn->prepare("
    SELECT c.*, 
           uc.progress,
           uc.last_accessed,
           COUNT(DISTINCT l.id) as total_lessons,
           COUNT(DISTINCT ul.lesson_id) as completed_lessons
    FROM courses c
    JOIN user_courses uc ON c.id = uc.course_id
    LEFT JOIN lessons l ON c.id = l.course_id
    LEFT JOIN user_lessons ul ON l.id = ul.lesson_id AND ul.user_id = ?
    WHERE uc.user_id = ?
    GROUP BY c.id
    ORDER BY uc.last_accessed DESC
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch learning resources with proper error handling
$stmt = $conn->prepare("
    SELECT lr.*, c.title as course_title 
    FROM learning_resources lr
    LEFT JOIN courses c ON lr.course_id = c.id
    WHERE lr.course_id IN (
        SELECT course_id FROM user_courses WHERE user_id = ?
    )
    OR lr.course_id IS NULL
    ORDER BY lr.id DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch lessons with completion status
$stmt = $conn->prepare("
    SELECT l.*,
           c.title as course_title,
           CASE WHEN ul.completed = 1 THEN 1 ELSE 0 END as is_completed
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    LEFT JOIN user_lessons ul ON l.id = ul.lesson_id AND ul.user_id = ?
    WHERE l.course_id IN (SELECT course_id FROM user_courses WHERE user_id = ?)
    ORDER BY l.course_id, l.order_number
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recent activities with proper error handling
$stmt = $conn->prepare("
    SELECT ua.id, ua.user_id, ua.activity_type, ua.activity_date, 
           ua.duration_minutes, ua.completion_status, ua.score
    FROM user_activities ua
    WHERE ua.user_id = ? 
    ORDER BY ua.activity_date DESC 
    LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Learnings | LUMEN - Virtual Classroom</title>
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
    
        /* Sidebar Styles */
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
    
        .badge {
            background-color: var(--accent-primary);
            color: white;
            border-radius: 50px;
            padding: 0.25rem 0.6rem;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: auto;
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
    
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
    
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.75rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }
    
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
    
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            transition: var(--transition);
        }
    
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
    
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
    
        .stat-icon {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 1.8rem;
            opacity: 0.1;
            color: var(--accent-primary);
        }
    
        /* Learning Progress Section */
        .learning-progress {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
    
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
    
        .section-title i {
            color: var(--accent-primary);
        }
    
        .view-all {
            color: var(--accent-primary);
            font-weight: 500;
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
        }
    
        .view-all:hover {
            text-decoration: underline;
        }
    
        .progress-tabs {
            display: flex;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
    
        .progress-tab {
            padding: 0.75rem 1.25rem;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
            position: relative;
            transition: var(--transition);
        }
    
        .progress-tab.active {
            color: var(--accent-primary);
        }
    
        .progress-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--accent-primary);
        }
    
        .progress-items {
            display: none;
        }
    
        .progress-items.active {
            display: block;
        }
    
        .progress-item {
            display: flex;
            align-items: center;
            padding: 1.25rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            margin-bottom: 0.75rem;
            background-color: var(--bg-primary);
        }
    
        .progress-item:hover {
            background-color: var(--highlight);
            transform: translateX(5px);
        }
    
        .progress-item-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: var(--highlight);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.25rem;
            color: var(--accent-primary);
            font-size: 1.2rem;
        }
    
        .progress-item-content {
            flex: 1;
        }
    
        .progress-item-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
    
        .progress-item-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
            display: flex;
            gap: 1rem;
        }
    
        .progress-item-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
    
        .progress-item-meta i {
            font-size: 0.8rem;
        }
    
        .progress-item-bar {
            width: 180px;
            margin-left: 1.5rem;
        }
    
        .progress-bar {
            height: 6px;
            background-color: var(--bg-secondary);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
    
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 3px;
            transition: width 0.5s ease;
        }
    
        .progress-percent {
            font-size: 0.85rem;
            color: var(--accent-primary);
            font-weight: 600;
            text-align: right;
            margin-top: 0.25rem;
        }
    
        /* Resources Section */
        .resources-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
    
        .resource-card {
            background-color: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
            text-align: center;
        }
    
        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent-primary);
        }
    
        .resource-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--highlight);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--accent-primary);
            font-size: 1.5rem;
        }
    
        .resource-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
    
        .resource-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
    
        /* Recent Activity */
        .activity-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
        }
    
        .activity-list {
            list-style: none;
        }
    
        .activity-item {
            padding: 1.25rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 1rem;
        }
    
        .activity-item:last-child {
            border-bottom: none;
        }
    
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--highlight);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-primary);
            flex-shrink: 0;
        }
    
        .activity-content {
            flex: 1;
        }
    
        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
    
        .activity-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
    
        .activity-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.25rem;
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
    
/* Responsive Styles - Fixed Horizontal Scrolling */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
    
    .resources-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
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
        width: 100%;
        overflow-x: hidden; /* Prevent horizontal scrolling */
    }
    
    .mobile-menu-btn {
        display: block;
    }
    
    .header {
        padding-top: 3rem;
    }
    
    .progress-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .progress-item-bar {
        width: 100%;
        margin-left: 0;
        margin-top: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
    
    /* Ensure no horizontal overflow */
    .learning-progress,
    .resources-container,
    .activity-container {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
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
    
    .progress-item {
        padding: 1rem;
    }
    
    .progress-item-icon {
        margin-right: 0;
        margin-bottom: 1rem;
    }
    
    .resources-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
    
    .activity-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .activity-icon {
        margin-bottom: 1rem;
    }
    
    /* Fix horizontal scrolling */
    body {
        overflow-x: hidden;
    }
    
    .progress-tabs {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .resources-grid {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .section-title {
        font-size: 1.3rem;
    }
    
    .progress-tabs {
        padding-bottom: 0.5rem;
        scrollbar-width: none;
    }
    
    .progress-tabs::-webkit-scrollbar {
        display: none;
    }
    
    .progress-tab {
        white-space: nowrap;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
    
    .progress-item-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .resource-card {
        padding: 1rem;
        max-width: 100%;
    }
    
    .resource-icon {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
    
    .resource-title {
        font-size: 0.95rem;
    }
    
    .resource-meta {
        font-size: 0.75rem;
    }
    
    .activity-icon {
        width: 36px;
        height: 36px;
        font-size: 1rem;
    }
    
    .activity-title {
        font-size: 1rem;
    }
    
    .activity-description {
        font-size: 0.85rem;
    }
    
    .activity-time {
        font-size: 0.75rem;
    }
    
    /* Ensure content fits */
    .progress-item-content,
    .progress-item-bar {
        width: 100%;
    }
}

@media (max-width: 400px) {
    .resources-grid {
        grid-template-columns: 1fr;
    }
    
    .resource-card {
        display: flex;
        flex-direction: row;
        align-items: center;
        text-align: left;
        gap: 1rem;
        padding: 1rem;
        max-width: 100%;
    }
    
    .resource-icon {
        margin: 0;
        flex-shrink: 0;
    }
    
    .resource-content {
        flex: 1;
        min-width: 0; /* Prevent text overflow */
    }
    
    .progress-item-content {
        width: 100%;
    }
    
    .progress-item-meta span {
        font-size: 0.8rem;
    }
    
    /* Prevent any horizontal overflow */
    html, body {
        overflow-x: hidden;
        width: 100%;
    }
    
    .main-content {
        padding: 0.75rem;
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
            <a href="Lumen My learnings page.php" class="nav-link active">
                <i class="nav-icon fas fa-book-open"></i>
                <span>My Learnings</span>
                <!-- <span class="badge">3</span> -->
            </a>
            <a href="Lumen My Activity page.php" class="nav-link">
                <i class="nav-icon fas fa-chart-line"></i>
                <span>My Activity</span>
            </a>
            <a href="Lumen Tests page.php" class="nav-link">
                <i class="nav-icon fas fa-clipboard-list"></i>
                <span>Tests</span>
                <!-- <span class="badge">1</span> -->
            </a>
            <a href="Lumen Schedule page.php" class="nav-link">
                <i class="nav-icon fas fa-calendar-alt"></i>
                <span>Schedule</span>
            </a>
            <a href="Lumen settings page.php" class="nav-link">
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
                <i class="fas fa-book-open"></i>
                My Learnings
            </h1>
            <div class="user-profile">
                <img src="<?php 
                    $photo_path = isset($user['profile_photo']) && !empty($user['profile_photo']) 
                        ? 'uploads/' . $user['profile_photo']  // This will use the exact filename from database
                        : 'uploads/default.png'; 
                    echo htmlspecialchars($photo_path);
                ?>" 
                    alt="User" class="user-avatar">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
            </div>
        </div>
        
        <?php
        // Calculate user stats
        $activeCourses = 0;
        $lessonsCompleted = 0;
        $certificatesEarned = 0;
        $totalProgress = 0;
        $courseCount = 0;

        foreach ($courses as $course) {
            if ($course['progress'] < 100) {
                $activeCourses++;
            }
            $lessonsCompleted += $course['completed_lessons'];
            if ($course['progress'] == 100) {
                $certificatesEarned++;
            }
            $totalProgress += $course['progress'];
            $courseCount++;
        }

        // Calculate average progress
        $learningProgress = $courseCount > 0 ? round(($totalProgress / $courseCount)) : 0;
        ?>
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $activeCourses; ?></div>
                <div class="stat-label">Active Courses</div>
                <i class="stat-icon fas fa-book-open"></i>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $lessonsCompleted; ?></div>
                <div class="stat-label">Lessons Completed</div>
                <i class="stat-icon fas fa-check-circle"></i>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $certificatesEarned; ?></div>
                <div class="stat-label">Certificates Earned</div>
                <i class="stat-icon fas fa-certificate"></i>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $learningProgress; ?>%</div>
                <div class="stat-label">Learning Progress</div>
                <i class="stat-icon fas fa-chart-line"></i>
            </div>
        </div>
        
        <!-- Learning Progress Section -->
        <div class="learning-progress">
            <div class="progress-header">
                <h2 class="section-title"><i class="fas fa-chart-line"></i> Learning Progress</h2>
                <a href="#" class="view-all">View All</a>
            </div>
            
            <div class="progress-tabs">
                <div class="progress-tab active" data-tab="active">Active Courses</div>
                <div class="progress-tab" data-tab="completed">Completed</div>
                <div class="progress-tab" data-tab="saved">Saved for Later</div>
            </div>

            <!-- Active Courses -->
            <div class="progress-items active" id="active-courses">
                <?php 
                foreach ($courses as $course): 
                    if ($course['progress'] < 100): // Only show incomplete courses
                ?>
                    <div class="progress-item">
                        <div class="progress-item-icon">
                            <?php
                            // Set icon based on course title
                            $icon = 'fa-book';
                            $title = strtolower($course['title']);
                            if (strpos($title, 'web') !== false) $icon = 'fa-globe';
                            else if (strpos($title, 'python') !== false) $icon = 'fa-code';
                            else if (strpos($title, 'database') !== false) $icon = 'fa-database';
                            else if (strpos($title, 'security') !== false) $icon = 'fa-shield-alt';
                            else if (strpos($title, 'network') !== false) $icon = 'fa-network-wired';
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="progress-item-content">
                            <h3 class="progress-item-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="progress-item-meta">
                                <span><i class="fas fa-book"></i> <?php echo $course['total_lessons']; ?> Lessons</span>
                                <span><i class="fas fa-check-circle"></i> <?php echo $course['completed_lessons']; ?> Completed</span>
                            </div>
                        </div>
                        <div class="progress-item-bar">
                            <div class="progress-percent"><?php echo $course['progress']; ?>% Complete</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>

            <!-- Completed Courses -->
            <div class="progress-items" id="completed-courses">
                <?php 
                foreach ($courses as $course): 
                    if ($course['progress'] == 100): // Only show completed courses
                ?>
                    <div class="progress-item">
                        <div class="progress-item-icon">
                            <?php
                            // Same icon logic as above
                            $icon = 'fa-book';
                            $title = strtolower($course['title']);
                            if (strpos($title, 'web') !== false) $icon = 'fa-globe';
                            else if (strpos($title, 'python') !== false) $icon = 'fa-code';
                            else if (strpos($title, 'database') !== false) $icon = 'fa-database';
                            else if (strpos($title, 'security') !== false) $icon = 'fa-shield-alt';
                            else if (strpos($title, 'network') !== false) $icon = 'fa-network-wired';
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="progress-item-content">
                            <h3 class="progress-item-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="progress-item-meta">
                                <span><i class="fas fa-book"></i> <?php echo $course['total_lessons']; ?> Lessons</span>
                                <span><i class="fas fa-check-circle"></i> <?php echo $course['completed_lessons']; ?> Completed</span>
                            </div>
                        </div>
                        <div class="progress-item-bar">
                            <div class="progress-percent">100% Complete</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>

            <!-- Saved for Later -->
            <div class="progress-items" id="saved-courses">
                <!-- Add saved courses logic here if needed -->
            </div>
        </div>
        
        <!-- Resources Section -->
        <div class="resources-container">
            <h2 class="section-title">
                <i class="fas fa-folder-open"></i>
                Learning Resources
            </h2>
            
            <div class="resources-grid">
                <?php foreach ($resources as $resource): 
    $title = isset($resource['title']) ? htmlspecialchars($resource['title']) : 'Untitled Resource';
    $description = isset($resource['description']) ? htmlspecialchars($resource['description']) : '';
    $duration = isset($resource['duration']) ? $resource['duration'] : null;
    $type = isset($resource['resource_type']) ? $resource['resource_type'] : '';
?>
                    <div class="resource-card">
                        <div class="resource-icon">
                            <i class="fas <?php echo getResourceIcon($type); ?>"></i>
                        </div>
                        <div class="resource-content">
                            <h3><?php echo $title; ?></h3>
                            <p><?php echo $description; ?></p>
                            <?php if ($duration): ?>
                                <span class="duration">
                                    <i class="far fa-clock"></i> 
                                    <?php echo $duration; ?> minutes
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Activity Section -->
        <div class="activity-container">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Recent Activity
            </h2>
            
            <div class="activity-list">
    <?php foreach ($activities as $activity): ?>
        <div class="activity-item">
            <div class="activity-icon">
                <i class="fas <?php 
                    echo match($activity['activity_type']) {
                        'course_access' => 'fa-book',
                        'quiz_attempt' => 'fa-question-circle',
                        default => 'fa-check-circle'
                    };
                ?>"></i>
            </div>
            <div class="activity-content">
                <div class="activity-title">
                    <?php echo htmlspecialchars($activity['activity_type']); ?>
                </div>
                <div class="activity-description">
                    Duration: <?php echo htmlspecialchars($activity['duration_minutes']); ?> minutes
                    <?php if ($activity['score'] !== null): ?>
                        - Score: <?php echo htmlspecialchars($activity['score']); ?>
                    <?php endif; ?>
                </div>
                <div class="activity-time">
                    <i class="far fa-clock"></i>
                    <?php echo date('M j, Y g:i A', strtotime($activity['activity_date'])); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
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
        
        // Progress tabs functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.progress-tab');
            const items = document.querySelectorAll('.progress-items');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and items
                    tabs.forEach(t => t.classList.remove('active'));
                    items.forEach(item => item.classList.remove('active'));

                    // Add active class to clicked tab and corresponding items
                    tab.classList.add('active');
                    const tabType = tab.getAttribute('data-tab');
                    document.getElementById(`${tabType}-courses`).classList.add('active');
                });
            });
        });
        
        // Animate progress bars on page load
        document.addEventListener('DOMContentLoaded', () => {
            const progressFills = document.querySelectorAll('.progress-fill');
            
            progressFills.forEach(fill => {
                const targetWidth = fill.style.width;
                fill.style.width = '0';
                
                setTimeout(() => {
                    fill.style.width = targetWidth;
                }, 100);
            });
        });
        
        // Resource card click handler
        document.querySelectorAll('.resource-card').forEach(card => {
            card.addEventListener('click', function(e) {
                e.preventDefault();
                const resourceType = this.querySelector('.resource-title').textContent;
                alert(`Opening ${resourceType} resources\n\nThis would navigate to the resource section in a real application.`);
            });
        });
        
        // Progress item click handler
        document.querySelectorAll('.progress-item').forEach(item => {
            item.addEventListener('click', function() {
                const courseTitle = this.querySelector('.progress-item-title').textContent;
                alert(`Opening course: ${courseTitle}\n\nThis would navigate to the course details page in a real application.`);
            });
        });
    </script>

<?php
// Helper function for resource icons
function getResourceIcon($type) {
    switch ($type) {
        case 'ML Handbook':
            return 'fa-brain';
        case 'Python Guide':
            return 'fa-python';
        case 'Web Dev Resources':
            return 'fa-code';
        default:
            return 'fa-book';
    }
}
?>
</body>
</html>