<?php
namespace App\Models;

use App\Core\Database;
use App\Models\Auth;
use PDO;
use PDOException;

class Admin {
  public static function findByApiKey(string $apiKey, ?string $csrfToken = null) {
    try {
      $stmt = Database::connect()->prepare("SELECT * FROM admin WHERE api_key = :api_key LIMIT 1");
      $stmt->execute(['api_key' => $apiKey]);
      $admin = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$admin) {
        return false;
      }

      // Use centralized CSRF validator if token is provided
      if ($csrfToken !== null) {
        $isValid = Auth::validateAdminCsrfToken($admin['user_id'], $csrfToken);
        if (!$isValid) {
          return false;
        }
      }

      return $admin;
    } catch (PDOException $e) {
      error_log("Find by API key error: " . $e->getMessage());
      return false;
    }
  }

  // Course
  public static function createCourse(array $data) {
    try {
      $sql = "INSERT INTO courses (
        course_id, course_code, course_name, created_at, updated_at
      ) VALUES (
        :course_id, :course_code, :course_name, NOW(), NOW()
      )";

      $stmt = Database::connect()->prepare($sql);
      
      $params = [
        'course_id' => $data['course_id'],
        'course_code' => $data['course_code'],
        'course_name' => $data['course_name']
      ];

      $result = $stmt->execute($params);

      return $result;

    } catch (PDOException $e) {
      error_log("Create course error: " . $e->getMessage());
      return false;
    }
  }

  public static function updateCourse(string $courseId, array $data) {
    $db = Database::connect();
    $stmt = $db->prepare("UPDATE courses SET course_code = :course_code, course_name = :course_name WHERE course_id = :course_id");
    return $stmt->execute([
      ':course_code' => $data['course_code'],
      ':course_name' => $data['course_name'],
      ':course_id'   => $courseId
    ]);
  }

  public static function deleteCourseById(string $courseId): bool {
    $db = Database::connect();
    $stmt = $db->prepare("DELETE FROM courses WHERE course_id = :course_id");
    return $stmt->execute([':course_id' => $courseId]);
  }

  public static function deleteAllCourses(): int {
    $db = Database::connect();
    $stmt = $db->prepare("DELETE FROM courses");
    $stmt->execute();
    return $stmt->rowCount(); 
  }

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

  public static function findCourseByCode(string $courseCode) {
    $stmt = Database::connect()->prepare("SELECT * FROM courses WHERE course_code = :course_code LIMIT 1");
    $stmt->execute(['course_code' => $courseCode]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // Department
  public static function createDepartment(array $data) {
    try {
      $sql = "INSERT INTO departments (
        department_id, department_name, created_at, updated_at
      ) VALUES (
        :department_id, :department_name, NOW(), NOW()
      )";

      $stmt = Database::connect()->prepare($sql);

      $params = [
        'department_id'   => $data['department_id'],
        'department_name' => $data['department_name']
      ];

      return $stmt->execute($params);

    } catch (PDOException $e) {
      error_log("Create department error: " . $e->getMessage());
      return false;
    }
  }

  public static function updateDepartment(string $departmentId, array $data) {
    $db = Database::connect();
    $stmt = $db->prepare("UPDATE departments SET department_name = :department_name WHERE department_id = :department_id");
    return $stmt->execute([
      ':department_name' => $data['department_name'],
      ':department_id'   => $departmentId
    ]);
  }

  public static function deleteDepartmentById(string $departmentId): bool {
    $db = Database::connect();
    $stmt = $db->prepare("DELETE FROM departments WHERE department_id = :department_id");
    return $stmt->execute([':department_id' => $departmentId]);
  }

  public static function deleteAllDepartments(): int {
    $db = Database::connect();
    $stmt = $db->prepare("DELETE FROM departments");
    $stmt->execute();
    return $stmt->rowCount();
  }

  public static function getDepartments($limit = 10, $offset = 0, $search = '') {
    try {
      $sql = "SELECT department_id, department_name, created_at, updated_at
              FROM departments
              WHERE department_name LIKE :search
              ORDER BY department_name
              LIMIT :limit OFFSET :offset";
      
      $stmt = Database::connect()->prepare($sql);
      $searchTerm = '%' . $search . '%';
      
      $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
      $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
      
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
      
    } catch (PDOException $e) {
      error_log("Get departments error: " . $e->getMessage());
      return false;
    }
  }

  public static function getTotalDepartments($search = '') {
    try {
      $sql = "SELECT COUNT(*) as total 
              FROM departments 
              WHERE department_id LIKE :search OR department_name LIKE :search";
      
      $stmt = Database::connect()->prepare($sql);
      $searchTerm = '%' . $search . '%';
      $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
      
      $stmt->execute();
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      
      return $result ? $result['total'] : 0;
      
    } catch (PDOException $e) {
      error_log("Get total departments error: " . $e->getMessage());
      return false;
    }
  }

  public static function findDepartmentById(string $departmentId) {
    $stmt = Database::connect()->prepare("SELECT * FROM departments WHERE department_id = :department_id LIMIT 1");
    $stmt->execute(['department_id' => $departmentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
  
  public static function findDepartmentByName(string $departmentName) {
    $stmt = Database::connect()->prepare("SELECT * FROM departments WHERE department_name = :department_name LIMIT 1");
    $stmt->execute(['department_name' => $departmentName]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // Scholarship forms
  public static function createScholarshipForm($data) {
    $db = Database::connect();
    
    $stmt = $db->prepare("
      INSERT INTO scholarship_forms 
      (scholarship_form_id, scholarship_form_name, scholarship_form, created_at, updated_at) 
      VALUES (:form_id, :form_name, :form_file, NOW(), NOW())
    ");
    
    $stmt->bindParam(':form_id', $data['scholarship_form_id'], PDO::PARAM_STR);
    $stmt->bindParam(':form_name', $data['scholarship_form_name'], PDO::PARAM_STR);
    $stmt->bindParam(':form_file', $data['scholarship_form'], PDO::PARAM_STR);
    
    return $stmt->execute();
  }

  public static function updateScholarshipForm($formId, $updateData) {
    $db = Database::connect();
    
    $setClauses = [];
    $params = [':formId' => $formId];
    
    if (isset($updateData['scholarship_form_name'])) {
      $setClauses[] = 'scholarship_form_name = :formName';
      $params[':formName'] = $updateData['scholarship_form_name'];
    }
    
    if (isset($updateData['scholarship_form'])) {
      $setClauses[] = 'scholarship_form = :formFile';
      $params[':formFile'] = $updateData['scholarship_form'];
    }
    
    $setClauses[] = 'updated_at = NOW()';
    
    $sql = "UPDATE scholarship_forms SET " . implode(', ', $setClauses) . " WHERE scholarship_form_id = :formId";
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    
    return $stmt->execute();
  }
  
  public static function findScholarshipFormByName($formName) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM scholarship_forms WHERE scholarship_form_name = :formName");
    $stmt->bindParam(':formName', $formName, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function findScholarshipFormById($formId) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM scholarship_forms WHERE scholarship_form_id = :formId");
    $stmt->bindParam(':formId', $formId, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function getAllScholarshipForms() {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM scholarship_forms");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function deleteScholarshipFormById($formId) {
    $db = Database::connect();
    $stmt = $db->prepare("DELETE FROM scholarship_forms WHERE scholarship_form_id = :formId");
    $stmt->bindParam(':formId', $formId, PDO::PARAM_STR);
    return $stmt->execute();
  }

  public static function deleteAllScholarshipForms() {
    $db = Database::connect();
    $stmt = $db->prepare("DELETE FROM scholarship_forms");
    return $stmt->execute();
  }

  public static function getScholarshipForms($limit, $offset, $search = '') {
    $db = Database::connect();
    
    if (!empty($search)) {
      $stmt = $db->prepare("
        SELECT * FROM scholarship_forms 
        WHERE scholarship_form_name LIKE :search 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
      ");
      $searchTerm = '%' . $search . '%';
      $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    } else {
      $stmt = $db->prepare("
        SELECT * FROM scholarship_forms 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
      ");
    }
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function getTotalScholarshipForms($search = '') {
    $db = Database::connect();
    
    if (!empty($search)) {
      $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM scholarship_forms 
        WHERE scholarship_form_name LIKE :search
      ");
      $searchTerm = '%' . $search . '%';
      $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    } else {
      $stmt = $db->prepare("SELECT COUNT(*) as total FROM scholarship_forms");
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? (int)$result['total'] : false;
  }

  // Profile
  public static function updateProfileByApiKey(string $apiKey, string $csrfToken, array $data): bool {
    if (empty($data)) {
      return false;
    }

    $fields = [];
    $params = [
      ':api_key' => $apiKey,
      ':csrf_token' => $csrfToken
    ];

    if (isset($data['first_name'])) {
      $fields[] = 'first_name = :first_name';
      $params[':first_name'] = $data['first_name'];
    }
    if (isset($data['last_name'])) {
      $fields[] = 'last_name = :last_name';
      $params[':last_name'] = $data['last_name'];
    }
    if (isset($data['department'])) {
      $fields[] = 'department = :department';
      $params[':department'] = $data['department'];
    }

    if (empty($fields)) {
      return false;
    }

    $sql = "UPDATE admin SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE api_key = :api_key AND csrf_token = :csrf_token";

    try {
      $db = Database::connect();
      $stmt = $db->prepare($sql);
      return $stmt->execute($params);
    } catch (PDOException $e) {
      // Optionally log the error
      return false;
    }
  }

  // Scholarship
  public static function findScholarshipByTitle($title) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM scholarships WHERE scholarship_title = :title");
    $stmt->bindParam(':title', $title, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function createScholarship($data) {
    $db = Database::connect();
    
    $stmt = $db->prepare("
      INSERT INTO scholarships 
      (scholarship_id, scholarship_title, description, amount, status, start_date, end_date, created_at, updated_at) 
      VALUES (:scholarship_id, :scholarship_title, :description, :amount, :status, :start_date, :end_date, NOW(), NOW())
    ");
    
    $amount = isset($data['amount']) ? $data['amount'] : null;
    
    $stmt->bindParam(':scholarship_id', $data['scholarship_id'], PDO::PARAM_STR);
    $stmt->bindParam(':scholarship_title', $data['scholarship_title'], PDO::PARAM_STR);
    $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
    $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
    $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
    $stmt->bindParam(':start_date', $data['start_date'], PDO::PARAM_STR);
    $stmt->bindParam(':end_date', $data['end_date'], PDO::PARAM_STR);
    
    $stmt->execute();

    // return your business id (scholarship_id string)
    return $data['scholarship_id'];
  }

  public static function createScholarshipCourse($scholarshipId, $courseId) {
    $db = Database::connect();
    
    $stmt = $db->prepare("
      INSERT INTO scholarship_courses 
      (scholarship_id, course_id, created_at) 
      VALUES (:scholarship_id, :course_id, NOW())
    ");
    
    $stmt->bindParam(':scholarship_id', $scholarshipId, PDO::PARAM_STR);
    $stmt->bindParam(':course_id', $courseId, PDO::PARAM_STR);
    
    return $stmt->execute();
  }

  public static function createScholarshipFormAssociation($scholarshipId, $formId) {
    $db = Database::connect();
    
    $stmt = $db->prepare("
      INSERT INTO scholarship_forms_association 
      (scholarship_id, scholarship_form_id, created_at) 
      VALUES (:scholarship_id, :form_id, NOW())
    ");
    
    $stmt->bindParam(':scholarship_id', $scholarshipId, PDO::PARAM_STR);
    $stmt->bindParam(':form_id', $formId, PDO::PARAM_STR);
    
    return $stmt->execute();
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

  public static function getAllCourses() {
    $db = Database::getInstance()->getConnection();
    
    try {
      $stmt = $db->prepare("SELECT course_id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_code");
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Error fetching all courses: " . $e->getMessage());
      return false;
    }
  }

  public static function getTotalScholarships(string $search = ''): int|false {
    $db = Database::connect();

    if (!empty($search)) {
      $stmt = $db->prepare("SELECT COUNT(*) FROM scholarships WHERE scholarship_title LIKE :search");
      $likeSearch = "%$search%";
      $stmt->bindParam(':search', $likeSearch, PDO::PARAM_STR);
    } else {
      $stmt = $db->prepare("SELECT COUNT(*) FROM scholarships");
    }

    if ($stmt->execute()) {
      return (int) $stmt->fetchColumn();
    }
    return false;
  }

  public static function getScholarshipCourseCodes(string $scholarshipId): array|false {
    $db = Database::connect();

    $stmt = $db->prepare("
      SELECT c.course_code
      FROM scholarship_courses sc
      JOIN courses c ON sc.course_id = c.course_id
      WHERE sc.scholarship_id = :scholarship_id
    ");
    $stmt->bindParam(':scholarship_id', $scholarshipId, PDO::PARAM_STR);

    if ($stmt->execute()) {
      return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return false;
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
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return false;
  }

  public static function findScholarshipById(string $scholarshipId): array|false {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM scholarships WHERE scholarship_id = :id LIMIT 1");
    $stmt->bindParam(':id', $scholarshipId, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function updateScholarship(array $data): bool {
    $db = Database::connect();

    $stmt = $db->prepare("
      UPDATE scholarships
      SET scholarship_title = COALESCE(:title, scholarship_title),
          description = COALESCE(:description, description),
          amount = COALESCE(:amount, amount),
          status = COALESCE(:status, status),
          start_date = COALESCE(:start_date, start_date),
          end_date = COALESCE(:end_date, end_date),
          updated_at = NOW()
      WHERE scholarship_id = :id
    ");

    $stmt->bindValue(':title', $data['scholarship_title'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':description', $data['description'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':amount', $data['amount'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':status', $data['status'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':start_date', $data['start_date'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':end_date', $data['end_date'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':id', $data['scholarship_id'], PDO::PARAM_STR);

    return $stmt->execute();
  }

  public static function deleteScholarshipCourses(string $scholarshipId): bool {
    $db = Database::connect();
    $stmt = $db->prepare("DELETE FROM scholarship_courses WHERE scholarship_id = :id");
    $stmt->bindParam(':id', $scholarshipId, PDO::PARAM_STR);
    return $stmt->execute();
  }

  public static function deleteScholarshipFormAssociations(string $scholarshipId): bool {
    $db = Database::connect();
    $stmt = $db->prepare("DELETE FROM scholarship_forms_association WHERE scholarship_id = :id");
    $stmt->bindParam(':id', $scholarshipId, PDO::PARAM_STR);
    return $stmt->execute();
  }

  public static function deleteScholarship(string $scholarshipId): bool {
    $db = Database::connect();
    $stmt = $db->prepare("DELETE FROM scholarships WHERE scholarship_id = :id");
    $stmt->bindParam(':id', $scholarshipId, PDO::PARAM_STR);
    return $stmt->execute();
  }

  public static function softDeleteScholarship(string $scholarshipId): bool {
    $db = Database::connect();
    $stmt = $db->prepare("
        UPDATE scholarships 
        SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() 
        WHERE scholarship_id = :id
    ");
    $stmt->bindParam(':id', $scholarshipId, PDO::PARAM_STR);
    return $stmt->execute();
  }

  // Announcements
  public static function createAnnouncement($data) {
    $db = Database::connect();

    $stmt = $db->prepare("
      INSERT INTO announcements 
        (announcement_id, admin_id, announcement_title, announcement_description, created_at, updated_at) 
      VALUES 
        (:announcement_id, :admin_id, :title, :description, :created_at, :updated_at)
    ");

    return $stmt->execute([
      ':announcement_id' => $data['announcement_id'],
      ':admin_id' => $data['admin_id'],
      ':title' => $data['announcement_title'],
      ':description' => $data['announcement_description'],
      ':created_at' => $data['created_at'],
      ':updated_at' => $data['updated_at']
    ]);
  }

  public static function findAnnouncementById($announcementId) {
    $db = Database::connect();

    $stmt = $db->prepare("SELECT * FROM announcements WHERE announcement_id = :announcement_id LIMIT 1");
    $stmt->execute([':announcement_id' => $announcementId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

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

  public static function updateAnnouncement($announcementId, $data) {
    $db = Database::connect();

    $stmt = $db->prepare("
      UPDATE announcements 
      SET announcement_title = :title,
          announcement_description = :description,
          updated_at = :updated_at
      WHERE announcement_id = :id
    ");

    return $stmt->execute([
      ':title' => $data['announcement_title'],
      ':description' => $data['announcement_description'],
      ':updated_at' => date('Y-m-d H:i:s'),
      ':id' => $announcementId
    ]);
  }

  public static function deleteAnnouncementById($announcementId) {
    $db = Database::connect();

    $stmt = $db->prepare("DELETE FROM announcements WHERE announcement_id = :id");
    return $stmt->execute([':id' => $announcementId]);
  }

  public static function deleteAllAnnouncements() {
    $db = Database::connect();

    $stmt = $db->prepare("DELETE FROM announcements");
    $stmt->execute();
    return $stmt->rowCount();
  }

  // Accounts
  public static function getAdminsList(int $limit, int $offset, string $search = '', string $status = 'all'): array|false {
    $db = Database::connect();

    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
      $whereConditions[] = "(CONCAT(a.first_name, ' ', a.last_name) LIKE :search 
        OR a.email LIKE :search
        OR d.department_name LIKE :search)";
      $params[':search'] = "%$search%";
    }

    if ($status !== 'all') {
      $whereConditions[] = "a.status = :status";
      $params[':status'] = $status;
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
      $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    $stmt = $db->prepare("
      SELECT 
        a.user_id, 
        a.first_name, 
        a.last_name, 
        a.email, 
        a.department, 
        d.department_name,
        a.status, 
        a.created_at, 
        a.updated_at
      FROM admin a
      LEFT JOIN departments d ON a.department = d.department_id
      $whereClause
      ORDER BY 
        CASE 
          WHEN a.status = 'pending' THEN 1
          WHEN a.status = 'approved' THEN 2
          WHEN a.status = 'declined' THEN 3
          ELSE 4
        END,
        a.created_at DESC
      LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    if ($stmt->execute()) {
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return false;
  }

  public static function getTotalAdminsCount(string $search = '', string $status = 'all'): int|false {
    $db = Database::connect();

    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
      $whereConditions[] = "(CONCAT(first_name, ' ', last_name) LIKE :search 
        OR email LIKE :search
        OR department LIKE :search)";
      $params[':search'] = "%$search%";
    }

    if ($status !== 'all') {
      $whereConditions[] = "status = :status";
      $params[':status'] = $status;
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
      $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM admin $whereClause");

    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    if ($stmt->execute()) {
      return (int) $stmt->fetchColumn();
    }
    return false;
  }

  public static function findAdminById(string $userId): array|false {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM admin WHERE user_id = :user_id LIMIT 1");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function updateAdminStatus(string $userId, string $status): bool {
    $db = Database::connect();
    $stmt = $db->prepare("
      UPDATE admin 
      SET status = :status, updated_at = NOW() 
      WHERE user_id = :user_id
    ");
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_STR);
    return $stmt->execute();
  }

  public static function deleteAdmin(string $userId): bool {
    $db = Database::connect();
    $stmt = $db->prepare("DELETE FROM admin WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_STR);
    return $stmt->execute();
  }

  // Applications
  public static function getAllApplications(int $limit, int $offset, string $search = '', string $status = 'all'): array|false {
    $db = Database::connect();

    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
      $whereConditions[] = "(CONCAT(s.first_name, ' ', s.last_name) LIKE :search 
        OR s.student_number LIKE :search
        OR sc.scholarship_title LIKE :search)";
      $params[':search'] = "%$search%";
    }

    if ($status !== 'all') {
      $whereConditions[] = "a.status = :status";
      $params[':status'] = $status;
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
      $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    $stmt = $db->prepare("
      SELECT 
        a.application_id,
        a.student_id,
        CONCAT(s.first_name, ' ', s.last_name) AS student_name,
        s.student_number,
        s.course,
        a.scholarship_id,
        sc.scholarship_title,
        a.status,
        a.applied_at,
        a.updated_at
      FROM applications a
      LEFT JOIN student s ON a.student_id = s.user_id
      LEFT JOIN scholarships sc ON a.scholarship_id = sc.scholarship_id
      $whereClause
      ORDER BY 
        CASE 
          WHEN a.status = 'pending' THEN 1
          WHEN a.status = 'approved' THEN 2
          WHEN a.status = 'declined' THEN 3
          ELSE 4
        END,
        a.applied_at DESC
      LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    if ($stmt->execute()) {
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return false;
  }

  public static function getTotalApplicationsCount(string $search = '', string $status = 'all'): int {
    $db = Database::connect();

    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
      $whereConditions[] = "(CONCAT(s.first_name, ' ', s.last_name) LIKE :search 
        OR s.student_number LIKE :search
        OR sc.scholarship_title LIKE :search)";
      $params[':search'] = "%$search%";
    }

    if ($status !== 'all') {
      $whereConditions[] = "a.status = :status";
      $params[':status'] = $status;
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
      $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    $stmt = $db->prepare("
      SELECT COUNT(*) as total
      FROM applications a
      LEFT JOIN student s ON a.student_id = s.user_id
      LEFT JOIN scholarships sc ON a.scholarship_id = sc.scholarship_id
      $whereClause
    ");

    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) $row['total'];
  }

  public static function updateApplicationStatus(string $applicationId, string $status): bool {
    $db = Database::connect();

    $stmt = $db->prepare("
      UPDATE applications
      SET status = :status, updated_at = NOW()
      WHERE application_id = :application_id
    ");
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':application_id', $applicationId, PDO::PARAM_INT);

    return $stmt->execute();
  }

  public static function getStudentByApplicationId(string $applicationId): array|false {
    $db = Database::connect();

    $stmt = $db->prepare("
      SELECT s.email, s.first_name
      FROM applications a
      INNER JOIN student s ON a.student_id = s.user_id
      WHERE a.application_id = :application_id
    ");
    $stmt->bindParam(':application_id', $applicationId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function getApplicationById(string $application_id) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM applications WHERE application_id = :application_id LIMIT 1");
    $stmt->execute([':application_id' => $application_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function getApplicationForms($applicationId) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT form_name, file_path, uploaded_at 
                          FROM application_forms 
                          WHERE application_id = :application_id");
    $stmt->execute(['application_id' => $applicationId]);
    $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Attach full URL for each form file
    foreach ($forms as &$form) {
      $form['file_url'] = rtrim($_ENV['APP_BASE_URL'], '/') . '/src/uploads/scholarships/' . ltrim($form['file_path'], '/');
    }

    return $forms;
  }

  // Dashboard
  public static function getStats(int $year): array {
    $db = Database::connect();

    // Total students
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM student WHERE YEAR(created_at) = :year");
    $stmt->execute([':year' => $year]);
    $totalStudents = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total scholars (approved applications)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM applications WHERE status = 'approved' AND YEAR(applied_at) = :year");
    $stmt->execute([':year' => $year]);
    $totalScholars = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total applications
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM applications WHERE YEAR(applied_at) = :year");
    $stmt->execute([':year' => $year]);
    $totalApplications = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total pending applications
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM applications WHERE status = 'pending' AND YEAR(applied_at) = :year");
    $stmt->execute([':year' => $year]);
    $totalPending = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Monthly applicants
    $stmt = $db->prepare("
      SELECT MONTHNAME(applied_at) as month, COUNT(*) as total
      FROM applications
      WHERE YEAR(applied_at) = :year
      GROUP BY MONTH(applied_at), MONTHNAME(applied_at)
      ORDER BY MONTH(applied_at)
    ");
    $stmt->execute([':year' => $year]);
    $monthlyApplicants = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Monthly scholars (approved)
    $stmt = $db->prepare("
      SELECT MONTHNAME(applied_at) as month, COUNT(*) as total
      FROM applications
      WHERE status = 'approved' AND YEAR(applied_at) = :year
      GROUP BY MONTH(applied_at), MONTHNAME(applied_at)
      ORDER BY MONTH(applied_at)
    ");
    $stmt->execute([':year' => $year]);
    $monthlyScholars = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Application status distribution
    $stmt = $db->prepare("
      SELECT status, COUNT(*) as total
      FROM applications
      WHERE YEAR(applied_at) = :year
      GROUP BY status
    ");
    $stmt->execute([':year' => $year]);
    $statusDistribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Top scholarships by applications
    $stmt = $db->prepare("
      SELECT sc.scholarship_title, COUNT(a.id) as total_applications
      FROM applications a
      JOIN scholarships sc ON a.scholarship_id = sc.scholarship_id
      WHERE YEAR(a.applied_at) = :year
      GROUP BY sc.scholarship_id, sc.scholarship_title
      ORDER BY total_applications DESC
      LIMIT 5
    ");
    $stmt->execute([':year' => $year]);
    $topScholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Most active courses (based on applications)
    $stmt = $db->prepare("
      SELECT s.course, COUNT(a.id) as total_applications
      FROM applications a
      JOIN student s ON a.student_id = s.user_id
      WHERE YEAR(a.applied_at) = :year
      GROUP BY s.course
      ORDER BY total_applications DESC
      LIMIT 1
    ");
    $stmt->execute([':year' => $year]);
    $topCourse = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
      'totals' => [
        'students' => $totalStudents,
        'scholars' => $totalScholars,
        'applications' => $totalApplications,
        'pending_applications' => $totalPending
      ],
      'monthly' => [
        'applicants' => $monthlyApplicants,
        'scholars' => $monthlyScholars
      ],
      'applications_by_status' => $statusDistribution,
      'top_scholarships' => $topScholarships,
      'top_course' => $topCourse
    ];
  }
}