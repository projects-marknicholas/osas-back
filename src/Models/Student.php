<?php
namespace App\Models;

use App\Core\Database;
use App\Models\Auth;
use PDO;
use PDOException;

class Student {
  public static function findByApiKey(string $apiKey, ?string $csrfToken = null) {
    try {
      $stmt = Database::connect()->prepare("SELECT * FROM student WHERE api_key = :api_key LIMIT 1");
      $stmt->execute(['api_key' => $apiKey]);
      $student = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$student) {
        return false;
      }

      if ($csrfToken !== null) {
        $isValid = Auth::validateStudentCsrfToken($student['user_id'], $csrfToken);
        if (!$isValid) {
          return false;
        }
      }

      return $student;
    } catch (PDOException $e) {
      error_log("Find by API key error: " . $e->getMessage());
      return false;
    }
  }

  public static function updateProfileByApiKey(string $apiKey, string $csrfToken, array $data): bool {
    if (empty($data)) {
      return false;
    }

    $fields = [];
    $params = [
      ':api_key' => $apiKey,
      ':csrf_token' => $csrfToken
    ];

    if (isset($data['first_name'])) { $fields[] = 'first_name = :first_name'; $params[':first_name'] = $data['first_name']; }
    if (isset($data['middle_name'])) { $fields[] = 'middle_name = :middle_name'; $params[':middle_name'] = $data['middle_name']; }
    if (isset($data['last_name'])) { $fields[] = 'last_name = :last_name'; $params[':last_name'] = $data['last_name']; }
    if (isset($data['course_code'])) { $fields[] = 'course = :course_code'; $params[':course_code'] = $data['course_code']; }
    if (isset($data['phone_number'])) { $fields[] = 'phone_number = :phone_number'; $params[':phone_number'] = $data['phone_number']; }
    if (isset($data['complete_address'])) { $fields[] = 'complete_address = :complete_address'; $params[':complete_address'] = $data['complete_address']; }

    if (empty($fields)) {
      return false;
    }

    $sql = "UPDATE student SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE api_key = :api_key AND csrf_token = :csrf_token";

    try {
      $db = Database::connect();
      $stmt = $db->prepare($sql);
      return $stmt->execute($params);
    } catch (PDOException $e) {
      error_log("Update profile error: " . $e->getMessage());
      return false;
    }
  }

  public static function courseCodeExists(string $courseCode): bool {
    try {
      $db = Database::connect();
      $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE UPPER(course_code) = :course_code");
      $stmt->bindParam(':course_code', $courseCode, PDO::PARAM_STR);
      $stmt->execute();
      return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
      return false;
    }
  }

  // Courses
  public static function getCourses($limit = 10, $offset = 0, $search = '') {
    try {
      $sql = "SELECT course_id, course_code, course_name, created_at, updated_at 
              FROM courses 
              WHERE course_code LIKE :search OR course_name LIKE :search
              ORDER BY course_code 
              LIMIT :limit OFFSET :offset";
      
      $stmt = Database::connect()->prepare($sql);
      $searchTerm = '%' . $search . '%';
      
      $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
      
    } catch (PDOException $e) {
      error_log("Get courses error: " . $e->getMessage());
      return false;
    }
  }

  public static function getTotalCourses($search = '') {
    try {
      $sql = "SELECT COUNT(*) as total 
              FROM courses 
              WHERE course_code LIKE :search OR course_name LIKE :search";
      
      $stmt = Database::connect()->prepare($sql);
      $searchTerm = '%' . $search . '%';
      $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
      
      $stmt->execute();
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      
      return $result ? $result['total'] : 0;
      
    } catch (PDOException $e) {
      error_log("Get total courses error: " . $e->getMessage());
      return false;
    }
  }

  // Scholarships
  public static function getScholarshipById(string $scholarshipId) {
    try {
      $db = Database::connect();
      $stmt = $db->prepare("SELECT * FROM scholarships WHERE scholarship_id = :scholarship_id LIMIT 1");
      $stmt->execute(['scholarship_id' => $scholarshipId]);
      return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
      error_log("Get scholarship error: " . $e->getMessage());
      return false;
    }
  }

  public static function getScholarshipApplication(string $studentId, string $scholarshipId) {
    try {
      $db = Database::connect();
      $stmt = $db->prepare("
        SELECT * FROM applications 
        WHERE student_id = :student_id AND scholarship_id = :scholarship_id 
        ORDER BY applied_at DESC LIMIT 1
      ");
      $stmt->execute([
        'student_id' => $studentId,
        'scholarship_id' => $scholarshipId
      ]);
      return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
      error_log("Get scholarship application error: " . $e->getMessage());
      return false;
    }
  }

  public static function createScholarshipApplication(array $data): bool {
    try {
      $db = Database::connect();
      $stmt = $db->prepare("
        INSERT INTO applications 
        (application_id, student_id, scholarship_id, status, applied_at)
        VALUES (:application_id, :student_id, :scholarship_id, :status, :applied_at)
      ");
      return $stmt->execute([
        'application_id' => $data['application_id'],
        'student_id' => $data['student_id'],
        'scholarship_id' => $data['scholarship_id'],
        'status' => $data['status'],
        'applied_at' => $data['applied_at']
      ]);
    } catch (PDOException $e) {
      error_log("Create scholarship application error: " . $e->getMessage());
      return false;
    }
  }

  public static function createApplicationForm($data) {
    $db = Database::connect();
    $stmt = $db->prepare("INSERT INTO application_forms 
      (application_form_id, application_id, form_name, file_path, uploaded_at) 
      VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([
      $data['application_form_id'],
      $data['application_id'],
      $data['form_name'],
      $data['file_path'],
      $data['uploaded_at']
    ]);
  }

  public static function getApplicationForms($applicationId) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT application_form_id, form_name, uploaded_at 
                          FROM application_forms 
                          WHERE application_id = ?");
    $stmt->execute([$applicationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function getStudentApplications(string $studentId): array {
    try {
      $db = Database::connect();
      $stmt = $db->prepare("SELECT * FROM applications WHERE student_id = :student_id");
      $stmt->execute(['student_id' => $studentId]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get student applications error: " . $e->getMessage());
      return [];
    }
  }

  public static function getScholarships(int $limit, int $offset, string $search = ''): array|false {
    $db = Database::connect();

    if (!empty($search)) {
      $stmt = $db->prepare("
        SELECT id, scholarship_id, scholarship_title, description, amount, status, start_date, end_date, created_at
        FROM scholarships
        WHERE scholarship_title LIKE :search
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
      ");
      $likeSearch = "%$search%";
      $stmt->bindParam(':search', $likeSearch, PDO::PARAM_STR);
    } else {
      $stmt = $db->prepare("
        SELECT id, scholarship_id, scholarship_title, description, amount, status, start_date, end_date, created_at
        FROM scholarships
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
      ");
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    if ($stmt->execute()) {
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return false;
  }

  public static function getTotalScholarshipsCount(string $search = ''): int {
    $db = Database::connect();

    if (!empty($search)) {
      $stmt = $db->prepare("SELECT COUNT(*) as count FROM scholarships WHERE scholarship_title LIKE :search");
      $likeSearch = "%$search%";
      $stmt->bindParam(':search', $likeSearch, PDO::PARAM_STR);
    } else {
      $stmt = $db->prepare("SELECT COUNT(*) as count FROM scholarships");
    }

    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($result['count'] ?? 0);
  }

  public static function getScholarshipCourseCodes(string $scholarshipId): array {
    $db = Database::connect();

    $stmt = $db->prepare("
      SELECT c.course_code
      FROM scholarship_courses sc
      JOIN courses c ON sc.course_id = c.course_id
      WHERE sc.scholarship_id = :scholarship_id
    ");
    $stmt->bindParam(':scholarship_id', $scholarshipId, PDO::PARAM_STR);

    if ($stmt->execute()) {
      $courseCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);

      if (empty($courseCodes)) {
        return ["ALL"];
      }

      return $courseCodes;
    }

    return ["ALL"];
  }

  public static function getScholarshipFormsIds(string $scholarshipId): array|false {
    $db = Database::connect();

    $stmt = $db->prepare("
      SELECT f.scholarship_form_id, f.scholarship_form_name, f.scholarship_form
      FROM scholarship_forms_association sfa
      JOIN scholarship_forms f 
        ON sfa.scholarship_form_id = f.scholarship_form_id
      WHERE sfa.scholarship_id = :scholarship_id
    ");
    $stmt->bindParam(':scholarship_id', $scholarshipId, PDO::PARAM_STR);

    if ($stmt->execute()) {
      $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Prepend full URL to each scholarship_form
      foreach ($forms as &$form) {
        $form['scholarship_form'] = rtrim($_ENV['APP_BASE_URL'], '/') 
          . '/src/uploads/scholarship_forms/' 
          . ltrim($form['scholarship_form'], '/');
      }

      return $forms;
    }
    return false;
  }

  // Applications
  public static function getAllScholarshipApplications(string $studentId, int $limit = 10, int $offset = 0): array {
    try {
      $db = Database::connect();

      $sql = "
        SELECT 
          s.first_name, s.middle_name, s.last_name, s.course, s.phone_number, s.complete_address,
          sc.scholarship_title, sc.description, sc.amount,
          a.status, a.applied_at, a.application_id
        FROM applications a
        LEFT JOIN student s ON a.student_id = s.user_id
        LEFT JOIN scholarships sc ON a.scholarship_id = sc.scholarship_id
        WHERE a.student_id = :student_id
        ORDER BY a.applied_at DESC
        LIMIT :limit OFFSET :offset
      ";

      $stmt = $db->prepare($sql);
      $stmt->bindValue(':student_id', $studentId, PDO::PARAM_STR);
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();

      return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
      error_log("Get all scholarship applications error: " . $e->getMessage());
      return [];
    }
  }

  public static function getTotalApplicationsCount(string $studentId): int {
    try {
      $db = Database::connect();
      $stmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE student_id = :student_id");
      $stmt->execute(['student_id' => $studentId]);
      return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
      error_log("Get total applications count error: " . $e->getMessage());
      return 0;
    }
  }

  // Announcements
  public static function getAnnouncements($limit, $offset, $search = '') {
    $db = Database::connect();

    if (!empty($search)) {
      $stmt = $db->prepare("
        SELECT 
          a.announcement_id,
          a.announcement_title,
          a.announcement_description,
          a.created_at,
          a.updated_at,
          ad.first_name,
          ad.last_name
        FROM announcements a
        JOIN admin ad ON a.admin_id = ad.user_id
        WHERE a.announcement_title LIKE :search 
          OR a.announcement_description LIKE :search
        ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset
      ");
      $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    } else {
      $stmt = $db->prepare("
        SELECT 
          a.announcement_id,
          a.announcement_title,
          a.announcement_description,
          a.created_at,
          a.updated_at,
          ad.first_name,
          ad.last_name
        FROM announcements a
        JOIN admin ad ON a.admin_id = ad.user_id
        ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset
      ");
    }

    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function getTotalAnnouncements($search = '') {
    $db = Database::connect();

    if (!empty($search)) {
      $stmt = $db->prepare("SELECT COUNT(*) as total FROM announcements WHERE announcement_title LIKE :search OR announcement_description LIKE :search");
      $stmt->execute([':search' => "%$search%"]);
    } else {
      $stmt = $db->query("SELECT COUNT(*) as total FROM announcements");
    }

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['total'] : 0;
  }
}
?>
