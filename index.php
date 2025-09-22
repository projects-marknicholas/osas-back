<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\Request;

date_default_timezone_set('Asia/Manila');

// header("Access-Control-Allow-Origin: http://localhost:5173"); 
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/');
$dotenv->load();

$request = new Request();
$router = new Router($request);

// ====== AUTH START ====== //
$router->post('/login', [App\Controllers\AuthController::class, 'login']);
$router->post('/register', [App\Controllers\AuthController::class, 'register']);
$router->post('/forgot', [App\Controllers\AuthController::class, 'forgotPassword']);
$router->post('/reset', [App\Controllers\AuthController::class, 'resetPassword']);
$router->post('/callback', [App\Controllers\AuthController::class, 'adminAuth']);
// ====== AUTH END ====== //

// ====== ADMIN START ====== //
// Course
$router->post('/admin/course', [App\Controllers\AdminController::class, 'createCourse']);
$router->get('/admin/course', [App\Controllers\AdminController::class, 'getCourses']);
$router->put('/admin/course', [App\Controllers\AdminController::class, 'editCourse']);
$router->delete('/admin/course', [App\Controllers\AdminController::class, 'deleteCourse']);

// Department
$router->post('/admin/department', [App\Controllers\AdminController::class, 'createDepartment']);
$router->get('/admin/department', [App\Controllers\AdminController::class, 'getDepartments']);
$router->put('/admin/department', [App\Controllers\AdminController::class, 'editDepartment']);
$router->delete('/admin/department', [App\Controllers\AdminController::class, 'deleteDepartment']);

// Scholarship Form
$router->post('/admin/scholarship-form', [App\Controllers\AdminController::class, 'createScholarshipForm']);
$router->get('/admin/scholarship-form', [App\Controllers\AdminController::class, 'getScholarshipForms']);
$router->post('/admin/scholarship-forms', [App\Controllers\AdminController::class, 'editScholarshipForm']);
$router->delete('/admin/scholarship-form', [App\Controllers\AdminController::class, 'deleteScholarshipForm']);

// Profile
$router->get('/admin/profile', [App\Controllers\AdminController::class, 'getProfile']);
$router->put('/admin/profile', [App\Controllers\AdminController::class, 'updateProfile']);

// Scholarship
$router->post('/admin/scholarship', [App\Controllers\AdminController::class, 'createScholarship']);
$router->get('/admin/scholarship', [App\Controllers\AdminController::class, 'getScholarship']);
$router->put('/admin/scholarship', [App\Controllers\AdminController::class, 'editScholarship']);
$router->delete('/admin/scholarship', [App\Controllers\AdminController::class, 'deleteScholarship']);

// Applications
$router->get('/admin/applications', [App\Controllers\AdminController::class, 'getApplications']);
$router->put('/admin/applications', [App\Controllers\AdminController::class, 'updateApplication']);

// Announcements
$router->get('/admin/announcement', [App\Controllers\AdminController::class, 'getAnnouncements']);
$router->put('/admin/announcement', [App\Controllers\AdminController::class, 'editAnnouncement']);
$router->post('/admin/announcement', [App\Controllers\AdminController::class, 'createAnnouncement']);
$router->delete('/admin/announcement', [App\Controllers\AdminController::class, 'deleteAnnouncement']);

// Accounts
$router->get('/admin/accounts', [App\Controllers\AdminController::class, 'getAdmins']);
$router->put('/admin/accounts', [App\Controllers\AdminController::class, 'updateAdminStatus']);
$router->delete('/admin/accounts', [App\Controllers\AdminController::class, 'deleteAdmin']);

// Dashboard
$router->get('/admin/dashboard', [App\Controllers\AdminController::class, 'dashboardStats']);
// ====== ADMIN START ====== //

// ====== STUDENT START ====== //
// Courses
$router->get('/student/course', [App\Controllers\StudentController::class, 'getCourses']);

// Profile
$router->get('/student/profile', [App\Controllers\StudentController::class, 'getProfile']);
$router->put('/student/profile', [App\Controllers\StudentController::class, 'updateProfile']);

// Scholarship
$router->post('/student/apply', [App\Controllers\StudentController::class, 'applyScholarship']);
$router->get('/student/scholarship', [App\Controllers\StudentController::class, 'getScholarships']);

// Applications
$router->get('/student/applications', [App\Controllers\StudentController::class, 'getApplications']);

// Announcements
$router->get('/student/announcement', [App\Controllers\StudentController::class, 'getAnnouncements']);
// ====== STUDENT END ====== //

$router->resolve();
