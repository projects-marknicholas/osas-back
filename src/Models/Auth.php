<?php
namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;
use DateTime;

class Auth {
  // Student
  public static function findByEmail(string $email) {
    $stmt = Database::connect()->prepare("SELECT * FROM student WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function findByStudentNumber(string $studentNumber) {
    $stmt = Database::connect()->prepare("SELECT id, user_id, password, api_key, csrf_token, login_attempts, last_login_attempt FROM student WHERE student_number = :student_number LIMIT 1");
    $stmt->execute(['student_number' => $studentNumber]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function createStudent(array $data) {
    try {
      $sql = "INSERT INTO student (
        user_id, api_key, first_name, middle_name, last_name, student_number, email, 
        phone_number, course, year_level, complete_address, password,
        picture, school_id, certificate_of_indigency, certificate_of_registration,
        created_at, updated_at
      ) VALUES (
        :user_id, :api_key, :first_name, :middle_name, :last_name, :student_number, :email,
        :phone_number, :course, :year_level, :complete_address, :password,
        :picture, :school_id, :certificate_of_indigency, :certificate_of_registration,
        NOW(), NOW()
      )";

      $stmt = Database::connect()->prepare($sql);
      
      $params = [
        'user_id' => $data['user_id'],
        'api_key' => $data['api_key'],
        'first_name' => $data['first_name'],
        'middle_name' => $data['middle_name'] ?? null,
        'last_name' => $data['last_name'],
        'student_number' => $data['student_number'],
        'email' => $data['email'],
        'phone_number' => $data['phone_number'],
        'course' => $data['course'],
        'year_level' => $data['year_level'],
        'complete_address' => $data['complete_address'],
        'password' => password_hash($data['password'], PASSWORD_DEFAULT),
        'picture' => $data['picture'] ?? null,
        'school_id' => $data['school_id'] ?? null,
        'certificate_of_indigency' => $data['certificate_of_indigency'] ?? null,
        'certificate_of_registration' => $data['certificate_of_registration'] ?? null
      ];

      $result = $stmt->execute($params);

      return $result;

    } catch (PDOException $e) {
      return false;
    }
  }

  public static function saveResetToken($userId, $token, $expiresAt) {
    try {
      $sql = "UPDATE student SET reset_token = :token, reset_token_expires = :expires_at WHERE id = :id";
      $stmt = Database::connect()->prepare($sql);
      return $stmt->execute([
        'token' => $token,
        'expires_at' => $expiresAt,
        'id' => $userId
      ]);
    } catch (PDOException $e) {
      error_log("Save reset token error: " . $e->getMessage());
      return false;
    }
  }

  public static function resetPasswordWithToken($token, $newPassword) {
    try {
      // Check if token is valid and not expired
      $sql = "SELECT id, reset_token_expires FROM student WHERE reset_token = :token";
      $stmt = Database::connect()->prepare($sql);
      $stmt->execute(['token' => $token]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        return ['success' => false, 'error' => 'Invalid or expired reset token'];
      }

      $currentTime = new DateTime();
      $expirationTime = new DateTime($user['reset_token_expires']);
      if ($currentTime > $expirationTime) {
        return ['success' => false, 'error' => 'Reset token has expired'];
      }

      // Update password and clear reset token
      $updateSql = "UPDATE student SET password = :password, reset_token = NULL, reset_token_expires = NULL WHERE id = :id";
      $updateStmt = Database::connect()->prepare($updateSql);
      $success = $updateStmt->execute([
        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => $user['id']
      ]);

      if ($success) {
        return ['success' => true];
      } else {
        return ['success' => false, 'error' => 'Failed to reset password'];
      }

    } catch (PDOException $e) {
      error_log("Reset password error: " . $e->getMessage());
      return ['success' => false, 'error' => 'Server error during password reset'];
    }
  }

  public static function incrementLoginAttempts($userId) {
    $stmt = Database::connect()->prepare("
      UPDATE student 
      SET login_attempts = COALESCE(login_attempts, 0) + 1, 
          last_login_attempt = NOW() 
      WHERE id = :id
    ");
    return $stmt->execute([':id' => $userId]);
  } 

  public static function resetLoginAttempts($userId) {
    $stmt = Database::connect()->prepare("
      UPDATE student 
      SET login_attempts = 0, 
          last_login_attempt = NULL 
      WHERE id = :id
    ");
    return $stmt->execute([':id' => $userId]);
  }
  
  public static function updateStudentCsrfToken($userId, $token) {
    $db = Database::connect();
    $stmt = $db->prepare("UPDATE student SET csrf_token = :token WHERE user_id = :user_id");
    $stmt->execute(['token' => $token, 'user_id' => $userId]);
  }

  public static function validateStudentCsrfToken($userId, $token) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT csrf_token FROM student WHERE user_id = :user_id LIMIT 1");
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row && hash_equals($row['csrf_token'], $token);
  }

  // Admin
  public static function findAdminByEmail(string $email) {
    $stmt = Database::connect()->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function createAdmin(array $data) {
    try {
      $sql = "INSERT INTO admin (
        user_id, api_key, first_name, last_name, department, email, password,
        created_at, updated_at
      ) VALUES (
        :user_id, :api_key, :first_name, :last_name, :department, :email, :password,
        NOW(), NOW()
      )";

      $stmt = Database::connect()->prepare($sql);
      
      $params = [
        'user_id' => $data['user_id'],
        'api_key' => $data['api_key'],
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'department' => $data['department'],
        'email' => $data['email'],
        'password' => password_hash($data['password'], PASSWORD_DEFAULT)
      ];

      $result = $stmt->execute($params);

      return $result;

    } catch (PDOException $e) {
      return false;
    }
  }

  public static function updateGoogleId($adminId, $googleId) {
    try {
      $sql = "UPDATE admin SET google_id = :google_id WHERE id = :id";
      $stmt = Database::connect()->prepare($sql);
      return $stmt->execute([
        'google_id' => $googleId,
        'id' => $adminId
      ]);
    } catch (PDOException $e) {
      error_log("Update Google ID error: " . $e->getMessage());
      return false;
    }
  }

  public static function updateAdminCsrfToken($userId, $token) {
    $db = Database::connect();
    $stmt = $db->prepare("UPDATE admin SET csrf_token = :token WHERE user_id = :user_id");
    $stmt->execute(['token' => $token, 'user_id' => $userId]);
  }

  public static function validateAdminCsrfToken($userId, $token) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT csrf_token FROM admin WHERE user_id = :user_id LIMIT 1");
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row && hash_equals($row['csrf_token'], $token);
  }
}