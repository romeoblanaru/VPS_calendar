<?php
session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/logger.php';

require_once __DIR__ . '/../includes/credentials.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['organisation_user', 'admin_user'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    } else {
        header('Location: ../login.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['name_of_the_place', 'address', 'landmark', 'directions', 'user', 'password', 'organisation_id', 'we_handling', 'specialist_relevance'];

        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
                    exit;
                } else {
                    $_SESSION['alert'] = ['type' => 'danger', 'message' => "Field '$field' is required"];
                    header('Location: admin_workpoints.php');
                    exit;
                }
            }
        }
        
        // Check if user has permission to add workpoint to this organisation
        if ($_SESSION['role'] === 'organisation_user') {
            $stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
            $stmt->execute([$_SESSION['user']]);
            $org = $stmt->fetch();
            
            if (!$org || $_POST['organisation_id'] != $org['unic_id']) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Access denied to this organisation']);
                    exit;
                } else {
                    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Access denied to this organisation'];
                    header('Location: admin_workpoints.php');
                    exit;
                }
            }
        }
        
        // Check if user+password combination is unique across all tables
        $uniquenessCheck = checkUserPasswordUniqueness($pdo, $_POST['user'], $_POST['password']);
        if (!$uniquenessCheck['unique']) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $uniquenessCheck['message']]);
                exit;
            } else {
                $_SESSION['alert'] = ['type' => 'danger', 'message' => $uniquenessCheck['message']];
                header('Location: admin_workpoints.php');
                exit;
            }
        }
        
        // Validate country and language
        $country = strtoupper(trim($_POST['country'] ?? ''));
        $language = strtoupper(trim($_POST['language'] ?? ''));
        
        // Validate language (must be 2 characters)
        if (!preg_match('/^[A-Z]{2}$/', $language)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Language must be a 2-letter code (e.g., EN, RO, LT)']);
                exit;
            } else {
                $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Language must be a 2-letter code (e.g., EN, RO, LT)'];
                header('Location: admin_workpoints.php');
                exit;
            }
        }
        
        // Validate country (must not be empty)
        if (empty($country)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Country is required']);
                exit;
            } else {
                $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Country is required'];
                header('Location: admin_workpoints.php');
                exit;
            }
        }
        
        $stmt = $pdo->prepare('INSERT INTO working_points
            (name_of_the_place, description_of_the_place, address, landmark, directions, lead_person_name, lead_person_phone_nr, workplace_phone_nr, booking_phone_nr, booking_sms_number, email, user, password, organisation_id, country, language, we_handling, specialist_relevance)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['name_of_the_place'],
            $_POST['description_of_the_place'] ?? '',
            $_POST['address'],
            $_POST['landmark'] ?? '',
            $_POST['directions'] ?? '',
            $_POST['lead_person_name'] ?? '',
            $_POST['lead_person_phone_nr'] ?? '',
            $_POST['workplace_phone_nr'] ?? '',
            $_POST['booking_phone_nr'] ?? '',
            $_POST['booking_sms_number'] ?? '',
            $_POST['email'] ?? '',
            $_POST['user'],
            $_POST['password'],
            $_POST['organisation_id'],
            $country,
            $language,
            $_POST['we_handling'] ?? '',
            $_POST['specialist_relevance'] ?? null
        ]);

        $id = $pdo->lastInsertId();
        log_action($pdo, $_SESSION['user'] ?? 'admin', 'insert', 'working_points', $id, $stmt->queryString);
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Workpoint added successfully']);
        } else {
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'Workpoint added successfully'];
            header('Location: admin_workpoints.php');
        }
        
    } catch (PDOException $e) {
        error_log("Database error in process_add_workpoint.php: " . $e->getMessage());
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Database error occurred'];
            header('Location: admin_workpoints.php');
        }
    }
} else {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    } else {
        header('Location: admin_workpoints.php');
    }
}
?>