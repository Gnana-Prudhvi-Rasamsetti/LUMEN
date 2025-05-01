<?php
session_start();
require_once 'config.php';
$conn = require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current date for reference
$current_date = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));
$next_month = date('Y-m-d', strtotime('+30 days'));

// Sample events for each course
$events = [
    // Cloud Computing events
    [
        'title' => 'Cloud Infrastructure Workshop',
        'description' => 'Hands-on workshop for setting up cloud infrastructure',
        'course_id' => 1,
        'instructor_id' => 3,
        'event_date' => date('Y-m-d', strtotime('+3 days')),
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'location' => 'Virtual Lab Room 1',
        'event_type' => 'workshop'
    ],
    [
        'title' => 'AWS Certification Prep Session',
        'description' => 'Preparation session for AWS certification exam',
        'course_id' => 1,
        'instructor_id' => 3,
        'event_date' => date('Y-m-d', strtotime('+10 days')),
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'location' => 'Virtual Lab Room 2',
        'event_type' => 'lecture'
    ],
    
    // Database Management events
    [
        'title' => 'SQL Advanced Queries Seminar',
        'description' => 'Learn advanced SQL query techniques and optimization',
        'course_id' => 2,
        'instructor_id' => 4,
        'event_date' => date('Y-m-d', strtotime('+2 days')),
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'location' => 'Database Lab',
        'event_type' => 'seminar'
    ],
    [
        'title' => 'Database Design Project Review',
        'description' => 'Review session for database design projects',
        'course_id' => 2,
        'instructor_id' => 4,
        'event_date' => date('Y-m-d', strtotime('+14 days')),
        'start_time' => '11:00:00',
        'end_time' => '13:00:00',
        'location' => 'Room 201',
        'event_type' => 'review'
    ],
    
    // Machine Learning events
    [
        'title' => 'Neural Networks Deep Dive',
        'description' => 'Exploring neural network architectures and applications',
        'course_id' => 3,
        'instructor_id' => 17,
        'event_date' => date('Y-m-d', strtotime('+5 days')),
        'start_time' => '15:00:00',
        'end_time' => '17:00:00',
        'location' => 'AI Lab',
        'event_type' => 'lecture'
    ],
    [
        'title' => 'ML Model Deployment Workshop',
        'description' => 'Hands-on session on deploying ML models to production',
        'course_id' => 3,
        'instructor_id' => 17,
        'event_date' => date('Y-m-d', strtotime('+20 days')),
        'start_time' => '14:00:00',
        'end_time' => '16:30:00',
        'location' => 'Virtual Lab Room 3',
        'event_type' => 'workshop'
    ],
    
    // Python Programming events
    [
        'title' => 'Python Libraries for Data Science',
        'description' => 'Overview of essential Python libraries for data science',
        'course_id' => 4,
        'instructor_id' => 18,
        'event_date' => date('Y-m-d', strtotime('+4 days')),
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'location' => 'Room 105',
        'event_type' => 'lecture'
    ],
    [
        'title' => 'Python Code Review Session',
        'description' => 'Group code review for Python programming assignments',
        'course_id' => 4,
        'instructor_id' => 18,
        'event_date' => date('Y-m-d', strtotime('+12 days')),
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'location' => 'Computer Lab 2',
        'event_type' => 'review'
    ],
    
    // Cybersecurity events
    [
        'title' => 'Ethical Hacking Demonstration',
        'description' => 'Live demonstration of ethical hacking techniques',
        'course_id' => 5,
        'instructor_id' => 19,
        'event_date' => date('Y-m-d', strtotime('+6 days')),
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'location' => 'Security Lab',
        'event_type' => 'demonstration'
    ],
    [
        'title' => 'Cybersecurity Threat Analysis Workshop',
        'description' => 'Workshop on identifying and analyzing security threats',
        'course_id' => 5,
        'instructor_id' => 19,
        'event_date' => date('Y-m-d', strtotime('+15 days')),
        'start_time' => '11:00:00',
        'end_time' => '13:00:00',
        'location' => 'Room 302',
        'event_type' => 'workshop'
    ],
    
    // Web Technologies events
    [
        'title' => 'Modern Frontend Frameworks Overview',
        'description' => 'Introduction to React, Vue, and Angular frameworks',
        'course_id' => 6,
        'instructor_id' => 20,
        'event_date' => date('Y-m-d', strtotime('+8 days')),
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'location' => 'Web Development Lab',
        'event_type' => 'lecture'
    ],
    [
        'title' => 'Web Application Security Seminar',
        'description' => 'Best practices for securing web applications',
        'course_id' => 6,
        'instructor_id' => 20,
        'event_date' => date('Y-m-d', strtotime('+18 days')),
        'start_time' => '13:30:00',
        'end_time' => '15:30:00',
        'location' => 'Room 204',
        'event_type' => 'seminar'
    ]
];

// Check if events already exist to avoid duplicates
$check_query = "SELECT COUNT(*) as count FROM events";
$result = $conn->query($check_query);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Insert events
    $insert_query = "INSERT INTO events (title, description, course_id, instructor_id, event_date, start_time, end_time, location, event_type) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_query);
    
    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("ssiissssss", $title, $description, $course_id, $instructor_id, $event_date, $start_time, $end_time, $location, $event_type);
    
    $success_count = 0;
    
    foreach ($events as $event) {
        $title = $event['title'];
        $description = $event['description'];
        $course_id = $event['course_id'];
        $instructor_id = $event['instructor_id'];
        $event_date = $event['event_date'];
        $start_time = $event['start_time'];
        $end_time = $event['end_time'];
        $location = $event['location'];
        $event_type = $event['event_type'];
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            echo "Error adding event: " . $stmt->error . "<br>";
        }
    }
    
    echo "<p>Successfully added $success_count events.</p>";
    echo "<p><a href='Lumen Schedule page.php'>Return to Schedule</a></p>";
} else {
    echo "<p>Events already exist in the database. No new events added.</p>";
    echo "<p><a href='Lumen Schedule page.php'>Return to Schedule</a></p>";
}
?>