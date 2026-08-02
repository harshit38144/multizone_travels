<?php
header('Content-Type: application/json');

// To prevent CORS issues if accessed from a different origin locally
header("Access-Control-Allow-Origin: *");

// Include Composer autoloader
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Admin email address to receive enquiries
$to_email = "cliffmediarnc@gmail.com"; // Replace with your actual email address

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and collect form data
    $name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
    $email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '';
    
    $destination = isset($_POST['destination']) ? htmlspecialchars($_POST['destination']) : '';
    $travel_date = isset($_POST['travel_date']) ? htmlspecialchars($_POST['travel_date']) : '';
    
    $rooms = isset($_POST['rooms']) ? htmlspecialchars($_POST['rooms']) : '';
    $adults = isset($_POST['adults']) ? htmlspecialchars($_POST['adults']) : '';
    $children = isset($_POST['children']) ? htmlspecialchars($_POST['children']) : '';
    $budget = isset($_POST['budget']) ? htmlspecialchars($_POST['budget']) : '';
    
    $services = isset($_POST['services']) && is_array($_POST['services']) ? implode(', ', $_POST['services']) : '';
    $message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';

    $package_url = isset($_POST['package_url']) ? htmlspecialchars($_POST['package_url']) : '';
    $package_image = isset($_POST['package_image']) ? htmlspecialchars($_POST['package_image']) : '';
    // Decode HTML entities so things like &amp; become &
    $package_title = isset($_POST['package_title']) ? htmlspecialchars_decode($_POST['package_title']) : '';

    // Validate required fields
    if (empty($name) || empty($email) || empty($phone) || empty($destination) || empty($travel_date)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
        exit;
    }

    // Prepare email subject
    $subject = "New Enquiry from Website: " . $name . " for " . $destination;

    $mail = new PHPMailer(true);

    // Generate package details card if provided
    $package_card_html = "";
    if (!empty($package_title) || !empty($package_url)) {
        $img_html = "";
        
        if (!empty($package_image)) {
            // Try to map URL to a local file for embedding
            $local_path = '';
            if (strpos($package_image, 'localhost') !== false || strpos($package_image, 'web.tripotomize.com') !== false || strpos($package_image, $_SERVER['HTTP_HOST'] ?? '') !== false || !preg_match('#^https?://#', $package_image)) {
                $parsed = parse_url($package_image);
                $path = isset($parsed['path']) ? $parsed['path'] : $package_image;
                
                // Clean the path
                $path = preg_replace('#^/multizone_travels/#', '', $path);
                $path = ltrim($path, '/');
                
                $project_root = dirname(__DIR__);
                
                $potential_paths = [
                    $project_root . '/' . str_replace('admin/admin/', 'admin/', $path),
                    $project_root . '/' . $path,
                    $project_root . '/admin/' . $path,
                    $project_root . '/images/' . basename($path),
                    $project_root . '/admin/uploads/' . basename($path)
                ];
                
                foreach ($potential_paths as $p) {
                    if (file_exists($p) && is_file($p)) {
                        $local_path = $p;
                        break;
                    }
                }
            }
            
            if ($local_path) {
                $cid = 'pkg_img_' . md5($local_path);
                $mail->addEmbeddedImage($local_path, $cid, basename($local_path));
                $img_html = "<img src='cid:{$cid}' alt='Package Image' style='width: 100%; max-height: 250px; object-fit: cover; border-radius: 8px 8px 0 0; margin-bottom: 15px;'>";
            } else {
                $img_html = "<img src='{$package_image}' alt='Package Image' style='width: 100%; max-height: 250px; object-fit: cover; border-radius: 8px 8px 0 0; margin-bottom: 15px;'>";
            }
        }

        $title_html = !empty($package_title) ? "<h3 style='margin: 0 0 10px 0; color: #2c3e50; font-size: 20px; padding: 0 15px;'>{$package_title}</h3>" : "";
        $btn_html = !empty($package_url) ? "<div style='text-align: center; padding: 15px; border-top: 1px solid #f1f1f1;'><a href='{$package_url}' style='display: inline-block; background-color: #f39c12; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: bold; font-size: 15px;'>View Package on Website</a></div>" : "";
        
        $package_card_html = "
        <div class='card' style='padding: 0; overflow: hidden; border: 2px solid #e8f4fd;'>
            {$img_html}
            {$title_html}
            {$btn_html}
        </div>
        ";
    }

    // Prepare HTML email content
    $email_content = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='utf-8'>
      <title>New Travel Enquiry</title>
      <style>
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            line-height: 1.6; 
            color: #333333; 
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            width: 100%;
            background-color: #f0f2f5;
            padding: 30px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        .header {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80') center/cover;
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 28px;
            letter-spacing: 1px;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }
        .header p {
            margin: 10px 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 10px 10px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .section-title {
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
        }
        .highlight-box {
            background-color: #e8f4fd;
            border-left: 4px solid #3498db;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .highlight-box .dest {
            font-size: 20px;
            font-weight: 700;
            color: #2980b9;
            margin: 0 0 5px 0;
        }
        .highlight-box .date {
            font-size: 15px;
            color: #34495e;
            margin: 0;
        }
        .footer {
            background-color: #2c3e50;
            padding: 10px;
            text-align: center;
            color: #bdc3c7;
        }
        .footer p {
            margin: 0 0 10px;
            font-size: 13px;
        }
        .link {
            color: #3498db;
            text-decoration: none;
        }
      </style>
    </head>
    <body>
      <div class='wrapper'>
          <div class='container'>
              <div class='header'>
                <h1>New Travel Enquiry</h1>
                <p>A new lead has arrived from your website!</p>
              </div>
              
              <div class='content'>
                  
                  {$package_card_html}
                  
                  <div class='highlight-box'>
                      <p class='dest'>✈️ {$destination}</p>
                      <p class='date'>📅 Travel Date: {$travel_date}</p>
                  </div>
                  
                  <div class='card'>
                      <h3 class='section-title'>👤 Traveler Information</h3>
                      <table style='width:100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f1f1; width: 35%; color: #7f8c8d; font-size: 14px; font-weight: bold;'>Name</td>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f1f1; color: #2c3e50; font-weight: 500;'>{$name}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f1f1; color: #7f8c8d; font-size: 14px; font-weight: bold;'>Email</td>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f1f1;'><a href='mailto:{$email}' class='link'>{$email}</a></td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #7f8c8d; font-size: 14px; font-weight: bold;'>Phone</td>
                            <td style='padding: 10px 0;'><a href='tel:{$phone}' class='link'>{$phone}</a></td>
                        </tr>
                      </table>
                  </div>
                  
                  <div class='card'>
                      <h3 class='section-title'>🧳 Trip Requirements</h3>
                      <table style='width:100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f1f1; width: 35%; color: #7f8c8d; font-size: 14px; font-weight: bold;'>Passengers</td>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f1f1; color: #2c3e50; font-weight: 500;'>{$adults} Adults, {$children} Children</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f1f1; color: #7f8c8d; font-size: 14px; font-weight: bold;'>Rooms</td>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f1f1; color: #2c3e50; font-weight: 500;'>{$rooms}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #7f8c8d; font-size: 14px; font-weight: bold;'>Budget</td>
                            <td style='padding: 10px 0; color: #27ae60; font-weight: bold;'>{$budget}</td>
                        </tr>
                      </table>
                  </div>
                  
                  <div class='card'>
                      <h3 class='section-title'>🛠️ Services & Preferences</h3>
                      <table style='width:100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f1f1; width: 35%; color: #7f8c8d; font-size: 14px; font-weight: bold;'>Required Services</td>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f1f1; color: #2c3e50; font-weight: 500;'>{$services}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #7f8c8d; font-size: 14px; font-weight: bold; vertical-align: top;'>Message / Needs</td>
                            <td style='padding: 10px 0; color: #2c3e50;'>" . (!empty($message) ? nl2br($message) : "<em>No additional details provided.</em>") . "</td>
                        </tr>
                      </table>
                  </div>
              </div>
              
              <div class='footer'>
                <p>This is an automated lead notification from <strong>Multizone Travels</strong>.</p>
                <p>&copy; " . date('Y') . " Multizone Travels. All rights reserved.</p>
              </div>
          </div>
      </div>
    </body>
    </html>
    ";

    // Send the email
    try {
        // Server settings
        // Enable verbose debug output (set to SMTP::DEBUG_SERVER for debugging)
        $mail->SMTPDebug = SMTP::DEBUG_OFF;                      
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                     // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        
        // IMPORTANT: Replace these with your actual SMTP credentials
        $mail->Username   = 'cliffmediarnc@gmail.com';               // SMTP username
        $mail->Password   = 'rjur diwp ssla ujhl';                  // SMTP password (use App Password for Gmail)
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            // Enable implicit TLS encryption
        $mail->Port       = 465;                                    // TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        // Recipients
        $mail->setFrom('cliffmediarnc@gmail.com', 'Multizone Travels Website');
        $mail->addAddress($to_email);                               // Add a recipient
        $mail->addReplyTo($email, $name);                           // Reply to the user's email

        // Content
        $mail->isHTML(true);                                        // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $email_content;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<tr>', '<td>', '<th>'], ["\n", "\n", " | ", " | "], $email_content));

        $mail->send();
        echo json_encode([
            'success' => true, 
            'message' => 'Thank you! Your enquiry has been submitted successfully. We will get back to you shortly.'
        ]);
    } catch (Exception $e) {
        // Log the error for debugging
        $log_entry = date('Y-m-d H:i:s') . " - PHPMailer Error: {$mail->ErrorInfo}\n";
        @file_put_contents('enquiries_log.txt', $log_entry, FILE_APPEND);
        
        echo json_encode([
            'success' => false, 
            'message' => 'Error sending enquiry: ' . $mail->ErrorInfo
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>