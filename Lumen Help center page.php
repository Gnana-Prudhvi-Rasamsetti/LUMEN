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
    <title>Help Center | LUMEN - Virtual Classroom</title>
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

        <?php
        session_start();
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit();
        }
        
        // Get user data from session
        $user_name = $_SESSION['user_name'] ?? 'Guest';
        $user_role = $_SESSION['user_role'] ?? 'Student';
        ?>
        <!DOCTYPE html>
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

        /* Help Center Content */
        .help-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .help-section {
            margin-bottom: 2.5rem;
        }

        .help-section:last-child {
            margin-bottom: 0;
        }

        .help-section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .help-section-title i {
            color: var(--accent-primary);
        }

        .faq-item {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 1.5rem;
        }

        .faq-item:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }

        .faq-question {
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--accent-primary);
        }

        .faq-answer {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Complaint Form */
        .complaint-form {
            margin-top: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: var(--border-radius);
            border: 1px solid rgba(0, 0, 0, 0.1);
            font-size: 0.95rem;
            transition: var(--transition);
            background-color: var(--bg-primary);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(214, 23, 75, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 0.95rem;
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

        /* Contact Methods */
        .contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .contact-card {
            background-color: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .contact-icon {
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

        .contact-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .contact-info {
            color: var(--text-secondary);
            font-size: 0.95rem;
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
            .contact-methods {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
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
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.25rem;
            }
            
            .help-container {
                padding: 1.5rem;
            }
            
            .help-section-title {
                font-size: 1.2rem;
            }
            
            .contact-methods {
                grid-template-columns: 1fr;
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
            <a href="Lumen settings page.php" class="nav-link">
                <i class="nav-icon fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <div style="height: 1rem;"></div>
            <a href="Lumen Help center page.php" class="nav-link active">
                <i class="nav-icon fas fa-question-circle"></i>
                <span>Help Center</span>
            </a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="header">
            <h1 class="page-title">
                <i class="fas fa-question-circle"></i>
                Help Center
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
        
        <div class="help-container">
            <!-- FAQ Section -->
            <div class="help-section">
                <h2 class="help-section-title">
                    <i class="fas fa-question"></i>
                    Frequently Asked Questions
                </h2>
                
                <div class="faq-item">
                    <h3 class="faq-question">How do I reset my password?</h3>
                    <p class="faq-answer">
                        You can reset your password by sending a mail to support@lumen.edu.in
                    </p>
                </div>
                
                <div class="faq-item">
                    <h3 class="faq-question">How do I enroll in a new course?</h3>
                    <p class="faq-answer">
                        Login to your student portal and pay the fee for the course you want to enroll in.
                        After payment, you will receive an enrollment confirmation email.
                    </p>
                </div>
                
                <div class="faq-item">
                    <h3 class="faq-question">Where can I find my completed assignments?</h3>
                    <p class="faq-answer">
                        All completed assignments can be found in the My Learnings section under each 
                        respective course. You can also view your submission history and grades from there.
                    </p>
                </div>
                
                <div class="faq-item">
                    <h3 class="faq-question">How do I contact my instructor?</h3>
                    <p class="faq-answer">
                        Each course has a professor assigned to it. You can contact them directly through
                        the course portal or by emailing them at their assigned email address.
                    </p>
                </div>
            </div>
            
            <!-- Contact Section -->
            <div class="help-section">
                <h2 class="help-section-title">
                    <i class="fas fa-headset"></i>
                    Contact Support
                </h2>
                
                <div class="contact-methods">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3 class="contact-title">Email Support</h3>
                        <p class="contact-info">support@lumen.edu.in</p>
                        <p class="contact-info">Response time: 24 hours</p>
                    </div>
                    
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h3 class="contact-title">Phone Support</h3>
                        <p class="contact-info">+91 1065789342</p>
                        <p class="contact-info">Mon-Fri, 9AM-5PM</p>
                    </div>
                    
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3 class="contact-title">Live Chat</h3>
                        <p class="contact-info">Will be available soon during business hours</p>
                    </div>
                </div>
            </div>
            
            <!-- Complaint Form Section -->
            <div class="help-section">
                <h2 class="help-section-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Submit a Complaint
                </h2>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                    If you have a serious concern or complaint, please fill out the form below. 
                    Your complaint will be sent directly to our administration department for review.
                </p>
                
                <form class="complaint-form" id="complaintForm" method="POST" action="send_complaint.php">
                    <div class="form-group">
                        <label for="recipient-email">Recipient Email</label>
                        <input type="email" id="recipient-email" name="recipient_email" placeholder="Enter recipient's email address" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="complaint-subject">Subject</label>
                        <input type="text" id="complaint-subject" name="subject" placeholder="Briefly describe your issue" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="complaint-category">Category</label>
                        <select id="complaint-category" name="category" required>
                            <option value="">Select a category</option>
                            <option value="technical">Technical Issue</option>
                            <option value="course">Course Related</option>
                            <option value="instructor">Instructor Related</option>
                            <option value="payment">Payment Issue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="complaint-details">Details</label>
                        <textarea id="complaint-details" name="details" placeholder="Please provide as much detail as possible about your complaint..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="complaint-contact">Preferred Contact Method</label>
                        <select id="complaint-contact">
                            <option value="email">Email</option>
                            <option value="phone">Phone</option>
                            <option value="none">No response needed</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn btn-outline">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Complaint</button>
                    </div>
                </form>
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
        
        // Complaint form submission
        const complaintForm = document.getElementById('complaintForm');
        
        complaintForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const subject = document.getElementById('complaint-subject').value;
            const category = document.getElementById('complaint-category').value;
            const details = document.getElementById('complaint-details').value;
            const contactMethod = document.getElementById('complaint-contact').value;
            
            // In a real application, this would send the data to a server
            console.log('Complaint submitted:', {
                subject,
                category,
                details,
                contactMethod
            });
            
            // Show success message
            alert('Your complaint has been submitted successfully. Our administration team will review it and respond if you requested contact. Thank you for bringing this to our attention.');
            
            // Reset form
            complaintForm.reset();
        });
    </script>
</body>
</html>