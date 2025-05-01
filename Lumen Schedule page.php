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
$stmt->bind_param("i", $user_id); // Fixed: removed extra parameter
if(!$stmt->execute()) {
    die('Execute failed: ' . $stmt->error);
}
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// ... existing code ...

// Fetch tasks for the current user
$tasks_query = "SELECT t.*, c.name as course_name 
                FROM tasks t 
                JOIN courses c ON t.course_id = c.id 
                WHERE t.user_id = ? AND t.status = 'pending'
                ORDER BY t.due_date ASC, t.priority DESC";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("i", $user_id); // Fixed: removed extra parameter
$stmt->execute();
$tasks_result = $stmt->get_result();



// Fetch upcoming events for the current user based on enrolled courses
$events_query = "SELECT 
    e.*,
    c.title as course_title,
    u.name as instructor_name,
    IFNULL(ea.status, 'Not Confirmed') as attendance_status
FROM events e
JOIN courses c ON e.course_id = c.id
JOIN users u ON e.instructor_id = u.id
LEFT JOIN event_attendees ea ON e.id = ea.event_id AND ea.user_id = ?
WHERE e.event_date >= CURDATE()
AND e.course_id IN (
    SELECT course_id 
    FROM user_courses 
    WHERE user_id = ?
)
ORDER BY e.event_date ASC, e.start_time ASC";
$stmt = $conn->prepare($events_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$events_result = $stmt->get_result();

// Fetch class schedules for the current user
$schedule_query = "SELECT cs.*, c.title as course_title, u.name as instructor_name 
                  FROM class_schedules cs 
                  LEFT JOIN courses c ON cs.course_id = c.id 
                  LEFT JOIN users u ON cs.instructor_id = u.id 
                  WHERE cs.course_id IN (
                      SELECT course_id FROM user_courses WHERE user_id = ?
                  ) 
                  ORDER BY cs.day_of_week ASC, cs.start_time ASC";
$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("i", $user_id); // Fixed: removed extra parameter
$stmt->execute();
$schedules_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule | LUMEN - Virtual Classroom</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* You can move this to a separate CSS file later */
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

        /* Calendar Styles */
        .calendar-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .calendar-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .calendar-nav-btn {
            background-color: var(--bg-secondary);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-secondary);
        }

        .calendar-nav-btn:hover {
            background-color: var(--accent-primary);
            color: white;
        }

        .month-selector {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background-color: white;
            cursor: pointer;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }

        .calendar-day-header {
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-secondary);
            padding: 0.75rem 0;
            font-weight: 500;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            background-color: white;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .calendar-day:hover {
            background-color: var(--highlight);
            border-color: rgba(214, 23, 75, 0.2);
        }

        .calendar-day-number {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .calendar-day-events {
            display: flex;
            flex-direction: column;
            gap: 2px;
            width: 100%;
        }

        .calendar-day-event {
            width: 100%;
            height: 4px;
            border-radius: 2px;
            background-color: var(--accent-primary);
        }

        .calendar-day-event.lecture {
            background-color: var(--accent-secondary);
        }

        .calendar-day-event.assignment {
            background-color: #FF9F1C;
        }

        .calendar-day-event.test {
            background-color: #EF4444;
        }

        .calendar-day.today {
            background: linear-gradient(135deg, var(--accent-primary), #e83a6d);
            color: white;
            border-color: var(--accent-primary);
        }

        .calendar-day.today .calendar-day-event {
            background-color: white;
        }

        .calendar-day.other-month {
            color: var(--text-secondary);
            opacity: 0.5;
            background-color: var(--bg-secondary);
        }

        .calendar-day.has-event::after {
            content: '';
            position: absolute;
            top: 4px;
            right: 4px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--accent-primary);
        }

        /* Upcoming Events Section */
        .events-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section-header {
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

        .events-list {
            list-style: none;
        }

        .event-item {
            padding: 1.25rem;
            border-radius: var(--border-radius);
            background-color: var(--bg-primary);
            margin-bottom: 0.75rem;
            display: flex;
            gap: 1rem;
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .event-item:hover {
            background-color: var(--highlight);
            transform: translateX(5px);
        }

        .event-date {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            padding: 0.5rem;
            border-radius: 8px;
            background-color: var(--highlight);
            color: var(--accent-primary);
        }

        .event-day {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .event-month {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .event-content {
            flex: 1;
        }

        .event-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .event-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .event-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .event-meta i {
            font-size: 0.8rem;
        }

        .event-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .event-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            align-self: flex-start;
        }

        .event-badge.lecture {
            background-color: #E3F9F0;
            color: var(--accent-secondary);
        }

        .event-badge.assignment {
            background-color: #FFF4E6;
            color: #FF9F1C;
        }

        .event-badge.test {
            background-color: #FEF2F2;
            color: #EF4444;
        }

        /* Class Schedule */
        .schedule-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .schedule-tabs {
            display: flex;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .schedule-tab {
            padding: 0.75rem 1.25rem;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
            position: relative;
            transition: var(--transition);
        }

        .schedule-tab.active {
            color: var(--accent-primary);
        }

        .schedule-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--accent-primary);
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .schedule-card {
            background-color: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .schedule-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .schedule-time {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .schedule-time i {
            color: var(--accent-primary);
        }

        .schedule-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .schedule-instructor {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .schedule-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        /* Tasks Section */
        .tasks-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
        }

        .task-list {
            list-style: none;
        }

        .task-item {
            padding: 1rem;
            border-radius: var(--border-radius);
            background-color: var(--bg-primary);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .task-item:hover {
            background-color: var(--highlight);
        }

        .task-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .task-checkbox.checked {
            background-color: var(--accent-primary);
            border-color: var(--accent-primary);
            color: white;
        }

        .task-content {
            flex: 1;
        }

        .task-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .task-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .task-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .task-meta i {
            font-size: 0.8rem;
        }

        .task-priority {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .task-priority.high {
            background-color: #FEF2F2;
            color: #EF4444;
        }

        .task-priority.medium {
            background-color: #FFF4E6;
            color: #FF9F1C;
        }

        .task-priority.low {
            background-color: #E3F9F0;
            color: var(--accent-secondary);
        }

        /* Add Task Form */
        .add-task-form,
        .task-input {
            display: none;
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
            .schedule-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
            
            /* Ensure no horizontal overflow */
            .calendar-container,
            .events-container,
            .schedule-container,
            .tasks-container {
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
            
            .calendar-nav {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .event-item {
                flex-direction: column;
            }
            
            .event-date {
                flex-direction: row;
                justify-content: flex-start;
                gap: 1rem;
                width: 100%;
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }
            
            .event-day {
                font-size: 1.2rem;
            }
            
            .schedule-tabs {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .schedule-grid {
                grid-template-columns: 1fr;
            }
            
            /* Fix horizontal scrolling */
            body {
                overflow-x: hidden;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }
            
            .calendar-day-header {
                font-size: 0.75rem;
                padding: 0.5rem 0;
            }
            
            .calendar-day-number {
                font-size: 0.8rem;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
            
            .schedule-tabs {
                padding-bottom: 0.5rem;
                scrollbar-width: none;
            }
            
            .schedule-tabs::-webkit-scrollbar {
                display: none;
            }
            
            .schedule-tab {
                white-space: nowrap;
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .add-task-form {
                flex-direction: column;
            }
            
            .add-task-btn {
                padding: 0.75rem;
            }
        }

        @media (max-width: 400px) {
            /* Prevent any horizontal overflow */
            html, body {
                overflow-x: hidden;
                width: 100%;
            }
            
            .main-content {
                padding: 0.75rem;
            }
            
            .calendar-grid {
                gap: 0.25rem;
            }
            
            .calendar-day {
                padding: 0.25rem;
            }
            
            .event-meta {
                flex-direction: column;
                gap: 0.5rem;
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
            <a href="Lumen Schedule page.php" class="nav-link active">
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
                <i class="fas fa-calendar-alt"></i>
                Schedule
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
        
        <!-- Calendar Section -->
 <!-- Calendar Section -->
 <div class="calendar-container">
            <div class="calendar-header">
                <h2 class="calendar-title">October 2023</h2>
                <div class="calendar-nav">
                    <button class="calendar-nav-btn" id="prevMonth">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <select class="month-selector" id="monthSelector">
                        <option value="0">January</option>
                        <option value="1">February</option>
                        <option value="2">March</option>
                        <option value="3">April</option>
                        <option value="4">May</option>
                        <option value="5">June</option>
                        <option value="6">July</option>
                        <option value="7">August</option>
                        <option value="8">September</option>
                        <option value="9" selected>October</option>
                        <option value="10">November</option>
                        <option value="11">December</option>
                    </select>
                    <select class="month-selector" id="yearSelector">
                        <option value="2022">2022</option>
                        <option value="2023" selected>2023</option>
                        <option value="2024">2024</option>
                    </select>
                    <button class="calendar-nav-btn" id="nextMonth">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="calendar-grid">
                <!-- Day Headers -->
                <div class="calendar-day-header">Sun</div>
                <div class="calendar-day-header">Mon</div>
                <div class="calendar-day-header">Tue</div>
                <div class="calendar-day-header">Wed</div>
                <div class="calendar-day-header">Thu</div>
                <div class="calendar-day-header">Fri</div>
                <div class="calendar-day-header">Sat</div>
                
                <!-- Calendar Days (Dynamically generated in real app) -->
                <!-- Week 1 -->
                <div class="calendar-day other-month">25</div>
                <div class="calendar-day other-month">26</div>
                <div class="calendar-day other-month">27</div>
                <div class="calendar-day other-month">28</div>
                <div class="calendar-day other-month">29</div>
                <div class="calendar-day other-month">30</div>
                <div class="calendar-day">1</div>
                
                <!-- Week 2 -->
                <div class="calendar-day">2</div>
                <div class="calendar-day">3</div>
                <div class="calendar-day">4</div>
                <div class="calendar-day">5</div>
                <div class="calendar-day">6</div>
                <div class="calendar-day">7</div>
                <div class="calendar-day">8</div>
                
                <!-- Week 3 -->
                <div class="calendar-day">9</div>
                <div class="calendar-day">10</div>
                <div class="calendar-day">11</div>
                <div class="calendar-day">12</div>
                <div class="calendar-day">13</div>
                <div class="calendar-day">14</div>
                <div class="calendar-day">15</div>
                
                <!-- Week 4 -->
                <div class="calendar-day">16</div>
                <div class="calendar-day today">17</div>
                <div class="calendar-day has-event">18</div>
                <div class="calendar-day has-event">19</div>
                <div class="calendar-day">20</div>
                <div class="calendar-day">21</div>
                <div class="calendar-day">22</div>
                
                <!-- Week 5 -->
                <div class="calendar-day has-event">23</div>
                <div class="calendar-day">24</div>
                <div class="calendar-day">25</div>
                <div class="calendar-day">26</div>
                <div class="calendar-day">27</div>
                <div class="calendar-day">28</div>
                <div class="calendar-day">29</div>
                
                <!-- Week 6 -->
                <div class="calendar-day">30</div>
                <div class="calendar-day">31</div>
                <div class="calendar-day other-month">1</div>
                <div class="calendar-day other-month">2</div>
                <div class="calendar-day other-month">3</div>
                <div class="calendar-day other-month">4</div>
                <div class="calendar-day other-month">5</div>
            </div>
        </div>
        
        <!-- Upcoming Events Section -->
        <div class="section-container">
    <div class="section-header">
        <h2><i class="fas fa-calendar-alt"></i> Upcoming Events</h2>
        <a href="add_events.php" class="view-all">View All</a>
    </div>
    
    <div class="events-container">
        <?php if ($events_result->num_rows > 0): ?>
            <?php while ($event = $events_result->fetch_assoc()): ?>
                <div class="event-card">
                    <div class="event-date">
                        <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                        <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                    </div>
                    <div class="event-details">
                        <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                        <p class="event-course"><?php echo htmlspecialchars($event['course_title']); ?></p>
                        <p class="event-time">
                            <i class="far fa-clock"></i> 
                            <?php echo date('h:i A', strtotime($event['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($event['end_time'])); ?>
                        </p>
                        <p class="event-location">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php echo htmlspecialchars($event['location']); ?>
                        </p>
                        <p class="event-instructor">
                            <i class="fas fa-user-tie"></i> 
                            <?php echo htmlspecialchars($event['instructor_name']); ?>
                        </p>
                    </div>
                    <div class="event-actions">
                        <span class="event-type <?php echo strtolower($event['event_type']); ?>">
                            <?php echo ucfirst($event['event_type']); ?>
                        </span>
                        <button class="btn-attend" data-event-id="<?php echo $event['id']; ?>">
                            <?php echo ($event['attendance_status'] == 'Attending') ? 'Attending' : 'Attend'; ?>
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-data-message">
                <i class="fas fa-calendar-times"></i>
                <p>No upcoming events found</p>
            </div>
        <?php endif; ?>
    </div>
</div>
        </div>
        
        <!-- Class Schedule Section -->
        <div class="schedule-container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-calendar-day"></i>
                    Weekly Class Schedule
                </h2>
                <div class="schedule-tabs">
                    <div class="schedule-tab active">This Week</div>
                    <div class="schedule-tab">Next Week</div>
                    <div class="schedule-tab">All Classes</div>
                </div>
            </div>
            
            <div class="schedule-grid">
                <?php while($schedule = $schedules_result->fetch_assoc()): ?>
                    <div class="schedule-card">
                        <div class="schedule-time">
                            <i class="far fa-clock"></i>
                            <?php 
                            echo date('g:i A', strtotime($schedule['start_time'])) . ' - ' . 
                                 date('g:i A', strtotime($schedule['end_time']));
                            ?>
                        </div>
                        <h3 class="schedule-title"><?php echo htmlspecialchars($schedule['course_title']); ?></h3>
                        <p class="schedule-instructor">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($schedule['instructor_name']); ?>
                        </p>
                        <div class="schedule-location">
                            <i class="fas fa-location-dot"></i>
                            <?php echo htmlspecialchars($schedule['location']); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
</div>
</div>
</div>

<!-- Tasks Section -->
<div class="tasks-container">
<div class="section-header">
<h2 class="section-title">
<i class="fas fa-tasks"></i>
Tasks
</h2>
</div>

<div class="tasks-list">
<?php while ($task = $tasks_result->fetch_assoc()): ?>
    <div class="task-item">
        <input type="checkbox" class="task-checkbox">
        <div class="task-content">
            <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
            <div class="task-meta">
                <span class="task-course"><?php echo htmlspecialchars($task['course_name']); ?></span>
                <span class="task-due">Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?></span>
            </div>
        </div>
        <span class="task-priority <?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span>
    </div>
<?php endwhile; ?>
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

// Calendar functionality
const monthSelector = document.getElementById('monthSelector');
const yearSelector = document.getElementById('yearSelector');
const prevMonthBtn = document.getElementById('prevMonth');
const nextMonthBtn = document.getElementById('nextMonth');
const calendarTitle = document.querySelector('.calendar-title');
const calendarGrid = document.querySelector('.calendar-grid');

let currentDate = new Date();
if (currentDate.getMonth() === 9) { // October is month 9 (0-indexed)
currentDate = new Date(2023, 9, 17); // Set to October 17, 2023 to match the example
}

// Sample events data
const events = {
'2023-10-18': [{ type: 'test', title: 'Web Technology Midterm Exam', time: '10:00 AM - 11:30 AM', location: 'Online' }],
'2023-10-19': [{ type: 'lecture', title: 'Python Programming Lecture', time: '2:00 PM - 3:30 PM', location: 'Room 302' }],
'2023-10-23': [{ type: 'assignment', title: 'Database Assignment Due', time: '11:59 PM', location: 'LMS Submission' }]
};

// Render calendar
function renderCalendar() {
const year = currentDate.getFullYear();
const month = currentDate.getMonth();

// Update calendar title
calendarTitle.textContent = new Intl.DateTimeFormat('en-US', { 
month: 'long', 
year: 'numeric' 
}).format(currentDate);

// Update selectors
monthSelector.value = month;
yearSelector.value = year;

// Get first day of month and total days in month
const firstDay = new Date(year, month, 1);
const daysInMonth = new Date(year, month + 1, 0).getDate();

// Get days from previous month to show
const prevMonthDays = firstDay.getDay(); // 0 = Sunday, 6 = Saturday

// Get days from next month to show
const totalCells = Math.ceil((daysInMonth + prevMonthDays) / 7) * 7;
const nextMonthDays = totalCells - (daysInMonth + prevMonthDays);

// Clear calendar grid (keep day headers)
const dayHeaders = Array.from(calendarGrid.children).slice(0, 7);
calendarGrid.innerHTML = '';
dayHeaders.forEach(header => calendarGrid.appendChild(header));

// Add days from previous month
const prevMonthLastDay = new Date(year, month, 0).getDate();
for (let i = prevMonthDays - 1; i >= 0; i--) {
const day = prevMonthLastDay - i;
const dayElement = createDayElement(day, true);
calendarGrid.appendChild(dayElement);
}

// Add days from current month
const today = new Date();
for (let day = 1; day <= daysInMonth; day++) {
const date = new Date(year, month, day);
const isToday = date.toDateString() === today.toDateString();
const dayElement = createDayElement(day, false, isToday);

// Check if day has events
const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
if (events[dateStr]) {
dayElement.classList.add('has-event');

// Add event indicators
const eventsContainer = dayElement.querySelector('.calendar-day-events');
events[dateStr].forEach(event => {
const eventDot = document.createElement('div');
eventDot.className = `calendar-day-event ${event.type}`;
eventsContainer.appendChild(eventDot);
});
}

calendarGrid.appendChild(dayElement);
}

// Add days from next month
for (let day = 1; day <= nextMonthDays; day++) {
const dayElement = createDayElement(day, true);
calendarGrid.appendChild(dayElement);
}
}

// Create a day element
function createDayElement(day, isOtherMonth, isToday = false) {
const dayElement = document.createElement('div');
dayElement.className = 'calendar-day';
if (isOtherMonth) dayElement.classList.add('other-month');
if (isToday) dayElement.classList.add('today');

dayElement.innerHTML = `
<div class="calendar-day-number">${day}</div>
<div class="calendar-day-events"></div>
`;

dayElement.addEventListener('click', () => {
showDayEvents(day);
});

return dayElement;
}

// Show events for a specific day
function showDayEvents(day) {
const year = currentDate.getFullYear();
const month = currentDate.getMonth();
const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

if (events[dateStr]) {
const eventList = events[dateStr].map(event => 
`${event.title}\nTime: ${event.time}\nLocation: ${event.location}`
).join('\n\n');

alert(`Events for ${month + 1}/${day}/${year}:\n\n${eventList}`);
} else {
alert(`No events scheduled for ${month + 1}/${day}/${year}`);
}
}

// Event listeners for calendar navigation
prevMonthBtn.addEventListener('click', () => {
currentDate.setMonth(currentDate.getMonth() - 1);
renderCalendar();
});

nextMonthBtn.addEventListener('click', () => {
currentDate.setMonth(currentDate.getMonth() + 1);
renderCalendar();
});

monthSelector.addEventListener('change', () => {
currentDate.setMonth(parseInt(monthSelector.value));
renderCalendar();
});

yearSelector.addEventListener('change', () => {
currentDate.setFullYear(parseInt(yearSelector.value));
renderCalendar();
});

// Task functionality
const taskCheckboxes = document.querySelectorAll('.task-checkbox');


document.querySelectorAll('.task-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const taskItem = this.closest('.task-item');
        if (this.checked) {
            taskItem.style.opacity = '0.5';
            // Here you can add AJAX call to update task status in database
        } else {
            taskItem.style.opacity = '1';
        }
    });
});

function generateCalendar(month, year) {
    const firstDay = new Date(year, month - 1, 1);
    const lastDay = new Date(year, month, 0);
    const startingDay = firstDay.getDay();
    const monthLength = lastDay.getDate();
    
    const calendarGrid = document.getElementById('calendarGrid');
    calendarGrid.innerHTML = '';
    
    // Add day headers
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    days.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-day-header';
        dayHeader.textContent = day;
        calendarGrid.appendChild(dayHeader);
    });
    
    // Add blank spaces for days before the first of the month
    for (let i = 0; i < startingDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day other-month';
        calendarGrid.appendChild(emptyDay);
    }
    
    // Add the days of the month
    const today = new Date();
    for (let i = 1; i <= monthLength; i++) {
        const dayDiv = document.createElement('div');
        dayDiv.className = 'calendar-day';
        if (i === today.getDate() && month === today.getMonth() + 1 && year === today.getFullYear()) {
            dayDiv.classList.add('today');
        }
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'calendar-day-number';
        dayNumber.textContent = i;
        dayDiv.appendChild(dayNumber);
        
        const eventsDiv = document.createElement('div');
        eventsDiv.className = 'calendar-day-events';
        dayDiv.appendChild(eventsDiv);
        
        calendarGrid.appendChild(dayDiv);
    }
}

// Initialize calendar
let currentMonth = new Date().getMonth() + 1;
let currentYear = new Date().getFullYear();
generateCalendar(currentMonth, currentYear);

// Event listeners for navigation
document.getElementById('prevMonth').addEventListener('click', () => {
    currentMonth--;
    if (currentMonth < 1) {
        currentMonth = 12;
        currentYear--;
    }
    generateCalendar(currentMonth, currentYear);
    document.getElementById('monthSelector').value = currentMonth;
});

document.getElementById('nextMonth').addEventListener('click', () => {
    currentMonth++;
    if (currentMonth > 12) {
        currentMonth = 1;
        currentYear++;
    }
    generateCalendar(currentMonth, currentYear);
    document.getElementById('monthSelector').value = currentMonth;
});

document.getElementById('monthSelector').addEventListener('change', (e) => {
    currentMonth = parseInt(e.target.value);
    generateCalendar(currentMonth, currentYear);
});

document.addEventListener('DOMContentLoaded', function() {
                // Get the current date
                let currentDate = new Date();
                let currentMonth = currentDate.getMonth();
                let currentYear = currentDate.getFullYear();
                
                // Get calendar elements
                const calendarTitle = document.querySelector('.calendar-title');
                const prevMonthBtn = document.querySelector('.calendar-nav-btn:first-child');
                const nextMonthBtn = document.querySelector('.calendar-nav-btn:last-child');
                const monthSelector = document.querySelector('.month-selector');
                
                // Update calendar title
                function updateCalendarTitle() {
                    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                        'July', 'August', 'September', 'October', 'November', 'December'];
                    calendarTitle.textContent = `${monthNames[currentMonth]} ${currentYear}`;
                    
                    // Also update the month selector if it exists
                    if (monthSelector) {
                        monthSelector.value = `${monthNames[currentMonth]} ${currentYear}`;
                    }
                }
                
                // Navigate to previous month
                prevMonthBtn.addEventListener('click', function() {
                    currentMonth--;
                    if (currentMonth < 0) {
                        currentMonth = 11;
                        currentYear--;
                    }
                    updateCalendarTitle();
                    loadCalendarData(currentYear, currentMonth);
                });
                
                // Navigate to next month
                nextMonthBtn.addEventListener('click', function() {
                    currentMonth++;
                    if (currentMonth > 11) {
                        currentMonth = 0;
                        currentYear++;
                    }
                    updateCalendarTitle();
                    loadCalendarData(currentYear, currentMonth);
                });
                
                // Function to load calendar data for the selected month
                function loadCalendarData(year, month) {
                    // You can implement AJAX call here to fetch events for the selected month
                    // For now, we'll just update the calendar grid
                    updateCalendarGrid(year, month);
                }
                
                // Function to update the calendar grid
                function updateCalendarGrid(year, month) {
                    const calendarGrid = document.querySelector('.calendar-grid');
                    if (!calendarGrid) return;
                    
                    // Clear existing days (except headers)
                    const dayHeaders = Array.from(calendarGrid.querySelectorAll('.calendar-day-header'));
                    calendarGrid.innerHTML = '';
                    
                    // Add back the day headers
                    dayHeaders.forEach(header => {
                        calendarGrid.appendChild(header);
                    });
                    
                    // Get first day of month and last day of month
                    const firstDay = new Date(year, month, 1);
                    const lastDay = new Date(year, month + 1, 0);
                    
                    // Get the day of week for the first day (0 = Sunday, 6 = Saturday)
                    const firstDayOfWeek = firstDay.getDay();
                    
                    // Add days from previous month
                    const prevMonthLastDay = new Date(year, month, 0).getDate();
                    for (let i = firstDayOfWeek - 1; i >= 0; i--) {
                        const dayElement = createDayElement(prevMonthLastDay - i, true);
                        calendarGrid.appendChild(dayElement);
                    }
                    
                    // Add days for current month
                    const today = new Date();
                    const isCurrentMonth = today.getMonth() === month && today.getFullYear() === year;
                    
                    for (let i = 1; i <= lastDay.getDate(); i++) {
                        const isToday = isCurrentMonth && i === today.getDate();
                        const dayElement = createDayElement(i, false, isToday);
                        calendarGrid.appendChild(dayElement);
                    }
                    
                    // Add days from next month to fill the grid
                    const totalDaysDisplayed = firstDayOfWeek + lastDay.getDate();
                    const remainingCells = 42 - totalDaysDisplayed; // 6 rows x 7 days = 42
                    
                    for (let i = 1; i <= remainingCells; i++) {
                        const dayElement = createDayElement(i, true);
                        calendarGrid.appendChild(dayElement);
                    }
                }
                
                // Function to create a day element
                function createDayElement(dayNumber, isOtherMonth, isToday = false) {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'calendar-day';
                    
                    if (isOtherMonth) {
                        dayElement.classList.add('other-month');
                    }
                    
                    if (isToday) {
                        dayElement.classList.add('today');
                    }
                    
                    const dayNumberElement = document.createElement('div');
                    dayNumberElement.className = 'calendar-day-number';
                    dayNumberElement.textContent = dayNumber;
                    
                    const eventsContainer = document.createElement('div');
                    eventsContainer.className = 'calendar-day-events';
                    
                    dayElement.appendChild(dayNumberElement);
                    dayElement.appendChild(eventsContainer);
                    
                    return dayElement;
                }
                
                // Initialize calendar
                updateCalendarTitle();
                updateCalendarGrid(currentYear, currentMonth);
            });

            document.addEventListener('DOMContentLoaded', function() {
    const calendarGrid = document.querySelector('.calendar-grid');
    const currentMonthDisplay = document.getElementById('currentMonthDisplay');
    const prevMonthBtn = document.getElementById('prevMonth');
    const nextMonthBtn = document.getElementById('nextMonth');
    
    let currentDate = new Date();
    
    function generateCalendar(date) {
        const year = date.getFullYear();
        const month = date.getMonth();
        
        // Update month display
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                          'July', 'August', 'September', 'October', 'November', 'December'];
        currentMonthDisplay.textContent = `${monthNames[month]} ${year}`;
        
        // Clear existing calendar days (except headers)
        const days = calendarGrid.querySelectorAll('.calendar-day');
        days.forEach(day => day.remove());
        
        // Get first day of month and total days
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const totalDays = lastDay.getDate();
        const startingDay = firstDay.getDay();
        
        // Add empty days for padding
        for (let i = 0; i < startingDay; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day other-month';
            calendarGrid.appendChild(emptyDay);
        }
        
        // Add days of the month
        for (let day = 1; day <= totalDays; day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            
            // Check if it's today
            const currentDay = new Date();
            if (day === currentDay.getDate() && 
                month === currentDay.getMonth() && 
                year === currentDay.getFullYear()) {
                dayElement.classList.add('today');
            }
            
            dayElement.innerHTML = `
                <div class="calendar-day-number">${day}</div>
                <div class="calendar-day-events"></div>
            `;
            
            calendarGrid.appendChild(dayElement);
        }
    }
    
    // Generate initial calendar
    generateCalendar(currentDate);
    
    // Add month navigation
    prevMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        generateCalendar(currentDate);
    });
    
    nextMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        generateCalendar(currentDate);
    });
});



// Schedule tabs functionality
const scheduleTabs = document.querySelectorAll('.schedule-tab');

scheduleTabs.forEach(tab => {
tab.addEventListener('click', () => {
scheduleTabs.forEach(t => t.classList.remove('active'));
tab.classList.add('active');
// In a real app, this would load different schedule data
});
});

// Initial render
renderCalendar();

document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.schedule-tab');
    const scheduleCards = document.querySelectorAll('.schedule-card');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');
            
            const day = this.getAttribute('data-day');
            
            // Show/hide relevant schedule cards
            scheduleCards.forEach(card => {
                if (day === 'all' || card.getAttribute('data-day') === day) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>
</body>
</html>