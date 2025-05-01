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

// Modified test statistics query to match your database structure
$stats_query = "SELECT 
    SUM(CASE WHEN completion_date IS NULL THEN 1 ELSE 0 END) as upcoming_tests,
    ROUND(AVG(CASE WHEN completion_date IS NOT NULL THEN score ELSE NULL END), 2) as average_score,
    SUM(CASE WHEN completion_date IS NOT NULL THEN 1 ELSE 0 END) as completed_tests,
    SUM(CASE WHEN score = 100 THEN 1 ELSE 0 END) as perfect_scores
FROM user_tests 
WHERE user_id = ?";

$stmt = $conn->prepare($stats_query);
if(!$stmt) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    // Log error
    error_log("Database query failed: " . $stmt->error);
    // Handle error gracefully
    $stats = [
        'upcoming_tests' => 0,
        'average_score' => 0,
        'completed_tests' => 0,
        'perfect_scores' => 0
    ];
} else {
    $stats_result = $stmt->get_result();
    $stats = $stats_result->fetch_assoc();
}

// Modified upcoming tests query to match your database structure
$upcoming_tests_query = "SELECT
    t.title,
    t.course_id,
    c.name as course_name,
    c.category
FROM tests t
JOIN courses c ON t.course_id = c.id
WHERE t.id IN (SELECT test_id FROM user_tests WHERE user_id = ? AND completion_date IS NULL)
ORDER BY t.id ASC
LIMIT 3";

$stmt = $conn->prepare($upcoming_tests_query);
if(!$stmt) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param("i", $user_id);
if(!$stmt->execute()) {
    die('Execute failed: ' . $stmt->error);
}
$upcoming_tests_result = $stmt->get_result();

// Fetch user's recent tests with course information
$recent_tests_query = "SELECT 
    t.title,
    t.course_id,
    c.name as course_name,
    ut.completion_date as date,
    ut.score,
    CASE WHEN ut.completion_date IS NOT NULL THEN 'Completed' ELSE 'Upcoming' END as status
FROM user_tests ut
JOIN tests t ON ut.test_id = t.id
JOIN courses c ON t.course_id = c.id
WHERE ut.user_id = ?
ORDER BY ut.completion_date DESC, t.id ASC
LIMIT 5";

$stmt = $conn->prepare($recent_tests_query);
if(!$stmt) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param("i", $user_id);
if(!$stmt->execute()) {
    die('Execute failed: ' . $stmt->error);
}
$recent_tests_result = $stmt->get_result();

// Fetch test preparation resources based on user's courses
$resources_query = "SELECT 
    c.id as course_id,
    c.name as course_name,
    c.instructor_id,
    u.name as instructor_name,
    COUNT(DISTINCT t.id) as practice_tests
FROM user_courses uc
JOIN courses c ON uc.course_id = c.id
JOIN users u ON c.instructor_id = u.id
LEFT JOIN tests t ON c.id = t.course_id
WHERE uc.user_id = ?
GROUP BY c.id, c.name, c.instructor_id, u.name";

$stmt = $conn->prepare($resources_query);
if(!$stmt) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param("i", $user_id);
if(!$stmt->execute()) {
    die('Execute failed: ' . $stmt->error);
}
$resources_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tests | LUMEN - Virtual Classroom</title>
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
    
        /* Section Container */
        .section-container {
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
    
        /* Test Cards Grid */
        .tests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
    
        .test-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
            cursor: pointer;
        }
    
        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
    
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1.25rem;
            background-color: var(--bg-secondary);
            position: relative;
        }
    
        .test-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    
        .test-badge.upcoming {
            background-color: #EFF6FF;
            color: #3B82F6;
        }
    
        .test-date {
            background-color: white;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
    
        .test-day {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent-primary);
        }
    
        .test-month {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
    
        .test-content {
            padding: 1.25rem;
        }
    
        .test-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }
    
        .test-course {
            color: var(--accent-primary);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
    
        .test-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
    
        .test-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
    
        .test-meta i {
            font-size: 0.9rem;
        }
    
        .test-actions {
            display: flex;
            gap: 0.75rem;
        }
    
        /* Test Table */
        .tests-table {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
    
        .table-header {
            background-color: var(--bg-secondary);
        }
    
        .table-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;
            padding: 1rem 1.25rem;
            align-items: center;
        }
    
        .table-body .table-row {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }
    
        .table-body .table-row:hover {
            background-color: var(--highlight);
        }
    
        .table-cell {
            padding: 0.5rem;
        }
    
        .test-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
    
        .test-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .instructor-name {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            font-style: italic;
        }
    
        .score-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            width: fit-content;
        }
    
        .score-badge.excellent {
            background-color: #E3F9F0;
            color: var(--accent-secondary);
        }
    
        .score-badge.good {
            background-color: #FFF4E6;
            color: #FF9F1C;
        }
    
        .score-badge.perfect {
            background-color: #EFF6FF;
            color: #3B82F6;
        }
    
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            width: fit-content;
        }
    
        .status-badge.completed {
            background-color: #E3F9F0;
            color: var(--accent-secondary);
        }
    
        /* Resources Grid */
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
    
        .resource-item {
            text-align: center;
            margin-bottom: 2rem;
        }

        .resource-item i {
            font-size: 2rem;
            color: var(--accent-primary);
            margin-bottom: 1rem;
        }

        .course-header {
            background: var(--bg-secondary);
            margin: -2rem -2rem 2rem -2rem;
            padding: 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .course-title {
            font-size: 1.3rem;
            color: var(--text-primary);
            font-weight: 600;
            margin: 0;
        }

        .resource-item h4 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .resource-item p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .resource-card {
            background-color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .course-name {
            font-size: 1.4rem;
            color: var(--accent-primary);
            margin-bottom: 2rem;
            text-align: center;
            font-weight: 600;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary {
            background-color: var(--accent-primary);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
    
        /* Buttons */
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
    
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
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
        .modal {
  display: none; /* Hidden by default */
  position: fixed; /* Stay in place */
  z-index: 1; /* Sit on top */
  left: 50%; /* Center horizontally */
  top: 50%; /* Center vertically */
  transform: translate(-50%, -50%); /* Adjust position to center */
  width: 300px; /* Set a fixed width */
  height: 200px; /* Set a fixed height */
  background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
  box-shadow: var(--shadow-md);
  border-radius: var(--border-radius);
}

.modal-content {
  background-color: #fefefe;
  padding: 20px;
  border: 1px solid #888;
  width: 100%; /* Full width of modal */
  height: 100%; /* Full height of modal */
  box-shadow: var(--shadow-md);
  border-radius: var(--border-radius);
  overflow: auto; /* Enable scroll if needed */
}

.close {
  color: #aaa;
  float: right;
  font-size: 28px;
  font-weight: bold;
}

.close:hover,
.close:focus {
  color: black;
  text-decoration: none;
  cursor: pointer;
}
    
        /* Responsive Styles */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }
            
            .tests-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
                overflow-x: hidden;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .header {
                padding-top: 3rem;
            }
            
            /* Fix table layout */
            .table-row {
                grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            }
            
            .table-row .table-cell:last-child {
                display: none;
            }
            
            /* Ensure no horizontal overflow */
            .section-container {
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
            
            .test-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
            
            .table-row {
                grid-template-columns: 2fr 1fr 1fr 1fr;
            }
            
            .table-row .table-cell:nth-last-child(2) {
                display: none;
            }
        }
    
        @media (max-width: 576px) {
            .main-content {
                padding: 1.25rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tests-grid {
                grid-template-columns: 1fr;
            }
            
            .resources-grid {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
            
            .table-row {
                grid-template-columns: 2fr 1fr 1fr;
                padding: 0.75rem;
            }
            
            .table-row .table-cell:nth-last-child(3) {
                display: none;
            }
            
            .test-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .resource-card {
                padding: 1rem;
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
        }
    
        @media (max-width: 400px) {
            .resources-grid {
                grid-template-columns: 1fr;
            }
            
            .table-row {
                grid-template-columns: 2fr 1fr;
            }
            
            .table-row .table-cell:nth-last-child(4) {
                display: none;
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
            <a href="Lumen My Activity page.php" class="nav-link">
                <i class="nav-icon fas fa-chart-line"></i>
                <span>My Activity</span>
            </a>
            <a href="Lumen Tests page.php" class="nav-link active">
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
                <i class="fas fa-clipboard-list"></i>
                Tests
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
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['upcoming_tests']; ?></div>
                <div class="stat-label">Upcoming Tests</div>
                <i class="fas fa-calendar stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['average_score'], 0); ?>%</div>
                <div class="stat-label">Average Score</div>
                <i class="fas fa-chart-line stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['completed_tests']; ?></div>
                <div class="stat-label">Completed Tests</div>
                <i class="fas fa-check-circle stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['perfect_scores']; ?></div>
                <div class="stat-label">Perfect Scores</div>
                <i class="fas fa-star stat-icon"></i>
            </div>
        </div>
        
        <!-- Upcoming Tests Section -->
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    Upcoming Tests
                </h2>
                <a href="#" class="view-all">View All</a>
            </div>
            
            <div class="tests-grid">
            <?php while($test = $upcoming_tests_result->fetch_assoc()): 
                $test_date = new DateTime($test['test_date']);
            ?>
            <div class="test-card">
                <div class="test-header">
                    <span class="test-badge upcoming">Upcoming</span>
                    <div class="test-date">
                        <div class="test-day"><?php echo $test_date->format('d'); ?></div>
                        <div class="test-month"><?php echo $test_date->format('M'); ?></div>
                    </div>
                </div>
                <div class="test-content">
                    <h3 class="test-title"><?php echo htmlspecialchars($test['title']); ?></h3>
                    <div class="test-course"><?php echo htmlspecialchars($test['course_name']); ?></div>
                    <div class="test-meta">
                        <span><i class="fas fa-clock"></i> <?php echo $test['duration']; ?> mins</span>
                        <span><i class="fas fa-question-circle"></i> <?php echo $test['questions']; ?> Questions</span>
                    </div>
                    <div class="test-actions">
                        <button class="btn btn-primary">Start Test</button>
                        <button class="btn btn-outline">View Details</button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        </div>
        
        <!-- Recent Tests Section -->
<div class="section-container">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-clock"></i>
            Recent Tests
        </h2>
        <a href="all_tests.php" class="view-all">View All</a>
    </div>
    <div class="tests-table">
        <div class="table-header table-row">
            <div class="table-cell">Test Name</div>
            <div class="table-cell">Course</div>
            <div class="table-cell">Date</div>
            <div class="table-cell">Score</div>
            <div class="table-cell">Status</div>
            <div class="table-cell">Actions</div>
        </div>
        <div class="table-body">
            <?php while($test = $recent_tests_result->fetch_assoc()): ?>
            <div class="table-row">
                <div class="table-cell">
                    <div class="test-name"><?php echo htmlspecialchars($test['title']); ?></div>
                    <div class="test-meta">
                        <span><i class="far fa-clock"></i> <?php echo $test['duration'] ?? '25'; ?> mins</span>
                        <span><i class="far fa-question-circle"></i> <?php echo $test['questions'] ?? '20'; ?> Qs</span>
                    </div>
                </div>
                <div class="table-cell"><?php echo htmlspecialchars($test['course_name']); ?></div>
                <div class="table-cell"><?php echo $test['date'] ? date('M d, Y', strtotime($test['date'])) : 'Upcoming'; ?></div>
                <div class="table-cell">
                    <?php if($test['score']): ?>
                    <div class="score-badge <?php echo $test['score'] == 100 ? 'perfect' : ($test['score'] >= 90 ? 'excellent' : 'good'); ?>">
                        <?php echo $test['score']; ?>%
                    </div>
                    <?php endif; ?>
                </div>
                <div class="table-cell">
                    <div class="status-badge <?php echo strtolower($test['status']); ?>">
                        <?php echo $test['status']; ?>
                    </div>
                </div>
                <div class="table-cell">
                    <button class="btn btn-sm <?php echo $test['status'] == 'Completed' ? 'btn-outline' : 'btn-primary'; ?>">
                        <?php echo $test['status'] == 'Completed' ? 'Review' : 'Start'; ?>
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Test Preparation Resources Section -->
<div class="section-container">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-book"></i>
            Test Preparation Resources
        </h2>
    </div>
    <div class="resources-grid">
    <?php if ($resources_result && $resources_result->num_rows > 0): ?>
        <?php while($resource = $resources_result->fetch_assoc()): ?>
            <div class="resource-card">
                <div class="course-header">
                    <h3 class="course-title"><?php echo htmlspecialchars($resource['course_name']); ?></h3>
                    <p class="instructor-name">Instructor: <?php echo htmlspecialchars($resource['instructor_name']); ?></p>
                </div>
                <div class="resource-item">
                    <i class="fas fa-file-alt"></i>
                    <h4>Practice Tests</h4>
                    <p><?php echo $resource['practice_tests']; ?> Available</p>
                    <button class="btn btn-primary btn-sm">Start Practice</button>
                </div>
                <div class="resource-item">
                    <i class="fas fa-video"></i>
                    <h4>Revision Videos</h4>
                    <p><?php echo isset($resource['revision_videos']) ? $resource['revision_videos'] : '0'; ?> Videos</p>
                    <button class="btn btn-primary btn-sm">Watch Now</button>
                </div>
                <div class="resource-item">
                    <i class="fas fa-question-circle"></i>
                    <h4>Question Banks</h4>
                    <p><?php echo isset($resource['question_banks']) ? $resource['question_banks'] : '0'; ?>+ Questions</p>
                    <button class="btn btn-primary btn-sm">Browse</button>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="grid-column: 1 / -1; text-align: center; color: var(--text-secondary); padding: 2rem;">No test preparation resources found for your enrolled courses.</p>
    <?php endif; ?>
</div>
</div>
<div id="myModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Videos</h2>
    <p>This will show you the resources in a real website</p>
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
        
        // Test card click handlers
        document.querySelectorAll('.test-card').forEach(card => {
            card.addEventListener('click', function() {
                const testTitle = this.querySelector('.test-title').textContent;
                alert(`Viewing details for: ${testTitle}\n\nThis would show test details in a real application.`);
            });
        });
        
        // Table row click handlers
        document.querySelectorAll('.table-row').forEach(row => {
            if (!row.classList.contains('table-header')) {
                row.addEventListener('click', function() {
                    const testName = this.querySelector('.test-name').textContent;
                    alert(`Viewing results for: ${testName}\n\nThis would show test results in a real application.`);
                });
            }
        });
        
        // Resource card click handlers
        document.querySelectorAll('.resource-card').forEach(card => {
            card.addEventListener('click', function() {
                const resourceTitle = this.querySelector('.resource-title').textContent;
                alert(`Accessing: ${resourceTitle}\n\nThis would open the resource in a real application.`);
            });
        });

        // Get the modal
var modal = document.getElementById("myModal");

// Get the button that opens the modal
var btns = document.querySelectorAll(".btn-primary");

// Get the <span> element that closes the modal
var span = document.getElementsByClassName("close")[0];

// When the user clicks the button, open the modal 
btns.forEach(btn => {
  btn.onclick = function() {
    modal.style.display = "block";
  }
});

// When the user clicks on <span> (x), close the modal
span.onclick = function() {
  modal.style.display = "none";
}

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
  if (event.target == modal) {
    modal.style.display = "none";
  }
}
    </script>
</body>
</html>