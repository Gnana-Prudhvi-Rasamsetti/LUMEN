<?php
session_start();
$conn = new mysqli("localhost", "root", "", "lumen_database");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Remove this line:
// $_SESSION['user_id'] = 1; // Change this to 2 to test Hari

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$user_sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch course stats
$stats = array();

// Get active courses count
$courses_sql = "SELECT COUNT(*) as count FROM user_courses WHERE user_id = ?";
$stmt = $conn->prepare($courses_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['active_courses'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get completed lessons count
$lessons_sql = "SELECT COUNT(*) as count FROM user_lessons WHERE user_id = ? AND completed = 1";
$stmt = $conn->prepare($lessons_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['completed_lessons'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get upcoming tests count
$tests_sql = "SELECT COUNT(*) as count FROM tests t 
              JOIN user_courses uc ON t.course_id = uc.course_id 
              WHERE uc.user_id = ? AND t.id NOT IN (
                  SELECT test_id FROM user_tests WHERE user_id = ?
              )";
$stmt = $conn->prepare($tests_sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$stats['upcoming_tests'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Remove these redundant lines that were causing the error
// $user_result = $conn->query($user_sql);
// $user = $user_result->fetch_assoc();

// Remove this line as user_stats table is not in our new database schema
// $stats_sql = "SELECT * FROM user_stats WHERE user_id = $user_id";
// $stats_result = $conn->query($stats_sql);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUMEN - Virtual Classroom</title>
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

        /* Course Cards */
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .section-title i {
            color: var(--accent-primary);
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .course-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .course-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .course-content {
            padding: 1.5rem;
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .course-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .course-instructor {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .course-badge {
            background-color: var(--highlight);
            color: var(--accent-primary);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .course-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .progress-container {
            margin-bottom: 1.5rem;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
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

        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .course-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .course-actions {
            display: flex;
            gap: 0.75rem;
        }

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

        /*Announcements Section */
        .announcements-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .announcement-item {
            padding: 1.25rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-item:hover {
            background-color: var(--highlight);
            border-radius: var(--border-radius);
            padding: 1.25rem;
            margin: 0 -1.25rem;
        }

        .announcement-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-primary);
        }

        .announcement-title i {
            color: var(--accent-primary);
        }

        .announcement-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }

        .announcement-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
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
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }
            
            .courses-grid {
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
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .header {
                padding-top: 3rem;
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
            
            .course-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.25rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                font-size: 1.3rem;
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
            <a href="lumen_home.php" class="nav-link active">
                <i class="nav-icon fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="Lumen My learnings page.php" class="nav-link">
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
                <i class="fas fa-home"></i>
                Dashboard
            </h1>
            <div class="user-profile">
                <img src="<?php 
                    // Use the exact image filenames as stored in the database
                    $photo_path = isset($user['profile_photo']) && !empty($user['profile_photo']) 
                        ? 'uploads/' . $user['profile_photo']  // This will use Alex_img.jpg or hari_img.jpg directly
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
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_courses']; ?></div>
                <div class="stat-label">Active Courses</div>
                <i class="stat-icon fas fa-book"></i>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['completed_lessons']; ?></div>
                <div class="stat-label">Completed Lessons</div>
                <i class="stat-icon fas fa-check-circle"></i>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['upcoming_tests']; ?></div>
                <div class="stat-label">Upcoming Tests</div>
                <i class="stat-icon fas fa-clipboard-list"></i>
            </div>
        </div>

        <!-- My Courses Section -->
        <div class="section-title">
            <i class="fas fa-graduation-cap"></i>
            My Courses
        </div>

        <div class="courses-grid">
            <?php
            // Fetch user's courses with instructor information
            $courses_sql = "SELECT c.*, u.name as instructor_name, uc.progress 
                           FROM courses c 
                           JOIN user_courses uc ON c.id = uc.course_id 
                           JOIN users u ON c.instructor_id = u.id 
                           WHERE uc.user_id = ?";
            $stmt = $conn->prepare($courses_sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $courses_result = $stmt->get_result();

            while ($course = $courses_result->fetch_assoc()) {
            ?>
            <div class="course-card">
                <img src="<?php 
                $image_url = htmlspecialchars($course['image_url']);
                echo file_exists($image_url) ? $image_url : 'uploads/default.jpg'; 
                ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="course-image">
                <div class="course-content">
                    <div class="course-header">
                        <div>
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="course-instructor">Prof. <?php echo htmlspecialchars($course['instructor_name']); ?></div>
                        </div>
                        <span class="course-badge">Active</span>
                    </div>
                    <p class="course-description"><?php echo htmlspecialchars($course['description']); ?></p>
                    <div class="progress-container">
                        <div class="progress-info">
                            <span>Progress</span>
                            <span><?php echo $course['progress']; ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%"></div>
                        </div>
                    </div>
                    <div class="course-footer">
                        <div class="course-meta">
                            <i class="fas fa-book"></i>
                            <span>12 Lessons</span>
                        </div>
                        <div class="course-actions">
                            <button class="btn btn-primary">Continue</button>
                            <button class="btn btn-outline">Syllabus</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            }
            $stmt->close();
            ?>
        </div>
        </div> <!-- End of courses-grid -->
        
        <!-- Announcements Section -->
        <div class="section-title">
            <i class="fas fa-bullhorn"></i>
            Announcements
        </div>
        
        <div class="announcements-container">
            <?php
            // Fetch announcements for the user's courses
            $announcements_sql = "SELECT a.* FROM announcements a 
                                JOIN user_courses uc ON a.course_id = uc.course_id 
                                WHERE uc.user_id = ? 
                                ORDER BY a.created_at DESC LIMIT 5";
            $stmt = $conn->prepare($announcements_sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $announcements_result = $stmt->get_result();
            
            if ($announcements_result->num_rows > 0) {
                while ($announcement = $announcements_result->fetch_assoc()) {
                    ?>
                    <div class="announcement-item">
                        <div class="announcement-title">
                            <i class="fas fa-circle-info"></i>
                            <?php echo htmlspecialchars($announcement['title']); ?>
                        </div>
                        <div class="announcement-date">
                            <?php echo date('F j, Y', strtotime($announcement['created_at'])); ?>
                        </div>
                        <div class="announcement-text">
                            <?php echo htmlspecialchars($announcement['content']); ?>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="announcement-item">No announcements at this time.</div>';
            }
            $stmt->close();
            ?>
        </div>
    </main>

    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
