<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Student;
use App\Core\RateLimiter;
use App\Core\Auth;
use Exception;

class StudentController{
  private function getApiKey(Request $request): ?string {
    // Get Authorization header via Request helper
    $authHeader = $request->getHeader('Authorization');

    if (empty($authHeader)) {
      return null;
    }

    // Clean the API key (remove "Bearer " if present)
    return trim(str_replace('Bearer ', '', $authHeader));
  }

  // Courses
  public function getCourses(Request $request) {
    // $apiKey = $this->getApiKey($request);

    // if (empty($apiKey)) {
    //   http_response_code(401);
    //   return json_encode(['error' => 'API key is required']);
    // }

    // // Rate limit check
    // if (!RateLimiter::check($apiKey)) {
    //   http_response_code(429); // Too Many Requests
    //   return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    // }

    // // Verify CSRF Token
    // $csrfToken = $request->getHeader('X-CSRF-Token');
    // if (empty($csrfToken)) {
    //   http_response_code(403);
    //   return json_encode(['error' => 'CSRF token is required']);
    // }

    // // Verify student authentication using API key
    // $student = Student::findByApiKey($apiKey, $csrfToken);

    // if (!$student) {
    //   http_response_code(401);
    //   return json_encode(['error' => 'Invalid API key or unauthorized access']);
    // }

    // Get query parameters for pagination
    $page = max(1, intval($request->getQuery('page', 1)));
    $limit = max(1, min(100, intval($request->getQuery('limit', 10))));
    $offset = ($page - 1) * $limit;

    // Get optional search parameter
    $search = $request->getQuery('search', '');

    // Get courses with pagination
    $result = Student::getCourses($limit, $offset, $search);

    if ($result === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve courses']);
    }

    // Get total count for pagination metadata
    $totalCourses = Student::getTotalCourses($search);

    if ($totalCourses === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve course count']);
    }

    $totalPages = ceil($totalCourses / $limit);

    return json_encode([
      'success' => true,
      'data' => $result,
      'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_items' => $totalCourses,
        'total_pages' => $totalPages,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ]
    ]);
  }

  // Profile
  public function getProfile(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Rate limit check
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429);
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // CSRF token check
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Find student by API key and CSRF token
    $student = Student::findByApiKey($apiKey, $csrfToken);

    if (!$student) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Only keep the fields you want
    $profile = [
      'first_name'        => $student['first_name'] ?? '',
      'middle_name'       => $student['middle_name'] ?? '',
      'last_name'         => $student['last_name'] ?? '',
      'student_number'    => $student['student_number'] ?? '',
      'email'             => $student['email'] ?? '',
      'phone_number'      => $student['phone_number'] ?? '',
      'course'            => $student['course'] ?? '',
      'year_level'        => $student['year_level'] ?? '',
      'complete_address'  => $student['complete_address'] ?? '',
      'picture'           => $_ENV['APP_BASE_URL'].'/src/uploads/'.$student['picture'] ?? ''
    ];

    return json_encode([
      'success' => true,
      'data' => $profile
    ]);
  }

  public function updateProfile(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Rate limit check
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429);
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // CSRF token check
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Find student by API key and CSRF token
    $student = Student::findByApiKey($apiKey, $csrfToken);
    if (!$student) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get request body
    $data = $request->body();

    // Only allow updating specific student fields
    $updateData = [];
    if (isset($data['first_name'])) {
      $updateData['first_name'] = ucwords(strtolower(trim($data['first_name'])));
    }
    if (isset($data['middle_name'])) {
      $updateData['middle_name'] = ucwords(strtolower(trim($data['middle_name'])));
    }
    if (isset($data['last_name'])) {
      $updateData['last_name'] = ucwords(strtolower(trim($data['last_name'])));
    }
    if (isset($data['phone_number'])) {
      $updateData['phone_number'] = trim($data['phone_number']);
    }
    if (isset($data['complete_address'])) {
      $updateData['complete_address'] = trim($data['complete_address']);
    }

    if (empty($updateData)) {
      http_response_code(400);
      return json_encode(['error' => 'Nothing to update']);
    }

    // Update student
    $updated = Student::updateProfileByApiKey($apiKey, $csrfToken, $updateData);

    if ($updated) {
      return json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'data' => $updateData
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to update profile']);
    }
  }

  // Scholarship
  public function applyScholarship(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    if (!RateLimiter::check($apiKey)) {
      http_response_code(429);
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    $student = Student::findByApiKey($apiKey, $csrfToken);
    if (!$student) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Validate student profile fields
    $requiredFields = ['picture', 'school_id', 'certificate_of_indigency', 'certificate_of_registration'];
    foreach ($requiredFields as $field) {
      if (empty($student[$field])) {
        http_response_code(400);
        return json_encode(['error' => "Student must have '{$field}' before applying"]);
      }
    }

    // Get scholarship_id from request
    $scholarshipId = $_POST['scholarship_id'] ?? '';
    if (empty($scholarshipId)) {
      http_response_code(400);
      return json_encode(['error' => 'Scholarship ID is required']);
    }

    // Fetch scholarship
    $scholarship = Student::getScholarshipById($scholarshipId);
    if (!$scholarship) {
      http_response_code(404);
      return json_encode(['error' => 'Scholarship not found']);
    }

    if ($scholarship['status'] !== 'active') {
      http_response_code(400);
      return json_encode(['error' => 'Scholarship is not active']);
    }

    $today = strtotime(date('Y-m-d'));
    $startDate = strtotime($scholarship['start_date']);
    $endDate = strtotime($scholarship['end_date']);

    if ($today < $startDate || $today > $endDate) {
      http_response_code(400);
      return json_encode(['error' => 'Scholarship is not open for application at this time']);
    }

    // Get required scholarship forms
    $requiredForms = Student::getScholarshipFormsIds($scholarshipId);
    if (!$requiredForms || count($requiredForms) === 0) {
      http_response_code(400);
      return json_encode(['error' => 'This scholarship has no required forms defined']);
    }

    // Check if student uploaded all required forms
    $uploadedFiles = $_FILES['files'] ?? [];
    foreach ($requiredForms as $form) {
      $formName = $form['scholarship_form_name'];
      $fileKey = "files[{$formName}]";
      
      // Check if file was uploaded in current request
      if (empty($uploadedFiles['name'][$formName]) || 
        $uploadedFiles['size'][$formName] === 0) {
        http_response_code(400);
        return json_encode(['error' => "Missing required form: {$formName}"]);
      }
      
      // Optional: Validate file type and size
      $maxFileSize = 5 * 1024 * 1024; // 5MB
      if ($uploadedFiles['size'][$formName] > $maxFileSize) {
        http_response_code(400);
        return json_encode(['error' => "File too large: {$formName}. Maximum size is 5MB"]);
      }
      
      $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
      if (!in_array($uploadedFiles['type'][$formName], $allowedTypes)) {
        http_response_code(400);
        return json_encode(['error' => "Invalid file type for: {$formName}. Only PDF, JPEG, PNG allowed"]);
      }
    }

    // Process file uploads
    $uploadedFilePaths = [];
    foreach ($requiredForms as $form) {
      $formName = $form['scholarship_form_name'];
      $fileData = [
        'name' => $uploadedFiles['name'][$formName],
        'type' => $uploadedFiles['type'][$formName],
        'tmp_name' => $uploadedFiles['tmp_name'][$formName],
        'error' => $uploadedFiles['error'][$formName],
        'size' => $uploadedFiles['size'][$formName]
      ];
      
      $uploadResult = $this->uploadScholarshipFile($fileData, $student['user_id'], $formName);
      if (!$uploadResult['success']) {
        http_response_code(400);
        return json_encode(['error' => "Failed to upload {$formName}: " . $uploadResult['error']]);
      }
      
      $uploadedFilePaths[$formName] = $uploadResult['file_path'];
    }

    // Check all existing applications for this student
    $existingApps = Student::getStudentApplications($student['user_id']);
    $hasActiveApplication = false;

    foreach ($existingApps as $app) {
      if ($app['status'] !== 'rejected') {
        $hasActiveApplication = true;
        break;
      }
    }

    if ($hasActiveApplication) {
      http_response_code(400);
      return json_encode(['error' => 'You cannot apply for a new scholarship while you have active or pending applications']);
    }

    // Insert new application
    $applicationData = [
      'application_id' => bin2hex(random_bytes(16)),
      'student_id' => $student['user_id'],
      'scholarship_id' => $scholarshipId,
      'status' => 'pending',
      'applied_at' => date('Y-m-d H:i:s'),
      'uploaded_forms' => json_encode($uploadedFilePaths)
    ];

    $created = Student::createScholarshipApplication($applicationData);

    if ($created) {
      foreach ($uploadedFilePaths as $formName => $filePath) {
        Student::createApplicationForm([
          'application_form_id' => bin2hex(random_bytes(16)),
          'application_id' => $applicationData['application_id'],
          'form_name' => $formName,
          'file_path' => $filePath,
          'uploaded_at' => date('Y-m-d H:i:s')
        ]);
      }

      return json_encode([
        'success' => true,
        'message' => 'Scholarship application submitted successfully',
        'application_id' => $applicationData['application_id'],
        'required_forms' => $requiredForms,
        'uploaded_files' => $uploadedFilePaths
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to submit scholarship application']);
    }
  }

  private function uploadScholarshipFile($file, $studentId, $formName) {
    $uploadDir = __DIR__ . "/../uploads/scholarships/{$studentId}/";
    
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $formName . '_' . time() . '.' . $fileExtension;
    $fullFilePath = $uploadDir . $filename; 
    $relativeFilePath = $studentId . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $fullFilePath)) {
      return [
        'success' => true,
        'file_path' => $relativeFilePath
      ];
    } else {
      return ['success' => false, 'error' => 'File upload failed'];
    }
  }

  public function getScholarships(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    if (!RateLimiter::check($apiKey)) {
      http_response_code(429);
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    $student = Student::findByApiKey($apiKey, $csrfToken);
    if (!$student) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get student course
    $studentCourse = $student['course'] ?? null;

    // Pagination + search
    $queryParams = $_GET;
    $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
    $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    $offset = ($page - 1) * $limit;
    $search = isset($queryParams['search']) ? trim($queryParams['search']) : '';

    // Fetch scholarships
    $scholarships = Student::getScholarships($limit, $offset, $search);
    $totalScholarships = Student::getTotalScholarshipsCount($search);

    $filteredScholarships = [];
    foreach ($scholarships as &$scholarship) {
      $courseCodes = Student::getScholarshipCourseCodes($scholarship['scholarship_id']) ?? [];
      $scholarship['course_codes'] = $courseCodes;
      $scholarship['scholarship_forms'] = Student::getScholarshipFormsIds($scholarship['scholarship_id']) ?? [];

      // Show only if ALL or student’s course matches
      if (in_array("ALL", $courseCodes) || in_array($studentCourse, $courseCodes)) {
        $filteredScholarships[] = $scholarship;
      }
    }

    // Recalculate total for filtered scholarships
    $totalScholarships = count($filteredScholarships);
    $totalPages = ceil($totalScholarships / $limit);

    return json_encode([
      'success' => true,
      'data' => $filteredScholarships,
      'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_items' => $totalScholarships,
        'total_pages' => $totalPages,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ]
    ]);
  }

  // Application
  public function getApplications(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    if (!RateLimiter::check($apiKey)) {
      http_response_code(429);
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    $student = Student::findByApiKey($apiKey, $csrfToken);
    if (!$student) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get pagination parameters
    $queryParams = $_GET;
    $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
    $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    $offset = ($page - 1) * $limit;

    // Fetch applications
    $applications = Student::getAllScholarshipApplications($student['user_id'], $limit, $offset);

    // Attach application_forms sa bawat application
    foreach ($applications as &$application) {
      $application['forms'] = Student::getApplicationForms($application['application_id']);
    }

    $totalApplications = Student::getTotalApplicationsCount($student['user_id']);
    $totalPages = ceil($totalApplications / $limit);

    return json_encode([
      'success' => true,
      'data' => $applications,
      'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_items' => $totalApplications,
        'total_pages' => $totalPages,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ]
    ]);
  }

  // Announcements
  public function getAnnouncements(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    if (!RateLimiter::check($apiKey)) {
      http_response_code(429);
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Validate student
    $student = Student::findByApiKey($apiKey, $csrfToken);
    if (!$student) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    $page = max(1, intval($request->getQuery('page', 1)));
    $limit = max(1, min(100, intval($request->getQuery('limit', 10))));
    $offset = ($page - 1) * $limit;
    $search = $request->getQuery('search', '');

    // Fetch all announcements
    $announcements = Student::getAnnouncements($limit, $offset, $search);

    // Optional: filter by student course if announcements are course-specific
    $studentCourse = $student['course'] ?? null;
    $filtered = [];
    foreach ($announcements as $announcement) {
      $courses = $announcement['course_codes'] ?? ['ALL'];
      if (in_array('ALL', $courses) || in_array($studentCourse, $courses)) {
        $filtered[] = $announcement;
      }
    }

    $total = count($filtered);
    $totalPages = ceil($total / $limit);

    return json_encode([
      'success' => true,
      'data' => $filtered,
      'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_items' => $total,
        'total_pages' => $totalPages,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ]
    ]);
  }
}

?>