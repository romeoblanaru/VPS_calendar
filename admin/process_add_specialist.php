<?php
session_start();

// Check if user is logged in before including session.php
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

include '../includes/session.php';
include __DIR__ . '/../includes/db.php';

$role = $_SESSION['role'] ?? '';
$dual_role = $_SESSION['dual_role'] ?? '';
$user = $_SESSION['user'] ?? '';

if (!in_array($role, ['admin_user', 'super_user', 'organisation_user', 'workpoint_supervisor', 'workpoint_user']) && !in_array($dual_role, ['workpoint_supervisor'])) {
    // Instead of redirecting, show an error message
    $error_message = "Access denied. Your role ($role) is not authorized to add specialists.";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $redirect_url = $_POST['redirect_url'] ?? $_GET['redirect_url'] ?? 'admin_dashboard.php';
        header("Location: " . $redirect_url . "?error=" . urlencode($error_message));
        exit;
    } else {
        // For GET requests, show the error in the form
        $show_error = $error_message;
    }
}

require_once __DIR__ . '/../includes/credentials.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Get form data
        $name = $_POST['name'] ?? '';
        $speciality = $_POST['speciality'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone_nr = $_POST['phone_nr'] ?? '';
        $specialist_user = $_POST['user'] ?? '';
        $specialist_password = $_POST['password'] ?? '';
        $h_of_email_schedule = $_POST['h_of_email_schedule'] ?? 9;
        $m_of_email_schedule = $_POST['m_of_email_schedule'] ?? 0;
        $working_points = $_POST['working_points'] ?? [];
        
        // Validate required fields
        if (empty($name) || empty($speciality) || empty($email)) {
            throw new Exception('Name, speciality, and email are required.');
        }

        // Validate user and password fields
        if (empty($specialist_user) || empty($specialist_password)) {
            throw new Exception('Username and password are required.');
        }

        // Check if user+password combination is unique across all tables
        $uniquenessCheck = checkUserPasswordUniqueness($pdo, $specialist_user, $specialist_password);
        if (!$uniquenessCheck['unique']) {
            throw new Exception($uniquenessCheck['message']);
        }
        
        // Get organisation ID based on user role or from URL parameters
        if (isset($_GET['organisation_id']) && !empty($_GET['organisation_id'])) {
            // Use organisation ID from URL parameter
            $organisation_id = $_GET['organisation_id'];
        } elseif ($role === 'admin_user' || $role === 'super_user') {
            // For admin_user/super_user, get organisation from form or use default
            $organisation_id = $_POST['organisation_id'] ?? 1; // Default to first org
        } elseif ($role === 'organisation_user') {
            $org_stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
            $org_stmt->execute([$user]);
            $org = $org_stmt->fetch();
            $organisation_id = $org['unic_id'];
        } elseif ($role === 'workpoint_user') {
            // Get organisation_id from working_points table
            $wp_stmt = $pdo->prepare("SELECT organisation_id FROM working_points WHERE user = ?");
            $wp_stmt->execute([$user]);
            $wp = $wp_stmt->fetch();
            if ($wp && isset($wp['organisation_id'])) {
                $organisation_id = $wp['organisation_id'];
            } else {
                throw new Exception('Could not determine organisation for this workpoint user.');
            }
        } elseif ($role === 'workpoint_supervisor' || $dual_role === 'workpoint_supervisor') {
            $org_stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
            $org_stmt->execute([$user]);
            $org = $org_stmt->fetch();
            $organisation_id = $org['unic_id'];
        }
        
        // Generate unique ID for specialist - get max ID and increment
        $stmt = $pdo->prepare("SELECT MAX(unic_id) as max_id FROM specialists");
        $stmt->execute();
        $result = $stmt->fetch();
        $specialist_unic_id = ($result['max_id'] ?? 0) + 1;
        
        // Insert specialist with user and password
        $stmt = $pdo->prepare("
            INSERT INTO specialists (unic_id, name, speciality, email, phone_nr, user, password, organisation_id, h_of_email_schedule, m_of_email_schedule) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $specialist_unic_id, 
            $name, 
            $speciality, 
            $email, 
            $phone_nr, 
            $specialist_user, 
            $specialist_password, 
            $organisation_id, 
            $h_of_email_schedule, 
            $m_of_email_schedule
        ]);
        
        // Handle workpoint assignment
        if (!empty($_POST['workpoint_id'])) {
            $working_points[] = $_POST['workpoint_id'];
        }
        
        // Insert working program for each workpoint
        if (!empty($working_points)) {
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            foreach ($working_points as $workpoint_id) {
                foreach ($days as $day) {
                    // Insert default working program (empty shifts)
                    $stmt = $pdo->prepare("
                        INSERT INTO working_program (specialist_id, working_place_id, organisation_id, day_of_week, 
                                                   shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end) 
                        VALUES (?, ?, ?, ?, '00:00:00', '00:00:00', '00:00:00', '00:00:00', '00:00:00', '00:00:00')
                    ");
                    $stmt->execute([$specialist_unic_id, $workpoint_id, $organisation_id, ucfirst($day)]);
                }
            }
        }

        // Handle schedule data if provided
        if (!empty($_POST['schedule_data'])) {
            $schedule_data = json_decode($_POST['schedule_data'], true);
            if ($schedule_data) {
                foreach ($schedule_data as $day => $shifts) {
                    // Update the working program with actual schedule data
                    $stmt = $pdo->prepare("
                        UPDATE working_program 
                        SET shift1_start = ?, shift1_end = ?, shift2_start = ?, shift2_end = ?, shift3_start = ?, shift3_end = ?
                        WHERE specialist_id = ? AND working_place_id = ? AND day_of_week = ?
                    ");
                    $stmt->execute([
                        $shifts['shift1_start'] ?? '00:00:00',
                        $shifts['shift1_end'] ?? '00:00:00',
                        $shifts['shift2_start'] ?? '00:00:00',
                        $shifts['shift2_end'] ?? '00:00:00',
                        $shifts['shift3_start'] ?? '00:00:00',
                        $shifts['shift3_end'] ?? '00:00:00',
                        $specialist_unic_id,
                        $working_points[0] ?? $_POST['workpoint_id'],
                        ucfirst($day)
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        // Return JSON response for AJAX calls
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Specialist added successfully!', 'specialist_id' => $specialist_unic_id]);
            exit;
        }
        
        // Redirect with success message for regular form submissions
        $redirect_url = $_POST['redirect_url'] ?? $_GET['redirect_url'] ?? 'admin_specialists.php';
        header("Location: $redirect_url?success=1&specialist_id=$specialist_unic_id");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollback();
        
        // Return JSON response for AJAX calls
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        
        // For regular form submissions, redirect with error
        $redirect_url = $_POST['redirect_url'] ?? $_GET['redirect_url'] ?? 'admin_specialists.php';
        header("Location: $redirect_url?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// If not POST, show the form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Specialist</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3><i class="fas fa-user-plus"></i> Add New Specialist</h3>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($show_error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($show_error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($_GET['redirect_url'] ?? 'admin_specialists.php') ?>">
                    <?php if (isset($_GET['organisation_id'])): ?>
                    <input type="hidden" name="organisation_id" value="<?= htmlspecialchars($_GET['organisation_id']) ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="speciality" class="form-label">Speciality *</label>
                                <input type="text" class="form-control" id="speciality" name="speciality" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone_nr" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone_nr" name="phone_nr">
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    // Get working points based on user role
                    if ($role === 'admin_user' || $role === 'super_user') {
                        $wp_stmt = $pdo->query("SELECT * FROM working_points ORDER BY name_of_the_place");
                    } elseif ($role === 'organisation_user') {
                        $org_stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
                        $org_stmt->execute([$user]);
                        $org = $org_stmt->fetch();
                        $wp_stmt = $pdo->prepare("SELECT * FROM working_points WHERE organisation_id = ? ORDER BY name_of_the_place");
                        $wp_stmt->execute([$org['unic_id']]);
                    } elseif ($role === 'workpoint_user') {
                        // Get organisation_id from working_points table for workpoint_user
                        $wp_stmt = $pdo->prepare("SELECT organisation_id FROM working_points WHERE user = ?");
                        $wp_stmt->execute([$user]);
                        $wp = $wp_stmt->fetch();
                        if ($wp && isset($wp['organisation_id'])) {
                            $wp_stmt = $pdo->prepare("SELECT * FROM working_points WHERE organisation_id = ? ORDER BY name_of_the_place");
                            $wp_stmt->execute([$wp['organisation_id']]);
                        } else {
                            throw new Exception('Could not determine organisation for this workpoint user.');
                        }
                    } elseif ($role === 'workpoint_supervisor' || $dual_role === 'workpoint_supervisor') {
                        $org_stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
                        $org_stmt->execute([$user]);
                        $org = $org_stmt->fetch();
                        $wp_stmt = $pdo->prepare("SELECT * FROM working_points WHERE organisation_id = ? ORDER BY name_of_the_place");
                        $wp_stmt->execute([$org['unic_id']]);
                    }
                    $working_points = $wp_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get pre-selected workpoint from URL
                    $pre_selected_workpoint = $_GET['workpoint_id'] ?? null;
                    ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Assign to Working Points *</label>
                        <div class="row">
                            <?php foreach ($working_points as $wp): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="working_points[]" 
                                               value="<?= $wp['unic_id'] ?>" id="wp_<?= $wp['unic_id'] ?>"
                                               <?= ($pre_selected_workpoint && $pre_selected_workpoint == $wp['unic_id']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="wp_<?= $wp['unic_id'] ?>">
                                            <?= htmlspecialchars($wp['name_of_the_place']) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note:</strong> A default working schedule will be created for each selected workpoint. 
                        You can modify the schedule after adding the specialist.
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= htmlspecialchars($_GET['redirect_url'] ?? 'admin_specialists.php') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Specialist
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>