<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();

// Fetch activity statistics with more detailed metrics
$stats_query = "SELECT 
    COUNT(DISTINCT id) as total_activities,
    SUM(CASE WHEN activity_type = 'course' THEN 1 ELSE 0 END) as total_courses,
    COUNT(CASE WHEN activity_type = 'quiz' THEN 1 END) as total_quizzes,
    COUNT(CASE WHEN DATE(activity_date) = CURDATE() THEN 1 END) as today_activities,
    SUM(duration_minutes) as total_minutes,
    COUNT(CASE WHEN completion_status = 'completed' THEN 1 END) as completed_courses,
    AVG(CASE WHEN activity_type = 'quiz' THEN score ELSE NULL END) as avg_score
FROM user_activities 
WHERE user_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Add activity filtering
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$period = isset($_GET['period']) ? $_GET['period'] : '7days';

// Build the activity query based on filters
$activities_query = "SELECT 
    a.id,
    a.user_id,
    a.activity_type,
    a.activity_date,
    a.duration_minutes,
    a.completion_status,
    a.score
FROM user_activities a
WHERE a.user_id = ? ";

// Add time period filter
switch($period) {
    case 'today':
        $activities_query .= "AND DATE(a.activity_date) = CURDATE() ";
        break;
    case '7days':
        $activities_query .= "AND a.activity_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
        break;
    case '30days':
        $activities_query .= "AND a.activity_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) ";
        break;
}

// Add activity type filter
if ($filter !== 'all') {
    $activities_query .= "AND a.activity_type = ? ";
}

$activities_query .= "ORDER BY a.activity_date DESC LIMIT 10";

// Prepare and execute the query
$stmt = $conn->prepare($activities_query);
if ($filter !== 'all') {
    $stmt->bind_param("is", $user_id, $filter);
} else {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$activities = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Activity | LUMEN - Virtual Classroom</title>
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

        /* Activity Timeline */
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

        .activity-list {
            list-style: none;
        }

        .activity-day-divider {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
            margin: 1.5rem 0 0.75rem;
            position: relative;
            text-align: center;
        }

        .activity-day-divider span {
            background: white;
            padding: 0 1rem;
            position: relative;
            z-index: 1;
        }

        .activity-day-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: rgba(0, 0, 0, 0.05);
            z-index: 0;
        }

        .activity-item {
            padding: 1.25rem;
            border-radius: var(--border-radius);
            background-color: var(--bg-primary);
            margin-bottom: 0.75rem;
            display: flex;
            gap: 1rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .activity-item:hover {
            background-color: var(--highlight);
            transform: translateX(5px);
        }

        .activity-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
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

        .activity-time i {
            font-size: 0.8rem;
        }

        /* Weekly Progress */
        .resources-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .weekly-progress-chart {
            margin-top: 1.5rem;
        }

        .chart-placeholder {
            background-color: var(--bg-secondary);
            height: 200px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
        }

        .chart-legend {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
            justify-content: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

       /* Activity Breakdown */
.activity-container {
    background-color: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.breakdown-grid {
    display: flex;  /* Changed from grid to flex */
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-top: 1.5rem;
    justify-content: flex-start;
}

.breakdown-card {
    flex: 1;  /* Allow cards to grow */
    min-width: 250px;  /* Minimum width for each card */
    max-width: calc(33.333% - 1rem);  /* Maximum width to ensure 3 cards per row */
    background-color: var(--bg-primary);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    transition: var(--transition);
    text-align: center;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

        .breakdown-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .breakdown-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .breakdown-card h3 {
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .breakdown-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .progress-bar {
            height: 6px;
            background-color: var(--bg-secondary);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        /* New: Skill Certification Badges Section */
        .badges-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .badge-card {
            background-color: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .badge-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .badge-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            position: relative;
        }

        .badge-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .badge-level {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
        }

        .badge-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .badge-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* New: Skill Growth Charts Section */
        .growth-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .growth-tabs {
            display: flex;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            margin: 1.5rem 0;
        }

        .growth-tab {
            padding: 0.75rem 1.25rem;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
            position: relative;
            transition: var(--transition);
        }

        .growth-tab.active {
            color: var(--accent-primary);
        }

        .growth-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--accent-primary);
        }

        .growth-chart {
            height: 250px;
            background-color: var(--bg-secondary);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .growth-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
        }

        .growth-metric {
            background-color: var(--bg-primary);
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
        }

        .growth-metric-value {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .growth-metric-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
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

/* Add these responsive styles to the existing CSS in Lumen My Activity page.html */

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
    
    .breakdown-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
    
    .badges-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    }
    
    .growth-metrics {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
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
        overflow-x: hidden;
    }
    
    .mobile-menu-btn {
        display: block;
    }
    
    .header {
        padding-top: 3rem;
    }
    
    /* Fix horizontal scrolling */
    .learning-progress,
    .resources-container,
    .activity-container,
    .badges-container,
    .growth-container {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }
    
    .activity-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .activity-icon {
        margin-bottom: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
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
    
    .breakdown-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .badges-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .activity-item {
        padding: 1rem;
    }
    
    .growth-tabs {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .growth-metrics {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .breakdown-grid {
        grid-template-columns: 1fr;
    }
    
    .badges-grid {
        grid-template-columns: 1fr;
    }
    
    .section-title {
        font-size: 1.3rem;
    }
    
    .growth-tabs {
        padding-bottom: 0.5rem;
        scrollbar-width: none;
    }
    
    .growth-tabs::-webkit-scrollbar {
        display: none;
    }
    
    .growth-tab {
        white-space: nowrap;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
    
    .growth-metrics {
        grid-template-columns: 1fr;
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
    
    .chart-placeholder,
    .growth-chart {
        height: 150px;
    }
}

.filter-controls {
    display: flex;
    gap: 1rem;
}

.filter-controls select {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: var(--border-radius);
    background-color: white;
    font-size: 0.9rem;
    color: var(--text-primary);
    cursor: pointer;
    transition: var(--transition);
}

.filter-controls select:hover {
    border-color: var(--accent-primary);
}

.activity-item {
    opacity: 0;
    transform: translateY(20px);
    animation: fadeIn 0.5s ease forwards;
}

@keyframes fadeIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in {
    animation: fadeIn 0.5s ease forwards;
}

@media (max-width: 400px) {
    .badge-card {
        display: flex;
        flex-direction: row;
        align-items: center;
        text-align: left;
        gap: 1rem;
        padding: 1rem;
        max-width: 100%;
    }
    
    .badge-icon {
        width: 50px;
        height: 50px;
        margin: 0;
        flex-shrink: 0;
    }
    
    .badge-content {
        flex: 1;
        min-width: 0;
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
            <a href="Lumen My learnings page.php" class="nav-link">
                <i class="nav-icon fas fa-book-open"></i>
                <span>My Learnings</span>
            </a>
            <a href="Lumen My activity page.php" class="nav-link active">
                <i class="nav-icon fas fa-chart-line"></i>
                <span>My Activity</span>
                <!-- <span class="badge">5</span> -->
            </a>
            <a href="Lumen Tests page.php" class="nav-link">
                <i class="nav-icon fas fa-clipboard-list"></i>
                <span>Tests</span>
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
            <i class="fas fa-chart-line"></i>
            My Activity
        </h1>
        <div class="user-profile">
            <img src="<?php 
                $photo_path = isset($user_data['profile_photo']) && !empty($user_data['profile_photo']) 
                    ? 'uploads/' . $user_data['profile_photo']  // This will use Alex_img.jpg or hari_img.jpg directly
                    : 'uploads/default.png'; 
                echo htmlspecialchars($photo_path);
            ?>" 
                alt="User" class="user-avatar">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user_data['name']); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($user_data['role']); ?></div>
            </div>
        </div>
    </div>
        
        <!-- Activity Summary Cards -->
        <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo isset($stats['total_courses']) ? $stats['total_courses'] : 0; ?></div>
            <div class="stat-label">Active Courses</div>
            <i class="fas fa-book-open stat-icon"></i>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo isset($stats['total_minutes']) ? $stats['total_minutes'] : 0; ?></div>
            <div class="stat-label">Total Minutes</div>
            <i class="fas fa-clock stat-icon"></i>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo isset($stats['completed_courses']) ? $stats['completed_courses'] : 0; ?></div>
            <div class="stat-label">Completed Courses</div>
            <i class="fas fa-check-circle stat-icon"></i>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo isset($stats['avg_score']) ? number_format($stats['avg_score'], 1) : '0.0'; ?></div>
            <div class="stat-label">Average Score</div>
            <i class="fas fa-star stat-icon"></i>
        </div>
    </div>
        
        <!-- New: Skill Certification Badges Section -->
        <div class="badges-container">
            <h2 class="section-title">
                <i class="fas fa-award"></i>
                Skill Certification Badges
            </h2>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">Certifications earned through completing courses and assessments</p>
            
            <div class="badges-grid">
                <!-- Badge 1 -->
                <div class="badge-card">
                    <div class="badge-icon">
                        <img src="https://cdn-icons-png.flaticon.com/512/2721/2721620.png" alt="Web Development Badge">
                        <div class="badge-level" style="background-color: #FF9F1C;">1</div>
                    </div>
                    <h3 class="badge-title">Web Development</h3>
                    <div class="badge-date">Earned: Oct 2023</div>
                </div>
                
                <!-- Badge 2 -->
                <div class="badge-card">
                    <div class="badge-icon">
                        <img src="https://cdn-icons-png.flaticon.com/512/2721/2721627.png" alt="Python Programming Badge">
                        <div class="badge-level" style="background-color: #3B82F6;">2</div>
                    </div>
                    <h3 class="badge-title">Python Programming</h3>
                    <div class="badge-date">Earned: Sep 2023</div>
                </div>
                
                <!-- Badge 3 -->
                <div class="badge-card">
                    <div class="badge-icon">
                        <img src="https://cdn-icons-png.flaticon.com/512/2721/2721657.png" alt="Database Fundamentals Badge">
                        <div class="badge-level" style="background-color: #10B981;">1</div>
                    </div>
                    <h3 class="badge-title">Database Fundamentals</h3>
                    <div class="badge-date">Earned: Aug 2023</div>
                </div>
                
                <!-- Badge 4 -->
                <div class="badge-card">
                    <div class="badge-icon">
                        <img src="https://cdn-icons-png.flaticon.com/512/2721/2721612.png" alt="JavaScript Basics Badge">
                        <div class="badge-level" style="background-color: #8B5CF6;">3</div>
                    </div>
                    <h3 class="badge-title">JavaScript Basics</h3>
                    <div class="badge-date">Earned: Jul 2023</div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 1.5rem;">
                <button style="padding: 0.75rem 1.5rem; background-color: var(--accent-primary); color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; transition: var(--transition);">
                    View All Certifications
                </button>
            </div>
        </div>
        
        <!-- Skill Growth Section -->
        <div class="growth-container">
            <div class="section-title">
                <i class="fas fa-chart-line"></i>
                Skill Growth Over Time
            </div>
            <p class="section-subtitle">Track your skill development and compare progress across different areas</p>
            
            <div class="growth-tabs">
                <?php
                // Fetch user's enrolled courses/subjects
                $subjects_query = "SELECT DISTINCT c.title 
                    FROM courses c 
                    JOIN user_courses uc ON c.id = uc.course_id 
                    WHERE uc.user_id = ?";
                $stmt = $conn->prepare($subjects_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $subjects_result = $stmt->get_result();
                
                $first = true;
                while ($subject = $subjects_result->fetch_assoc()) {
                    $activeClass = $first ? 'active' : '';
                    echo "<div class='growth-tab {$activeClass}'>" . htmlspecialchars($subject['title']) . "</div>";
                    $first = false;
                }
                ?>
            </div>

            <div class="growth-chart">
                [ Skill Growth Chart Would Appear Here ]
            </div>

            <div class="growth-metrics">
                <div class="growth-metric">
                    <div class="growth-metric-value">76%</div>
                    <div class="growth-metric-label">Current Level</div>
                </div>
                <div class="growth-metric">
                    <div class="growth-metric-value">+22%</div>
                    <div class="growth-metric-label">Last Month</div>
                </div>
                <div class="growth-metric">
                    <div class="growth-metric-value">4.8</div>
                    <div class="growth-metric-label">Avg. Weekly Hours</div>
                </div>
                <div class="growth-metric">
                    <div class="growth-metric-value">87%</div>
                    <div class="growth-metric-label">Completion Rate</div>
                </div>
            </div>
        </div>
        
        <!-- Activity Timeline -->
        <div class="learning-progress">
        <div class="progress-header">
            <h2 class="section-title">
                <i class="fas fa-clock-rotate-left"></i>
                Activity Timeline
            </h2>
            <div class="filters">
                <select onchange="window.location.href='?period=' + this.value + '&filter=<?php echo $filter; ?>'">
                    <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                </select>
                <select onchange="window.location.href='?filter=' + this.value + '&period=<?php echo $period; ?>'">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Activities</option>
                    <option value="course" <?php echo $filter === 'course' ? 'selected' : ''; ?>>Courses</option>
                    <option value="quiz" <?php echo $filter === 'quiz' ? 'selected' : ''; ?>>Quizzes</option>
                </select>
            </div>
        </div>

    <div class="activity-list">
            <?php 
            $current_date = '';
            while ($activity = $activities->fetch_assoc()): 
                $activity_date = date('Y-m-d', strtotime($activity['activity_date']));
                if ($current_date !== $activity_date):
                    $current_date = $activity_date;
            ?>
                <div class="activity-day-divider">
                    <span><?php echo date('F j, Y', strtotime($activity_date)); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="activity-item">
                <div class="activity-icon" style="background-color: var(--highlight);">
                    <i class="fas <?php 
                        echo match($activity['activity_type']) {
                            'course_access' => 'fa-book',
                            'quiz_attempt' => 'fa-question-circle', 
                            default => 'fa-check-circle'
                        };
                    ?>" style="color: var(--accent-primary);"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title"><?php 
                        echo match($activity['activity_type']) {
                            'course_access' => 'Course Access',
                            'quiz_attempt' => 'Quiz Attempt',
                            default => ucfirst($activity['activity_type'])
                        };
                    ?></div>
                    <div class="activity-description">
                        Duration: <?php echo htmlspecialchars($activity['duration_minutes']); ?> minutes
                        <?php if ($activity['score'] !== null): ?>
                            - Score: <?php echo htmlspecialchars($activity['score']); ?>%
                        <?php endif; ?>
                    </div>
                    <div class="activity-time">
                        <i class="far fa-clock"></i>
                        <?php echo date('M j, Y g:i A', strtotime($activity['activity_date'])); ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
</div>
        
        <!-- Weekly Progress -->
        <div class="resources-container">
            <h2 class="section-title">
                <i class="fas fa-chart-bar"></i>
                Weekly Progress
            </h2>
            
            <div class="weekly-progress-chart">
                <!-- This would be replaced with a real chart in production -->
                <div class="chart-placeholder">
                    [ Weekly Activity Chart Would Appear Here ]
                </div>
                <div class="chart-legend">
                    <div class="legend-item">
                        <span class="legend-color" style="background-color: var(--accent-primary);"></span>
                        <span>Learning Time</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color" style="background-color: var(--accent-secondary);"></span>
                        <span>Completed Work</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Activity Breakdown Section -->
        <div class="activity-container">
            <div class="section-title">
                <i class="fas fa-chart-pie"></i>
                Activity Breakdown
            </div>
            
            <div class="breakdown-grid">
                <?php
                // Fetch activity breakdown by activity type
                $breakdown_query = "SELECT 
                    activity_type,
                    COUNT(*) as activity_count,
                    SUM(duration_minutes) as total_duration
                FROM user_activities
                WHERE user_id = ?
                GROUP BY activity_type
                ORDER BY total_duration DESC";

                $stmt = $conn->prepare($breakdown_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $breakdown_result = $stmt->get_result();

                while ($activity = $breakdown_result->fetch_assoc()) {
                    $icon = match($activity['activity_type']) {
                        'course_access' => 'fa-book',
                        'quiz_attempt' => 'fa-question-circle',
                        'database' => 'fa-database',
                        'security' => 'fa-shield-alt',
                        'cloud' => 'fa-cloud',
                        default => 'fa-check-circle'
                    };
                    
                    $hours = floor($activity['total_duration'] / 60);
                    $minutes = $activity['total_duration'] % 60;
                    $duration = sprintf("%dh %02dm", $hours, $minutes);
                    
                    $activity_title = match($activity['activity_type']) {
                        'course_access' => 'Course Access',
                        'quiz_attempt' => 'Quiz Attempt',
                        'database' => 'Database Work',
                        'security' => 'Security Tasks',
                        'cloud' => 'Cloud Computing',
                        default => ucfirst($activity['activity_type'])
                    };
                    ?>
                    <div class="breakdown-card">
                        <div class="breakdown-icon">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($activity_title); ?></h3>
                        <div class="breakdown-stats">
                            <span><?php echo $duration; ?></span>
                            <span><?php echo $activity['activity_count']; ?> Activities</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(($activity['activity_count'] / 10) * 100, 100); ?>%"></div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </main>

    <script>
           // Add this JavaScript to the existing script in Lumen My Activity page.html

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

document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.growth-tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Here you would typically update the chart data based on the selected subject
            // For now, we'll just update the placeholder text
            document.querySelector('.growth-chart').innerHTML = 
                `[ ${this.textContent} Skill Growth Chart Would Appear Here ]`;
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

// Activity item click handler
document.querySelectorAll('.activity-item').forEach(item => {
    item.addEventListener('click', function() {
        const activityTitle = this.querySelector('.activity-title').textContent;
        alert(`Viewing details for: ${activityTitle}\n\nThis would show more details in a real application.`);
    });
});

// Badge card click handler
document.querySelectorAll('.badge-card').forEach(card => {
    card.addEventListener('click', function() {
        const badgeTitle = this.querySelector('.badge-title').textContent;
        alert(`Viewing details for: ${badgeTitle} badge`);
    });
});
        </script>
    <script>
function updateActivities() {
    const period = document.getElementById('periodFilter').value;
    const filter = document.getElementById('activityFilter').value;
    window.location.href = `?period=${period}&filter=${filter}`;
}

// Add smooth animations for activity items
document.addEventListener('DOMContentLoaded', function() {
    const activityItems = document.querySelectorAll('.activity-item');
    activityItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.classList.add('fade-in');
    });
});
</script>

    </body>
    </html>