<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Admin;
use App\Core\RateLimiter;
use App\Core\Auth;
use Exception;

class AdminController {
  private function getApiKey(Request $request): ?string {
    // Get Authorization header via Request helper
    $authHeader = $request->getHeader('Authorization');

    if (empty($authHeader)) {
      return null;
    }

    // Clean the API key (remove "Bearer " if present)
    return trim(str_replace('Bearer ', '', $authHeader));
  }

  // Course
  public function createCourse(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429); // Too Many Requests
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);

    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    $data = $request->body();

    $data['course_code'] = strtoupper($data['course_code']);
    $data['course_name'] = ucwords(strtolower($data['course_name']));

    // Validate required fields
    if (empty($data['course_code']) || empty($data['course_name'])) {
      http_response_code(400);
      return json_encode(['error' => 'Course code and course name are required']);
    }

    // Check if course code already exists
    if (Admin::findCourseByCode($data['course_code'])) {
      http_response_code(409);
      return json_encode(['error' => 'Course code already exists']);
    }

    // Generate course ID
    $data['course_id'] = bin2hex(random_bytes(16));

    // Create course
    if (Admin::createCourse($data)) {
      http_response_code(201);
      return json_encode([
        'success' => true, 
        'message' => 'Course created successfully',
        'course_id' => $data['course_id']
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to create course']);
    }
  }

  public function getCourses(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429); // Too Many Requests
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);

    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get query parameters for pagination
    $page = max(1, intval($request->getQuery('page', 1)));
    $limit = max(1, min(100, intval($request->getQuery('limit', 10))));
    $offset = ($page - 1) * $limit;

    // Get optional search parameter
    $search = $request->getQuery('search', '');

    // Get courses with pagination
    $result = Admin::getCourses($limit, $offset, $search);

    if ($result === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve courses']);
    }

    // Get total count for pagination metadata
    $totalCourses = Admin::getTotalCourses($search);

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

  public function editCourse(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429); // Too Many Requests
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get course_code from query string
    $courseCode = $_GET['course_code'] ?? null;
    if (empty($courseCode)) {
      http_response_code(400);
      return json_encode(['error' => 'Course code is required']);
    }

    // Find course by code
    $course = Admin::findCourseByCode($courseCode);
    if (!$course) {
      http_response_code(404);
      return json_encode(['error' => 'Course not found']);
    }

    // Get request body
    $data = $request->body();

    // Normalize values
    if (isset($data['course_code'])) {
      $data['course_code'] = strtoupper(trim($data['course_code']));
    }
    if (isset($data['course_name'])) {
      $data['course_name'] = ucwords(strtolower(trim($data['course_name'])));
    }

    // Validate input
    if (empty($data['course_code']) || empty($data['course_name'])) {
      http_response_code(400);
      return json_encode(['error' => 'Course code and course name are required']);
    }

    // Update course
    $updated = Admin::updateCourse($course['course_id'], $data);

    if ($updated) {
      return json_encode([
        'success' => true,
        'message' => 'Course updated successfully',
        'course_id' => $course['course_id']
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to update course']);
    }
  }

  public function deleteCourse(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429); // Too Many Requests
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Check query string
    $courseCode = $_GET['course_code'] ?? null;
    $deleteAll  = $_GET['delete_all'] ?? null;

    // If delete_all flag is passed → delete all courses
    if ($deleteAll === 'true') {
      $deletedCount = Admin::deleteAllCourses();

      if ($deletedCount > 0) {
        return json_encode([
          'success' => true,
          'message' => "Deleted {$deletedCount} courses successfully"
        ]);
      } else {
        http_response_code(404);
        return json_encode(['error' => 'No courses found to delete']);
      }
    }

    // Otherwise → delete by course code
    if (empty($courseCode)) {
      http_response_code(400);
      return json_encode(['error' => 'Course code is required for deletion']);
    }

    $course = Admin::findCourseByCode($courseCode);
    if (!$course) {
      http_response_code(404);
      return json_encode(['error' => 'Course not found']);
    }

    $deleted = Admin::deleteCourseById($course['course_id']);
    if ($deleted) {
      return json_encode([
        'success' => true,
        'message' => 'Course deleted successfully',
        'course_id' => $course['course_id']
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to delete course']);
    }
  }

  // Department
  public function createDepartment(Request $request) {
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

    // Validate API key + CSRF
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Request body
    $data = $request->body();

    // Normalize
    if (isset($data['department_name'])) {
      $data['department_name'] = ucwords(strtolower(trim($data['department_name'])));
    }

    // Validate input
    if (empty($data['department_name'])) {
      http_response_code(400);
      return json_encode(['error' => 'Department name is required']);
    }

    // Check if department name already exists
    $existing = Admin::findDepartmentByName($data['department_name']);
    if ($existing) {
      http_response_code(409); // Conflict
      return json_encode(['error' => 'Department name already exists']);
    }

    // Generate department_id
    $data['department_id'] = bin2hex(random_bytes(16));

    // Insert department
    if (Admin::createDepartment($data)) {
      http_response_code(201);
      return json_encode([
        'success'        => true,
        'message'        => 'Department created successfully',
        'department_id'  => $data['department_id']
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to create department']);
    }
  }

  public function getDepartments(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429); // Too Many Requests
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Pagination parameters
    $page = max(1, intval($request->getQuery('page', 1)));
    $limit = max(1, min(100, intval($request->getQuery('limit', 10))));
    $offset = ($page - 1) * $limit;

    // Optional search
    $search = $request->getQuery('search', '');

    // Fetch departments
    $result = Admin::getDepartments($limit, $offset, $search);

    if ($result === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve departments']);
    }

    // Get total count for pagination
    $totalDepartments = Admin::getTotalDepartments($search);
    if ($totalDepartments === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve department count']);
    }

    $totalPages = ceil($totalDepartments / $limit);

    return json_encode([
      'success' => true,
      'data' => $result,
      'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_items' => $totalDepartments,
        'total_pages' => $totalPages,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ]
    ]);
  }

  public function editDepartment(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429); // Too Many Requests
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get department_id from query string
    $departmentId = $_GET['department_id'] ?? null;
    if (empty($departmentId)) {
      http_response_code(400);
      return json_encode(['error' => 'Department ID is required']);
    }

    // Find department by ID
    $department = Admin::findDepartmentById($departmentId);
    if (!$department) {
      http_response_code(404);
      return json_encode(['error' => 'Department not found']);
    }

    // Get request body
    $data = $request->body();

    // Normalize values
    if (isset($data['department_name'])) {
      $data['department_name'] = ucwords(strtolower(trim($data['department_name'])));
    }

    // Validate input
    if (empty($data['department_name'])) {
      http_response_code(400);
      return json_encode(['error' => 'Department Name are required']);
    }

    // Update department
    $updated = Admin::updateDepartment($departmentId, $data);

    if ($updated) {
      return json_encode([
        'success' => true,
        'message' => 'Department updated successfully',
        'department_id' => $departmentId
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to update department']);
    }
  }

  public function deleteDepartment(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429);
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Check query string
    $departmentId = $_GET['department_id'] ?? null;
    $deleteAll    = $_GET['delete_all'] ?? null;

    // If delete_all flag is passed → delete all departments
    if ($deleteAll === 'true') {
      $deletedCount = Admin::deleteAllDepartments();

      if ($deletedCount > 0) {
        return json_encode([
          'success' => true,
          'message' => "Deleted {$deletedCount} departments successfully"
        ]);
      } else {
        http_response_code(404);
        return json_encode(['error' => 'No departments found to delete']);
      }
    }

    // Otherwise → delete by department_id
    if (empty($departmentId)) {
      http_response_code(400);
      return json_encode(['error' => 'Department ID is required for deletion']);
    }

    $department = Admin::findDepartmentById($departmentId);
    if (!$department) {
      http_response_code(404);
      return json_encode(['error' => 'Department not found']);
    }

    $deleted = Admin::deleteDepartmentById($department['department_id']);
    if ($deleted) {
      return json_encode([
        'success' => true,
        'message' => 'Department deleted successfully',
        'department_id' => $department['department_id']
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to delete department']);
    }
  }

  // Scholarship Forms
  public function createScholarshipForm(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429); // Too Many Requests
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);

    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Check if file was uploaded
    if (!isset($_FILES['scholarship_form']) || $_FILES['scholarship_form']['error'] !== UPLOAD_ERR_OK) {
      http_response_code(400);
      return json_encode(['error' => 'Scholarship form file is required']);
    }

    $file = $_FILES['scholarship_form'];
    
    // Get form name from POST data
    $formName = trim($_POST['scholarship_form_name'] ?? '');
    if (empty($formName)) {
      http_response_code(400);
      return json_encode(['error' => 'Scholarship form name is required']);
    }

    // Normalize form name
    $formName = ucwords(strtolower(trim($formName)));

    // Check if scholarship form name already exists
    if (Admin::findScholarshipFormByName($formName)) {
      http_response_code(409);
      return json_encode(['error' => 'Scholarship form name already exists']);
    }

    // Handle file upload
    $uploadResult = $this->handleScholarshipFormUpload($file);
    
    if (!$uploadResult['success']) {
      http_response_code(400);
      return json_encode(['error' => $uploadResult['error']]);
    }

    // Generate scholarship form ID
    $scholarshipFormId = bin2hex(random_bytes(16));

    // Create scholarship form data
    $formData = [
      'scholarship_form_id' => $scholarshipFormId,
      'scholarship_form_name' => $formName,
      'scholarship_form' => $uploadResult['filename']
    ];

    // Create scholarship form record
    if (Admin::createScholarshipForm($formData)) {
      http_response_code(201);
      return json_encode([
        'success' => true, 
        'message' => 'Scholarship form created successfully',
        'scholarship_form_id' => $scholarshipFormId,
        'scholarship_form_name' => $formName,
        'file_path' => $uploadResult['filename']
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to create scholarship form record']);
    }
  }

  public function getScholarshipForms(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429);
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Pagination parameters
    $page = max(1, intval($request->getQuery('page', 1)));
    $limit = max(1, min(100, intval($request->getQuery('limit', 10))));
    $offset = ($page - 1) * $limit;

    // Optional search
    $search = $request->getQuery('search', '');

    // Fetch scholarship forms
    $result = Admin::getScholarshipForms($limit, $offset, $search);

    if ($result === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve scholarship forms']);
    }

    // Get total count for pagination
    $totalForms = Admin::getTotalScholarshipForms($search);
    if ($totalForms === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve scholarship forms count']);
    }

    $totalPages = ceil($totalForms / $limit);

    return json_encode([
      'success' => true,
      'data' => $result,
      'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_items' => $totalForms,
        'total_pages' => $totalPages,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ]
    ]);
  }

  public function editScholarshipForm(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429); // Too Many Requests
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);

    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get scholarship form ID from query parameter
    $scholarshipFormId = $request->getQuery('scholarship_form_id');
    if (empty($scholarshipFormId)) {
      http_response_code(400);
      return json_encode(['error' => 'Scholarship form ID is required']);
    }

    // Check if scholarship form exists
    $existingForm = Admin::findScholarshipFormById($scholarshipFormId);
    if (!$existingForm) {
      http_response_code(404);
      return json_encode(['error' => 'Scholarship form not found']);
    }

    $updateData = [];
    $newFilename = null;

    // Handle PUT method with form data - manual parsing required
    $formName = '';
    $hasFile = false;

    // Check if it's a PUT request with form data
    if ($_SERVER['REQUEST_METHOD'] === 'PUT' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
      // Parse multipart form data manually for PUT requests
      $putData = $this->parsePutFormData();
      $formName = trim($putData['scholarship_form_name'] ?? '');
      $hasFile = isset($putData['scholarship_form']);
    } else {
      // For POST requests, use normal $_POST and $_FILES
      $formName = trim($_POST['scholarship_form_name'] ?? '');
      $hasFile = isset($_FILES['scholarship_form']) && $_FILES['scholarship_form']['error'] === UPLOAD_ERR_OK;
    }

    // Handle form name update if provided
    if (!empty($formName)) {
      $formName = ucwords(strtolower(trim($formName)));
      
      // Check if new name already exists (excluding current form)
      $existingWithName = Admin::findScholarshipFormByName($formName);
      if ($existingWithName && $existingWithName['scholarship_form_id'] !== $scholarshipFormId) {
        http_response_code(409);
        return json_encode(['error' => 'Scholarship form name already exists']);
      }
      
      $updateData['scholarship_form_name'] = $formName;
    }

    // Handle file upload if provided
    if ($hasFile) {
      if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // For PUT requests, use the parsed file data
        $fileData = $putData['scholarship_form'];
        $uploadResult = $this->handlePutFileUpload($fileData);
      } else {
        // For POST requests, use normal $_FILES
        $file = $_FILES['scholarship_form'];
        $uploadResult = $this->handleScholarshipFormUpload($file);
      }
      
      if (!$uploadResult['success']) {
        http_response_code(400);
        return json_encode(['error' => $uploadResult['error']]);
      }
      
      $newFilename = $uploadResult['filename'];
      $updateData['scholarship_form'] = $newFilename;
    }

    // Check if there's anything to update
    if (empty($updateData)) {
      http_response_code(400);
      return json_encode(['error' => 'No data provided for update. Provide either scholarship_form_name or scholarship_form file.']);
    }

    // Update scholarship form record
    if (Admin::updateScholarshipForm($scholarshipFormId, $updateData)) {
      // If a new file was uploaded, delete the old file
      if ($newFilename && !empty($existingForm['scholarship_form'])) {
        $this->deleteScholarshipFormFile($existingForm['scholarship_form']);
      }
      
      http_response_code(200);
      return json_encode([
        'success' => true, 
        'message' => 'Scholarship form updated successfully',
        'scholarship_form_id' => $scholarshipFormId,
        'updates' => $updateData
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to update scholarship form']);
    }
  }

  public function deleteScholarshipForm(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429);
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Check query string
    $scholarshipFormId = $request->getQuery('scholarship_form_id');
    $deleteAll = $request->getQuery('delete_all');

    // If delete_all flag is passed → delete all scholarship forms
    if ($deleteAll === 'true') {
      // First get all forms to delete their files
      $allForms = Admin::getAllScholarshipForms();
      
      if (!$allForms) {
        http_response_code(404);
        return json_encode(['error' => 'No scholarship forms found to delete']);
      }

      $deletedCount = 0;
      $failedCount = 0;

      foreach ($allForms as $form) {
        // Delete the physical file first
        if (!empty($form['scholarship_form'])) {
          $this->deleteScholarshipFormFile($form['scholarship_form']);
        }
        
        // Delete the database record
        $deleted = Admin::deleteScholarshipFormById($form['scholarship_form_id']);
        if ($deleted) {
          $deletedCount++;
        } else {
          $failedCount++;
        }
      }

      if ($deletedCount > 0) {
        return json_encode([
          'success' => true,
          'message' => "Deleted {$deletedCount} scholarship forms successfully" . 
                      ($failedCount > 0 ? ", {$failedCount} failed" : "")
        ]);
      } else {
        http_response_code(500);
        return json_encode(['error' => 'Failed to delete scholarship forms']);
      }
    }

    // Otherwise → delete by scholarship_form_id
    if (empty($scholarshipFormId)) {
      http_response_code(400);
      return json_encode(['error' => 'Scholarship form ID is required for deletion']);
    }

    $scholarshipForm = Admin::findScholarshipFormById($scholarshipFormId);
    if (!$scholarshipForm) {
      http_response_code(404);
      return json_encode(['error' => 'Scholarship form not found']);
    }

    // Delete the physical file first
    if (!empty($scholarshipForm['scholarship_form'])) {
      $this->deleteScholarshipFormFile($scholarshipForm['scholarship_form']);
    }

    // Delete the database record
    $deleted = Admin::deleteScholarshipFormById($scholarshipFormId);
    if ($deleted) {
      return json_encode([
        'success' => true,
        'message' => 'Scholarship form deleted successfully',
        'scholarship_form_id' => $scholarshipFormId
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to delete scholarship form']);
    }
  }

  private function deleteScholarshipFormFile($filename) {
    $uploadDir = __DIR__ . '/../uploads/scholarship_forms/';
    $filePath = $uploadDir . $filename;
    
    if (file_exists($filePath)) {
      unlink($filePath);
      return true;
    }
    return false;
  }

  private function parsePutFormData() {
    $input = fopen('php://input', 'r');
    $data = [];
    $boundary = '';

    // Get boundary from content type
    if (preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches)) {
      $boundary = $matches[1];
    }

    if (empty($boundary)) {
      return $data;
    }

    $currentKey = null;
    $currentValue = '';

    while (!feof($input)) {
      $line = fgets($input);
      if ($line === false) {
        break;
      }

      // Remove CRLF
      $line = rtrim($line, "\r\n");

      if ($line === "--$boundary--") {
        break;
      }

      if (preg_match('/^--$boundary$/', $line)) {
        // New part
        if ($currentKey !== null) {
          $data[$currentKey] = trim($currentValue);
          $currentValue = '';
        }
        $currentKey = null;
        continue;
      }

      if (preg_match('/^Content-Disposition: form-data; name="([^"]+)"/', $line, $matches)) {
        $currentKey = $matches[1];
      } else if ($currentKey !== null) {
        $currentValue .= $line . "\n";
      }
    }

    fclose($input);
    return $data;
  }

  private function handleScholarshipFormUpload($file) {
    $subfolder = 'scholarship_forms/';
    $allowedExtensions = ['pdf', 'docx', 'doc'];
    $allowedMimeTypes = [
      'application/pdf', 
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/msword'
    ];

    $uploadDir = __DIR__ . '/../uploads/' . $subfolder;
    
    // Create uploads directory and subfolder if they don't exist
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }

    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
      return ['success' => false, 'error' => 'File upload failed'];
    }

    // Set file size limit (10MB for scholarship forms)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
      return ['success' => false, 'error' => 'File size too large. Maximum size: 10MB'];
    }

    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileMimeType = mime_content_type($file['tmp_name']);

    if (!in_array($fileExtension, $allowedExtensions)) {
      $allowedTypes = implode(', ', $allowedExtensions);
      return ['success' => false, 'error' => "Invalid file type. Allowed: {$allowedTypes}"];
    }

    // Generate unique filename while preserving original extension
    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $uniqueName = $originalName . '_' . uniqid() . '_' . time() . '.' . $fileExtension;
    $targetPath = $uploadDir . $uniqueName;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
      // Return the relative path including subfolder
      return ['success' => true, 'filename' => $uniqueName];
    } else {
      return ['success' => false, 'error' => 'Failed to save file'];
    }
  }

  private function handlePutFileUpload($fileData) {
    $subfolder = 'scholarship_forms/';
    $allowedExtensions = ['pdf', 'docx', 'doc'];
    $allowedMimeTypes = [
      'application/pdf', 
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/msword'
    ];

    $uploadDir = __DIR__ . '/../uploads/' . $subfolder;
    
    // Create uploads directory and subfolder if they don't exist
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $fileExtension = 'pdf'; // Default to pdf, you might want to detect this better
    $uniqueName = 'form_' . uniqid() . '_' . time() . '.' . $fileExtension;
    $targetPath = $uploadDir . $uniqueName;

    // Save the file data
    if (file_put_contents($targetPath, $fileData)) {
      return ['success' => true, 'filename' => $uniqueName];
    } else {
      return ['success' => false, 'error' => 'Failed to save file'];
    }
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

    // Find admin by API key and CSRF token
    $admin = Admin::findByApiKey($apiKey, $csrfToken);

    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Fetch department name if department_id exists
    $departmentName = '';
    if (!empty($admin['department'])) {
      $department = Admin::findDepartmentById($admin['department']);
      $departmentName = $department['department_name'] ?? '';
    }

    // Only keep the fields you want
    $profile = [
      'first_name' => $admin['first_name'] ?? '',
      'last_name'  => $admin['last_name'] ?? '',
      'department' => $departmentName,
      'email'      => $admin['email'] ?? ''
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

    // Find admin by API key and CSRF token
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get request body
    $data = $request->body();

    // Only allow updating first_name, last_name, department_id
    $updateData = [];
    if (isset($data['first_name'])) {
      $updateData['first_name'] = ucwords(strtolower(trim($data['first_name'])));
    }
    if (isset($data['last_name'])) {
      $updateData['last_name'] = ucwords(strtolower(trim($data['last_name'])));
    }
    if (isset($data['department'])) {
      $departmentId = trim($data['department']);

      // Check if department exists
      $department = Admin::findDepartmentById($departmentId);
      if (!$department) {
        http_response_code(404);
        return json_encode(['error' => 'Department not found']);
      }

      $updateData['department'] = $departmentId;
    }

    if (empty($updateData)) {
      http_response_code(400);
      return json_encode(['error' => 'Nothing to update']);
    }

    // Update using API key and CSRF token
    $updated = Admin::updateProfileByApiKey($apiKey, $csrfToken, $updateData);

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

  // Scholarships
  public function createScholarship(Request $request) {
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

    // Validate API key + CSRF
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Request body
    $data = $request->body();

    // Validate required fields
    if (empty($data['scholarship_title'])) {
      http_response_code(400);
      return json_encode(['error' => 'Scholarship title is required']);
    }

    if (empty($data['description'])) {
      http_response_code(400);
      return json_encode(['error' => 'Description is required']);
    }

    if (empty($data['start_date'])) {
      http_response_code(400);
      return json_encode(['error' => 'Start date is required']);
    }

    if (empty($data['end_date'])) {
      http_response_code(400);
      return json_encode(['error' => 'End date is required']);
    }

    if (empty($data['status']) || !in_array($data['status'], ['active', 'archive'])) {
      http_response_code(400);
      return json_encode(['error' => 'Valid status is required (active or archive)']);
    }

    // Normalize data
    $data['scholarship_title'] = ucwords(strtolower(trim($data['scholarship_title'])));
    $data['description'] = trim($data['description']);
    
    if (isset($data['amount'])) {
      $data['amount'] = floatval($data['amount']);
    }

    // Check if scholarship title already exists
    $existing = Admin::findScholarshipByTitle($data['scholarship_title']);
    if ($existing) {
      http_response_code(409);
      return json_encode(['error' => 'Scholarship title already exists']);
    }

    // Validate dates
    $startDate = strtotime($data['start_date']);
    $endDate = strtotime($data['end_date']);
    
    if ($startDate === false || $endDate === false) {
      http_response_code(400);
      return json_encode(['error' => 'Invalid date format']);
    }

    if ($endDate <= $startDate) {
      http_response_code(400);
      return json_encode(['error' => 'End date must be after start date']);
    }

    // Validate course codes if provided
    if (!empty($data['course_codes'])) {
      if (!is_array($data['course_codes'])) {
        http_response_code(400);
        return json_encode(['error' => 'Course codes must be an array']);
      }

      // Handle "all" option
      if (in_array('all', $data['course_codes'])) {
        // Get all available courses
        $allCourses = Admin::getAllCourses();
        if ($allCourses === false || empty($allCourses)) {
          http_response_code(404);
          return json_encode(['error' => 'No courses found in the system']);
        }
          
        // Replace with all course codes
        $data['course_codes'] = array_column($allCourses, 'course_code');
      } else {
        // Validate individual course codes
        foreach ($data['course_codes'] as $courseCode) {
          $course = Admin::findCourseByCode($courseCode);
          if (!$course) {
            http_response_code(404);
            return json_encode(['error' => "Course code '{$courseCode}' not found"]);
          }
        }
      }
    }

    // Validate scholarship form IDs if provided
    if (!empty($data['scholarship_form_ids'])) {
      if (!is_array($data['scholarship_form_ids'])) {
        http_response_code(400);
        return json_encode(['error' => 'Scholarship form IDs must be an array']);
      }

      foreach ($data['scholarship_form_ids'] as $formId) {
        $form = Admin::findScholarshipFormById($formId);
        if (!$form) {
          http_response_code(404);
          return json_encode(['error' => "Scholarship form ID {$formId} not found"]);
        }
      }
    }

    // Generate scholarship_id
    $data['scholarship_id'] = bin2hex(random_bytes(16));

    // Create scholarship
    if (Admin::createScholarship($data)) {
      // Handle course associations if provided
      if (!empty($data['course_codes'])) {
        foreach ($data['course_codes'] as $courseCode) {
          $course = Admin::findCourseByCode($courseCode);
          if ($course) {
            Admin::createScholarshipCourse($data['scholarship_id'], $course['course_id']);
          }
        }
      }

      // Handle form associations if provided
      if (!empty($data['scholarship_form_ids'])) {
        foreach ($data['scholarship_form_ids'] as $formId) {
          Admin::createScholarshipFormAssociation($data['scholarship_id'], $formId);
        }
      }

      http_response_code(201);
      return json_encode([
        'success' => true,
        'message' => 'Scholarship created successfully',
        'scholarship_id' => $data['scholarship_id']
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to create scholarship']);
    }
  }

  public function getScholarship(Request $request) {
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

    // Validate API key + CSRF
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Pagination params
    $page  = max(1, intval($request->getQuery('page', 1)));
    $limit = max(1, min(100, intval($request->getQuery('limit', 10))));
    $offset = ($page - 1) * $limit;

    // Optional search by title
    $search = trim($request->getQuery('search', ''));

    // Fetch scholarships
    $scholarships = Admin::getScholarships($limit, $offset, $search);
    if ($scholarships === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve scholarships']);
    }

    // Get total count
    $totalScholarships = Admin::getTotalScholarships($search);
    if ($totalScholarships === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve scholarships count']);
    }

    // Attach related data (course codes + forms)
    foreach ($scholarships as &$scholarship) {
      $scholarship['course_codes'] = Admin::getScholarshipCourseCodes($scholarship['scholarship_id']) ?? [];
      $scholarship['scholarship_forms'] = Admin::getScholarshipFormsIds($scholarship['scholarship_id']) ?? [];
    }

    $totalPages = ceil($totalScholarships / $limit);

    return json_encode([
      'success' => true,
      'data' => $scholarships,
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

  public function editScholarship(Request $request) {
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

    // Validate API key + CSRF
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Request body
    $data = $request->body();

    // Validate scholarship_id
    $scholarshipId = $request->getQuery('scholarship_id');
    if (empty($scholarshipId)) {
      http_response_code(400);
      return json_encode(['error' => 'Scholarship ID is required']);
    }

    // Force scholarship_id into $data
    $data['scholarship_id'] = $scholarshipId;

    // Find existing scholarship
    $scholarship = Admin::findScholarshipById($scholarshipId);
    if (!$scholarship) {
      http_response_code(404);
      return json_encode(['error' => 'Scholarship not found']);
    }

    // Normalize fields if provided
    if (!empty($data['scholarship_title'])) {
      $data['scholarship_title'] = ucwords(strtolower(trim($data['scholarship_title'])));
    }
    if (!empty($data['description'])) {
      $data['description'] = trim($data['description']);
    }
    if (isset($data['amount'])) {
      $data['amount'] = floatval($data['amount']);
    }
    if (!empty($data['status']) && !in_array($data['status'], ['active', 'archive'])) {
      http_response_code(400);
      return json_encode(['error' => 'Invalid status (must be active or archive)']);
    }

    // Validate dates if provided
    if (!empty($data['course_codes'])) {
      if (!is_array($data['course_codes'])) {
        http_response_code(400);
        return json_encode(['error' => 'Course codes must be an array']);
      }

      // Handle "all" option
      if (in_array('all', $data['course_codes'])) {
        // Get all available courses
        $allCourses = Admin::getAllCourses();
        if ($allCourses === false || empty($allCourses)) {
          http_response_code(404);
          return json_encode(['error' => 'No courses found in the system']);
        }
          
        // Replace with all course codes
        $data['course_codes'] = array_column($allCourses, 'course_code');
      } else {
        // Validate individual course codes
        foreach ($data['course_codes'] as $courseCode) {
          $course = Admin::findCourseByCode($courseCode);
          if (!$course) {
            http_response_code(404);
            return json_encode(['error' => "Course code '{$courseCode}' not found"]);
          }
        }
      }
    }

    // Validate course codes if provided
    if (isset($data['course_codes'])) {
      if (!is_array($data['course_codes'])) {
        http_response_code(400);
        return json_encode(['error' => 'Course codes must be an array']);
      }

      foreach ($data['course_codes'] as $courseCode) {
        $course = Admin::findCourseByCode($courseCode);
        if (!$course) {
          http_response_code(404);
          return json_encode(['error' => "Course code '{$courseCode}' not found"]);
        }
      }
    }

    // Validate scholarship form IDs if provided
    if (isset($data['scholarship_form_ids'])) {
      if (!is_array($data['scholarship_form_ids'])) {
        http_response_code(400);
        return json_encode(['error' => 'Scholarship form IDs must be an array']);
      }

      foreach ($data['scholarship_form_ids'] as $formId) {
        $form = Admin::findScholarshipFormById($formId);
        if (!$form) {
          http_response_code(404);
          return json_encode(['error' => "Scholarship form ID {$formId} not found"]);
        }
      }
    }

    // Update scholarship
    if (!Admin::updateScholarship($data)) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to update scholarship']);
    }

    // Refresh course associations if provided
    if (isset($data['course_codes'])) {
      Admin::deleteScholarshipCourses($scholarshipId); // clear old
      foreach ($data['course_codes'] as $courseCode) {
        $course = Admin::findCourseByCode($courseCode);
        if ($course) {
          Admin::createScholarshipCourse($scholarshipId, $course['course_id']);
        }
      }
    }

    // Refresh form associations if provided
    if (isset($data['scholarship_form_ids'])) {
      Admin::deleteScholarshipFormAssociations($scholarshipId); // clear old
      foreach ($data['scholarship_form_ids'] as $formId) {
        $form = Admin::findScholarshipFormById($formId);
        if ($form) {
          Admin::createScholarshipFormAssociation($scholarshipId, $formId);
        }
      }
    }

    http_response_code(200);
    return json_encode([
      'success' => true,
      'message' => 'Scholarship updated successfully'
    ]);
  }

  public function deleteScholarship(Request $request) {
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

    // Validate API key + CSRF
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get scholarship_id from query parameters
    $scholarshipId = $request->getQuery('scholarship_id');
    if (empty($scholarshipId)) {
      http_response_code(400);
      return json_encode(['error' => 'Scholarship ID is required']);
    }

    // Check if scholarship exists
    $scholarship = Admin::findScholarshipById($scholarshipId);
    if (!$scholarship) {
      http_response_code(404);
      return json_encode(['error' => 'Scholarship not found']);
    }

    try {
      // Delete associated records first (to maintain referential integrity)
      
      // 1. Delete scholarship course associations
      if (!Admin::deleteScholarshipCourses($scholarshipId)) {
        throw new Exception('Failed to delete scholarship course associations');
      }

      // 2. Delete scholarship form associations
      if (!Admin::deleteScholarshipFormAssociations($scholarshipId)) {
        throw new Exception('Failed to delete scholarship form associations');
      }

      // 3. Finally delete the scholarship itself
      if (!Admin::deleteScholarship($scholarshipId)) {
        throw new Exception('Failed to delete scholarship');
      }

      http_response_code(200);
      return json_encode([
        'success' => true,
        'message' => 'Scholarship deleted successfully'
      ]);

    } catch (Exception $e) {
      http_response_code(500);
      return json_encode([
        'error' => 'Failed to delete scholarship: ' . $e->getMessage()
      ]);
    }
  }

  // Announcements
  public function createAnnouncement(Request $request) {
    $apiKey = $this->getApiKey($request);

    if (empty($apiKey)) {
      http_response_code(401);
      return json_encode(['error' => 'API key is required']);
    }

    // Check rate limit
    if (!RateLimiter::check($apiKey)) {
      http_response_code(429); // Too Many Requests
      return json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    }

    // Verify CSRF Token
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (empty($csrfToken)) {
      http_response_code(403);
      return json_encode(['error' => 'CSRF token is required']);
    }

    // Verify admin authentication using API key
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get request body
    $data = $request->body();

    // Normalize values
    $data['announcement_title'] = ucwords(strtolower(trim($data['announcement_title'] ?? '')));
    $data['announcement_description'] = trim($data['announcement_description'] ?? '');

    // Validate required fields
    if (empty($data['announcement_title']) || empty($data['announcement_description'])) {
      http_response_code(400);
      return json_encode(['error' => 'Announcement title and description are required']);
    }

    // Generate announcement ID
    $data['announcement_id'] = bin2hex(random_bytes(16));
    $data['admin_id'] = $admin['user_id'];
    $data['created_at'] = date('Y-m-d H:i:s');
    $data['updated_at'] = date('Y-m-d H:i:s');

    // Create announcement
    if (Admin::createAnnouncement($data)) {
      http_response_code(201);
      return json_encode([
        'success' => true,
        'message' => 'Announcement created successfully',
        'announcement_id' => $data['announcement_id']
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to create announcement']);
    }
  }

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

    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    $page = max(1, intval($request->getQuery('page', 1)));
    $limit = max(1, min(100, intval($request->getQuery('limit', 10))));
    $offset = ($page - 1) * $limit;

    $search = $request->getQuery('search', '');

    $result = Admin::getAnnouncements($limit, $offset, $search);
    if ($result === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve announcements']);
    }

    $total = Admin::getTotalAnnouncements($search);
    $totalPages = ceil($total / $limit);

    return json_encode([
      'success' => true,
      'data' => $result,
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

  public function editAnnouncement(Request $request) {
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

    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    $data = $request->body();
    $data['announcement_title'] = ucwords(strtolower(trim($data['announcement_title'] ?? '')));
    $data['announcement_description'] = trim($data['announcement_description'] ?? '');

    $announcementId = $data['announcement_id'] ?? null;
    if (empty($announcementId)) {
      http_response_code(400);
      return json_encode(['error' => 'Announcement ID is required']);
    }

    $announcement = Admin::findAnnouncementById($announcementId);
    if (!$announcement) {
      http_response_code(404);
      return json_encode(['error' => 'Announcement not found']);
    }

    if (empty($data['announcement_title']) || empty($data['announcement_description'])) {
      http_response_code(400);
      return json_encode(['error' => 'Title and description are required']);
    }

    $updated = Admin::updateAnnouncement($announcementId, $data);

    if ($updated) {
      return json_encode([
        'success' => true,
        'message' => 'Announcement updated successfully',
        'announcement_id' => $announcementId
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to update announcement']);
    }
  }

  public function deleteAnnouncement(Request $request) {
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

    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    $announcementId = $_GET['announcement_id'] ?? null;
    $deleteAll = $_GET['delete_all'] ?? null;

    if ($deleteAll === 'true') {
      $deletedCount = Admin::deleteAllAnnouncements();
      if ($deletedCount > 0) {
        return json_encode([
          'success' => true,
          'message' => "Deleted {$deletedCount} announcements successfully"
        ]);
      } else {
        http_response_code(404);
        return json_encode(['error' => 'No announcements found to delete']);
      }
    }

    if (empty($announcementId)) {
      http_response_code(400);
      return json_encode(['error' => 'Announcement ID is required for deletion']);
    }

    $announcement = Admin::findAnnouncementById($announcementId);
    if (!$announcement) {
      http_response_code(404);
      return json_encode(['error' => 'Announcement not found']);
    }

    $deleted = Admin::deleteAnnouncementById($announcementId);
    if ($deleted) {
      return json_encode([
        'success' => true,
        'message' => 'Announcement deleted successfully',
        'announcement_id' => $announcementId
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to delete announcement']);
    }
  }

  // Accounts
  public function getAdmins(Request $request) {
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

    // Validate API key + CSRF
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Pagination params
    $page  = max(1, intval($request->getQuery('page', 1)));
    $limit = max(1, min(100, intval($request->getQuery('limit', 10))));
    $offset = ($page - 1) * $limit;

    // Optional search by name or email
    $search = trim($request->getQuery('search', ''));

    // Status filter (all, pending, approved, declined)
    $status = trim($request->getQuery('status', 'all'));
    $validStatuses = ['all', 'pending', 'approved', 'declined'];
    if (!in_array($status, $validStatuses)) {
      $status = 'all';
    }

    // Fetch admins (using renamed model method)
    $admins = Admin::getAdminsList($limit, $offset, $search, $status);
    if ($admins === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve admin accounts']);
    }

    // Get total count (using renamed model method)
    $totalAdmins = Admin::getTotalAdminsCount($search, $status);
    if ($totalAdmins === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve admin accounts count']);
    }

    // Remove sensitive data from response
    foreach ($admins as &$admin) {
      unset($admin['password']);
      unset($admin['api_key']);
      unset($admin['csrf_token']);
    }

    $totalPages = ceil($totalAdmins / $limit);

    return json_encode([
      'success' => true,
      'data' => $admins,
      'filters' => [
        'search' => $search,
        'status' => $status
      ],
      'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_items' => $totalAdmins,
        'total_pages' => $totalPages,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ]
    ]);
  }

  public function updateAdminStatus(Request $request) {
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

    // Validate API key + CSRF
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Request body
    $data = $request->body();

    // Validate required fields
    if (empty($data['user_id'])) {
      http_response_code(400);
      return json_encode(['error' => 'User ID is required']);
    }

    if (empty($data['status']) || !in_array($data['status'], ['pending', 'approved', 'declined'])) {
      http_response_code(400);
      return json_encode(['error' => 'Valid status is required (pending, approved, declined)']);
    }

    $userId = $data['user_id'];
    $newStatus = $data['status'];

    // Check if admin exists
    $targetAdmin = Admin::findAdminById($userId);
    if (!$targetAdmin) {
      http_response_code(404);
      return json_encode(['error' => 'Admin account not found']);
    }

    // Prevent self-status change
    if ($targetAdmin['user_id'] === $admin['user_id']) {
      http_response_code(400);
      return json_encode(['error' => 'Cannot change your own status']);
    }

    // Update admin status
    if (Admin::updateAdminStatus($userId, $newStatus)) {
      http_response_code(200);
      return json_encode([
        'success' => true,
        'message' => 'Admin status updated successfully',
        'data' => [
          'user_id' => $userId,
          'status' => $newStatus
        ]
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to update admin status']);
    }
  }

  public function deleteAdmin(Request $request) {
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

    // Validate API key + CSRF
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get user_id from query parameters
    $userId = $request->getQuery('user_id');
    if (empty($userId)) {
      http_response_code(400);
      return json_encode(['error' => 'User ID is required']);
    }

    // Check if admin exists
    $targetAdmin = Admin::findAdminById($userId);
    if (!$targetAdmin) {
      http_response_code(404);
      return json_encode(['error' => 'Admin account not found']);
    }

    // Prevent self-deletion
    if ($targetAdmin['user_id'] === $admin['user_id']) {
      http_response_code(400);
      return json_encode(['error' => 'Cannot delete your own account']);
    }

    // Delete admin account
    if (Admin::deleteAdmin($userId)) {
      http_response_code(200);
      return json_encode([
        'success' => true,
        'message' => 'Admin account deleted successfully',
        'data' => [
          'user_id' => $userId,
          'email' => $targetAdmin['email']
        ]
      ]);
    } else {
      http_response_code(500);
      return json_encode(['error' => 'Failed to delete admin account']);
    }
  }

  // Applications
  public function getApplications(Request $request) {
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

    // Validate API key + CSRF
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Pagination params
    $page  = max(1, intval($request->getQuery('page', 1)));
    $limit = max(1, min(100, intval($request->getQuery('limit', 10))));
    $offset = ($page - 1) * $limit;

    // Optional filters
    $search = trim($request->getQuery('search', '')); 
    $status = trim($request->getQuery('status', 'all')); 
    $validStatuses = ['all', 'pending', 'approved', 'declined'];
    if (!in_array($status, $validStatuses)) {
      $status = 'all';
    }

    // Fetch applications
    $applications = Admin::getAllApplications($limit, $offset, $search, $status);
    if ($applications === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve applications']);
    }

    // Attach uploaded forms for each application
    foreach ($applications as &$app) {
      $forms = Admin::getApplicationForms($app['application_id']); 
      $app['forms'] = $forms ?: [];
    }

    // Get total count
    $totalApplications = Admin::getTotalApplicationsCount($search, $status);
    if ($totalApplications === false) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to retrieve applications count']);
    }

    $totalPages = ceil($totalApplications / $limit);

    return json_encode([
      'success' => true,
      'data' => $applications,
      'filters' => [
        'search' => $search,
        'status' => $status
      ],
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

  public function updateApplication(Request $request) {
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

    // Validate API key + CSRF
    $admin = Admin::findByApiKey($apiKey, $csrfToken);
    if (!$admin) {
      http_response_code(401);
      return json_encode(['error' => 'Invalid API key or unauthorized access']);
    }

    // Get body data
    $data = $request->body();
    $applicationId = $data['application_id'] ?? null;
    $status = strtolower(trim($data['status'] ?? ''));

    // Validate input
    if (empty($applicationId) || empty($status)) {
      http_response_code(400);
      return json_encode(['error' => 'Application ID and status are required']);
    }

    $validStatuses = ['pending', 'approved', 'declined'];
    if (!in_array($status, $validStatuses)) {
      http_response_code(400);
      return json_encode(['error' => 'Invalid status value']);
    }

    // Check if application exists first
    $application = Admin::getApplicationById($applicationId);
    if (!$application) {
      http_response_code(404);
      return json_encode(['error' => 'Application not found']);
    }

    // Update application status
    $updated = Admin::updateApplicationStatus($applicationId, $status);
    if (!$updated) {
      http_response_code(500);
      return json_encode(['error' => 'Failed to update application status']);
    }

    // Fetch student details
    $student = Admin::getStudentByApplicationId($applicationId);
    if ($student && !empty($student['email'])) {
      $this->sendStatusEmail($student['email'], $student['first_name'], $status);
    }

    return json_encode([
      'success' => true,
      'message' => "Application status updated to {$status}"
    ]);
  }

  private function sendStatusEmail(string $toEmail, string $firstName, string $status): void {
    $apiKey = $_ENV['BREVO_API_KEY'];
    $senderName = $_ENV['BREVO_SENDER_NAME'];
    $senderEmail = $_ENV['BREVO_SENDER_EMAIL'];
    $url = "https://api.brevo.com/v3/smtp/email";

    $subject = "Your Scholarship Application Status Update";
    $content = "
      Hi {$firstName},<br><br>
      Your scholarship application status has been updated to: <b>{$status}</b>.<br><br>
      If you have any questions, please contact the scholarship office.<br><br>
      Regards,<br>
      Scholarship Team
    ";

    $data = [
      "sender" => ["name" => $senderName, "email" => $senderEmail],
      "to" => [["email" => $toEmail, "name" => $firstName]],
      "subject" => $subject,
      "htmlContent" => $content
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "accept: application/json",
      "api-key: {$apiKey}",
      "content-type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);
  }

  // Dashboard
  public function dashboardStats(Request $request) {
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

    // ✅ use getQuery instead of query()
    $year = intval($request->getQuery('year', date('Y')));

    try {
      $stats = Admin::getStats($year);

      return json_encode([
        'success' => true,
        'year' => (int) $year,
        'stats' => $stats
      ]);

    } catch (Exception $e) {
      http_response_code(500);
      return json_encode([
        'success' => false,
        'error' => 'Failed to fetch dashboard stats',
        'details' => $e->getMessage()
      ]);
    }
  } 
}
