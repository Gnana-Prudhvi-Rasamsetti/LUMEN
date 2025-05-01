<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $response = ['success' => false, 'message' => ''];

    // Get the updated profile data
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Handle profile photo upload if present
    $profile_photo = $_SESSION['profile_photo']; // Keep existing by default
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $upload_dir = 'uploads/';
        
        // Get original file extension
        $file_info = pathinfo($_FILES['profile_photo']['name']);
        $file_ext = strtolower($file_info['extension']);
        
        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_ext, $allowed_types)) {
            $response['message'] = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
            echo json_encode($response);
            exit();
        }
        
        // Create unique filename while preserving extension
        $file_name = $user_id . '_' . time() . '.' . $file_ext;
        $target_file = $upload_dir . $file_name;
        
        // Store only filename in database, not full path
        $db_file_name = $file_name;
        
        // Ensure upload directory exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
            // Delete old profile photo if it exists and is not the default
            $old_file = 'uploads/' . $profile_photo;
            if ($profile_photo !== 'default.png' && file_exists($old_file)) {
                unlink($old_file);
            }
            $profile_photo = $db_file_name; // Store only filename
        } else {
            $response['message'] = 'Failed to upload profile photo';
            echo json_encode($response);
            exit();
        }
    }

    // Update the database
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, profile_photo = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $email, $role, $profile_photo, $user_id);
    
    if ($stmt->execute()) {
        // Update session variables
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;
        $_SESSION['profile_photo'] = $profile_photo;
        
        $response['success'] = true;
        $response['message'] = 'Profile updated successfully';
        $response['profile_photo'] = $profile_photo; // Send back the new photo path
    } else {
        $response['message'] = 'Failed to update profile';
    }
    
    echo json_encode($response);
    exit();
}
?>