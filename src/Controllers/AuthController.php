<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Auth;
use Exception;
use Google\Client;
use Google\Service\Oauth2;

class AuthController {
  // Student
  public function login(Request $request) {
    $data = $request->body();
    
    // Validate required fields
    if (empty($data['student_number']) || empty($data['password'])) {
      http_response_code(400);
      return json_encode(['error' => 'Student number and password are required']);
    }

    $user = Auth::findByStudentNumber($data['student_number']);

    // Check if user exists and is locked out
    if ($user) {
      $loginAttempts = $user['login_attempts'] ?? 0;
      $lastAttempt = isset($user['last_login_attempt']) ? strtotime($user['last_login_attempt']) : 0;
      $lockoutTime = 30 * 60; // 30 minutes in seconds
      
      // Check if user is locked out
      if ($loginAttempts >= 5 && (time() - $lastAttempt < $lockoutTime)) {
        $remainingTime = ceil(($lockoutTime - (time() - $lastAttempt)) / 60);
        http_response_code(429);
        return json_encode([
          'error' => 'Account locked. Please try again in ' . $remainingTime . ' minutes'
        ]);
      } elseif ($loginAttempts >= 5 && (time() - $lastAttempt >= $lockoutTime)) {
        // Reset attempts if lockout period has passed
        Auth::resetLoginAttempts($user['id']);
        $user['login_attempts'] = 0;
        $loginAttempts = 0;
      }
    }

    if (!$user || !password_verify($data['password'], $user['password'])) {
      // Increment failed login attempts only if user exists
      if ($user) {
        Auth::incrementLoginAttempts($user['id']);
        
        $currentAttempts = ($user['login_attempts'] ?? 0) + 1;
        $attemptsLeft = 5 - $currentAttempts;
        
        if ($attemptsLeft <= 0) {
          http_response_code(429);
          return json_encode([
            'error' => 'Too many failed attempts. Account locked for 30 minutes'
          ]);
        }
        
        http_response_code(401);
        return json_encode([
          'error' => 'Invalid credentials. ' . $attemptsLeft . ' attempts remaining'
        ]);
      } else {
        // User doesn't exist
        http_response_code(401);
        return json_encode(['error' => 'Invalid credentials']);
      }
    }

    // Successful login - reset attempts
    Auth::resetLoginAttempts($user['id']);

    $csrfToken = bin2hex(random_bytes(32));
    Auth::updateStudentCsrfToken($user['user_id'], $csrfToken);
    $user['csrf_token'] = $csrfToken;

    // Remove sensitive data from response
    unset($user['password']);
    unset($user['login_attempts']);
    unset($user['last_login_attempt']);

    return json_encode([
      'success' => true, 
      'message' => 'Login successful',
      'user' => $user
    ]);
  }

  public function register(Request $request) {
    $data = $request->body();

    // Validate required fields
    $required = [
      'first_name', 'middle_name', 'last_name', 'student_number', 'email', 
      'phone_number', 'course', 'year_level', 'complete_address', 'password', 'confirm_password'
    ];

    foreach ($required as $field) {
      if (empty($data[$field])) {
        http_response_code(400);
        return json_encode(['error' => "$field is required"]);
      }
    }

    // Capitalize the name
    $data['first_name'] = ucwords(strtolower($data['first_name']));
    $data['middle_name'] = ucwords(strtolower($data['middle_name']));
    $data['last_name'] = ucwords(strtolower($data['last_name']));
    $data['complete_address'] = ucwords(strtolower($data['complete_address']));

    // Validate student number format
    if (!preg_match('/^\d{4}-\d{4}$/', $data['student_number'])) {
      http_response_code(400);
      return json_encode(['error' => 'Student number must be in the format: 0000-0000']);
    }

    // Check if student number already exists
    if (Auth::findByStudentNumber($data['student_number'])) {
      http_response_code(409);
      return json_encode(['error' => 'Student number already registered']);
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
      http_response_code(400);
      return json_encode(['error' => 'Please enter a valid email address']);
    }

    // Check if email already exists
    if (Auth::findByEmail($data['email'])) {
      http_response_code(409);
      return json_encode(['error' => 'Email already registered']);
    }

    // Phone number format must be ph
    if (!preg_match('/^(09|\+639)\d{9}$/', $data['phone_number'])) {
      http_response_code(400);
      return json_encode(['error' => 'Phone number must be a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX)']);
    }

    // Check if course exists

    // Validate password and confirm password match
    if ($data['password'] !== $data['confirm_password']) {
      http_response_code(400);
      return json_encode(['error' => 'Password and confirm password do not match']);
    }

    // Password minimum 6 characters
    if (strlen($data['password']) < 6) {
      http_response_code(400);
      return json_encode(['error' => 'Password must be at least 6 characters long']);
    }

    // Password format must have text and numbers
    if (!preg_match('/[a-zA-Z]/', $data['password']) || !preg_match('/[0-9]/', $data['password'])) {
      http_response_code(400);
      return json_encode(['error' => 'Password must contain both letters and numbers']);
    }

    // Handle file uploads
    $requiredFiles = ['picture', 'school_id', 'certificate_of_indigency', 'certificate_of_registration'];
    foreach ($requiredFiles as $fileField) {
      if (empty($_FILES[$fileField]['name'])) {
        http_response_code(400);
        return json_encode(['error' => "$fileField is required"]);
      }
    }

    $fileFields = ['picture', 'school_id', 'certificate_of_indigency', 'certificate_of_registration'];
    foreach ($fileFields as $field) {
      if (!empty($_FILES[$field]['name'])) {
        $uploadResult = $this->handleFileUpload($_FILES[$field], $field);
        if ($uploadResult['success']) {
          $data[$field] = $uploadResult['filename'];
        } else {
          http_response_code(400);
          return json_encode(['error' => $uploadResult['error']]);
        }
      }
    }

    $data['user_id'] = bin2hex(random_bytes(16));
    $data['api_key'] = bin2hex(random_bytes(16));

    // Create user
    if (Auth::createStudent($data)) {
      http_response_code(201);
      return json_encode([
        'success' => true, 
        'message' => 'Registration successful'
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to create user']);
    }
  }

  private function handleFileUpload($file, $fieldName) {
    // Determine the subfolder based on file type
    $subfolder = '';
    $allowedExtensions = [];
    $allowedMimeTypes = [];

    switch ($fieldName) {
      case 'picture':
      case 'school_id':
        $subfolder = 'images/';
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        break;
      case 'certificate_of_indigency':
      case 'certificate_of_registration':
        $subfolder = 'pdf/';
        $allowedExtensions = ['pdf', 'docx'];
        $allowedMimeTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        break;
    }

    $uploadDir = __DIR__ . '../../uploads/' . $subfolder;
    
    // Create uploads directory and subfolder if they don't exist
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }

    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
      return ['success' => false, 'error' => 'File upload failed'];
    }

    // Set file size limits (5MB for images, 10MB for PDFs/DOCX)
    $maxSize = in_array($fieldName, ['certificate_of_indigency', 'certificate_of_registration']) 
      ? 10 * 1024 * 1024 
      : 5 * 1024 * 1024;

    if ($file['size'] > $maxSize) {
      $maxSizeMB = $maxSize / (1024 * 1024);
      return ['success' => false, 'error' => "File size too large. Maximum size: {$maxSizeMB}MB"];
    }

    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileMimeType = mime_content_type($file['tmp_name']);

    if (!in_array($fileExtension, $allowedExtensions) || !in_array($fileMimeType, $allowedMimeTypes)) {
      $allowedTypes = implode(', ', $allowedExtensions);
      return ['success' => false, 'error' => "Invalid file type for {$fieldName}. Allowed: {$allowedTypes}"];
    }

    // Generate unique filename
    $uniqueName = uniqid() . '_' . time() . '.' . $fileExtension;
    $targetPath = $uploadDir . $uniqueName;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
      // Return the relative path including subfolder
      return ['success' => true, 'filename' => $subfolder . $uniqueName];
    } else {
      return ['success' => false, 'error' => 'Failed to save file'];
    }
  }

  public function forgotPassword(Request $request) {
    $data = $request->body();

    // Validate required field
    if (empty($data['student_number'])) {
      http_response_code(400);
      return json_encode(['error' => 'Student number is required']);
    }

    // Find user by student number
    $user = Auth::findByStudentNumber($data['student_number']);
    
    if (!$user) {
      // Return success even if user not found to prevent email enumeration
      http_response_code(200);
      return json_encode([
        'success' => true,
        'message' => 'If the student number exists, a password reset email has been sent'
      ]);
    }

    // Generate reset token (expires in 1 hour)
    $resetToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Save token to database
    if (!Auth::saveResetToken($user['id'], $resetToken, $expiresAt)) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to generate reset token']);
    }

    // Send email using Brevo
    $emailSent = $this->sendPasswordResetEmail($user['email'], $user['first_name'], $resetToken);

    if ($emailSent) {
      http_response_code(200);
      return json_encode([
        'success' => true,
        'message' => 'Password reset email has been sent'
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to send password reset email']);
    }
  }

  public function resetPassword(Request $request) {
    $data = $request->body();

    // Validate required fields
    if (empty($data['token']) || empty($data['password']) || empty($data['confirm_password'])) {
      http_response_code(400);
      return json_encode(['error' => 'Token, password, and confirm password are required']);
    }

    // Validate password and confirm password match
    if ($data['password'] !== $data['confirm_password']) {
      http_response_code(400);
      return json_encode(['error' => 'Password and confirm password do not match']);
    }

    // Password minimum 6 characters
    if (strlen($data['password']) < 6) {
      http_response_code(400);
      return json_encode(['error' => 'Password must be at least 6 characters long']);
    }

    // Password format must have text and numbers
    if (!preg_match('/[a-zA-Z]/', $data['password']) || !preg_match('/[0-9]/', $data['password'])) {
      http_response_code(400);
      return json_encode(['error' => 'Password must contain both letters and numbers']);
    }

    // Verify token and reset password
    $result = Auth::resetPasswordWithToken($data['token'], $data['password']);

    if ($result['success']) {
      http_response_code(200);
      return json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully'
      ]);
    } else {
      http_response_code(400);
      return json_encode(['error' => $result['error']]);
    }
  }

  private function sendPasswordResetEmail($email, $firstName, $token) {
    try {
      // Brevo API configuration (add these to your .env file)
      $brevoApiKey = $_ENV['BREVO_API_KEY'];
      $brevoSenderEmail = $_ENV['BREVO_SENDER_EMAIL'];
      $brevoSenderName = $_ENV['BREVO_SENDER_NAME'];
      $appBaseUrl = $_ENV['APP_BASE_URL'];

      $resetLink = $appBaseUrl . "/reset?token=" . urlencode($token);

      // Email content
      $subject = "Password Reset Request";
      $htmlContent = "
        <h2>Password Reset Request</h2>
        <p>Hello $firstName,</p>
        <p>You requested to reset your password. Click the link below to reset your password:</p>
        <p><a href='$resetLink' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
        <p>This link will expire in 1 hour.</p>
        <p>If you didn't request this reset, please ignore this email.</p>
        <br>
        <p>Best regards,<br>OSAS Team</p>
      ";

      // Brevo API request
      $url = 'https://api.brevo.com/v3/smtp/email';
      $data = [
        'sender' => [
          'name' => $brevoSenderName,
          'email' => $brevoSenderEmail
        ],
        'to' => [
          [
            'email' => $email,
            'name' => $firstName
          ]
        ],
        'subject' => $subject,
        'htmlContent' => $htmlContent
      ];

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . $brevoApiKey,
        'content-type: application/json'
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      return $httpCode >= 200 && $httpCode < 300;

    } catch (Exception $e) {
      error_log("Brevo email error: " . $e->getMessage());
      return false;
    }
  }

  // Admin
  public function adminAuth(Request $request) {
    $data = $request->body();
    
    // Only Google auth is supported
    if (empty($data['google_token'])) {
      http_response_code(400);
      return json_encode(['error' => 'Google token is required']);
    }

    return $this->handleGoogleAuth($data['google_token']);
  }

  private function handleGoogleAuth($googleToken) {
    try {
      // Initialize Google Client
      $client = new Client();
      $client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
      $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
      
      // Verify the token
      $payload = $client->verifyIdToken($googleToken);
      
      if ($payload) {
        $googleId = $payload['sub'];
        $email = $payload['email'];
        $firstName = $payload['given_name'] ?? '';
        $lastName = $payload['family_name'] ?? '';
        
        // Check if admin exists with this email
        $admin = Auth::findAdminByEmail($email);
        
        if (!$admin) {
          // Auto-create admin account
          $adminData = [
            'user_id' => bin2hex(random_bytes(8)),
            'api_key' => bin2hex(random_bytes(8)), 
            'first_name' => $firstName,
            'last_name' => $lastName,
            'department' => '', 
            'email' => $email,
            'status' => 'pending',
            'role' => 'admin',
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT) 
          ];
          
          if (Auth::createAdmin($adminData)) {
            // Return warning instead of success
            http_response_code(403);
            return json_encode([
              'success' => false,
              'error' => 'Account pending approval',
              'message' => 'Your admin account has been created but is pending approval. Please contact another admin.'
            ]);
          } else {
            http_response_code(500);
            return json_encode(['success' => false, 'error' => 'Failed to create admin account']);
          }
        } else {
          // Check if admin status is approved
          if ($admin['status'] !== 'approved') {
            http_response_code(403);
            return json_encode([
              'error' => 'Account not approved',
              'message' => 'Your account is pending approval. Please contact administrator.'
            ]);
          }

          // Update Google ID if not set
          if (empty($admin['google_id'])) {
            Auth::updateGoogleId($admin['id'], $googleId);
          }
        }
        
        // Remove password from response
        unset($admin['password']);

        $csrfToken = bin2hex(random_bytes(32));
        Auth::updateAdminCsrfToken($admin['user_id'], $csrfToken);
        $admin['csrf_token'] = $csrfToken;
        $admin['role'] = 'admin';
        
        return json_encode([
          'success' => true,
          'message' => 'Google authentication successful',
          'user' => $admin
        ]);
      } else {
        http_response_code(401);
        return json_encode(['error' => 'Invalid Google token']);
      }
    } catch (Exception $e) {
      error_log("Google auth error: " . $e->getMessage());
      http_response_code(500);
      return json_encode(['error' => 'Google authentication failed']);
    }
  }
}
