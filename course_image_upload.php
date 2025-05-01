<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $_POST['course_id'] ?? '';
    $response = ['success' => false, 'message' => ''];

    if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === 0) {
        $upload_dir = 'uploads/courses/';
        
        // Get original file extension
        $file_info = pathinfo($_FILES['course_image']['name']);
        $file_ext = strtolower($file_info['extension']);
        
        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_ext, $allowed_types)) {
            $response['message'] = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
            echo json_encode($response);
            exit();
        }
        
        // Create unique filename
        $file_name = 'course_' . $course_id . '_' . time() . '.' . $file_ext;
        $target_file = $upload_dir . $file_name;
        
        // Ensure upload directory exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['course_image']['tmp_name'], $target_file)) {
            // Update course image in database
            $stmt = $conn->prepare("UPDATE courses SET image_url = ? WHERE id = ?");
            $image_path = 'uploads/courses/' . $file_name;
            $stmt->bind_param("si", $image_path, $course_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Course image updated successfully';
                $response['image_url'] = $image_path;
            } else {
                $response['message'] = 'Failed to update course image in database';
            }
        } else {
            $response['message'] = 'Failed to upload course image';
        }
    }
    
    echo json_encode($response);
    exit();
}
?>