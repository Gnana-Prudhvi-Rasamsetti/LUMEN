<?php
session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $recipient_email = filter_var($_POST['recipient_email'], FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars($_POST['subject']);
    $category = htmlspecialchars($_POST['category']);
    $details = htmlspecialchars($_POST['details']);
    
    // Set email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: gnanaprudhvir@gmail.com' . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";
    
    // Compose email message
    $message = "
    <html>
    <head>
        <title>Complaint Submission</title>
    </head>
    <body>
        <h2>New Complaint Submission</h2>
        <p><strong>Category:</strong> $category</p>
        <p><strong>Subject:</strong> $subject</p>
        <p><strong>Details:</strong></p>
        <p>$details</p>
        <hr>
        <p>This complaint was submitted through the LUMEN Help Center.</p>
    </body>
    </html>
    ";
    
    // Add error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Send email with error checking
    $mail_sent = mail($recipient_email, "LUMEN Complaint: $subject", $message, $headers);
    
    if($mail_sent) {
        $_SESSION['message'] = "Your complaint has been submitted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $error = error_get_last();
        $_SESSION['message'] = "Failed to send complaint. Error: " . ($error['message'] ?? 'Unknown error');
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect back to help center
    header("Location: Lumen Help center page.php");
    exit();
}
?>
<?php
session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $recipient_email = filter_var($_POST['recipient_email'], FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars($_POST['subject']);
    $category = htmlspecialchars($_POST['category']);
    $details = htmlspecialchars($_POST['details']);
    
    // Set email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: gnanaprudhvir@gmail.com' . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";
    
    // Compose email message
    $message = "
    <html>
    <head>
        <title>Complaint Submission</title>
    </head>
    <body>
        <h2>New Complaint Submission</h2>
        <p><strong>Category:</strong> $category</p>
        <p><strong>Subject:</strong> $subject</p>
        <p><strong>Details:</strong></p>
        <p>$details</p>
        <hr>
        <p>This complaint was submitted through the LUMEN Help Center.</p>
    </body>
    </html>
    ";
    
    // Add error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Send email with error checking
    $mail_sent = mail($recipient_email, "LUMEN Complaint: $subject", $message, $headers);
    
    if($mail_sent) {
        $_SESSION['message'] = "Your complaint has been submitted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $error = error_get_last();
        $_SESSION['message'] = "Failed to send complaint. Error: " . ($error['message'] ?? 'Unknown error');
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect back to help center
    header("Location: Lumen Help center page.php");
    exit();
}
?>