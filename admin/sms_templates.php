<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/session.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin_user') {
    header('Location: ../login.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_template') {
        $working_point_id = (int)$_POST['working_point_id'];
        $template_value = $_POST['template_value'];
        
        // Update or insert the SMS template
        $stmt = $pdo->prepare("
            INSERT INTO workingpoint_settings_and_attr 
            (working_point_id, setting_key, setting_value, description) 
            VALUES (?, 'sms_cancellation_template', ?, 'SMS template for booking cancellation')
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        if ($stmt->execute([$working_point_id, $template_value])) {
            $success_message = "SMS template updated successfully!";
        } else {
            $error_message = "Failed to update SMS template.";
        }
    }
}

// Get all working points and their SMS templates
$stmt = $pdo->prepare("
    SELECT 
        wp.unic_id,
        wp.name_of_the_place,
        wp.address,
        wp.phone_nr,
        wsa.setting_value as sms_template,
        o.alias_name as organisation_name
    FROM working_points wp
    LEFT JOIN workingpoint_settings_and_attr wsa 
        ON wp.unic_id = wsa.working_point_id 
        AND wsa.setting_key = 'sms_cancellation_template'
    LEFT JOIN organisations o ON wp.organisation_id = o.unic_id
    WHERE wp.deleted = 0 OR wp.deleted IS NULL
    ORDER BY o.alias_name, wp.name_of_the_place
");
$stmt->execute();
$working_points = $stmt->fetchAll();

// Default template for reference
$default_template = 'Your Booking ID:{booking_id} at {organisation_alias} - {workpoint_name} ({workpoint_address}) for {service_name} at {start_time} - {booking_date} was canceled. Call {workpoint_phone} if needed.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Templates - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include admin sidebar if exists -->
            <div class="col-md-12">
                <h1 class="mt-4">SMS Cancellation Templates</h1>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Available Template Variables</h5>
                        <p class="card-text">You can use the following variables in your SMS templates:</p>
                        <div class="row">
                            <div class="col-md-6">
                                <ul>
                                    <li><code>{booking_id}</code> - Booking ID number</li>
                                    <li><code>{organisation_alias}</code> - Organisation name</li>
                                    <li><code>{workpoint_name}</code> - Working point name</li>
                                    <li><code>{workpoint_address}</code> - Working point address</li>
                                    <li><code>{workpoint_phone}</code> - Working point phone number</li>
                                    <li><code>{service_name}</code> - Name of the booked service</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul>
                                    <li><code>{start_time}</code> - Booking start time (HH:mm)</li>
                                    <li><code>{end_time}</code> - Booking end time (HH:mm)</li>
                                    <li><code>{booking_date}</code> - Full date (e.g., Monday 15 January 2025)</li>
                                    <li><code>{client_name}</code> - Client's full name</li>
                                    <li><code>{specialist_name}</code> - Specialist's name</li>
                                </ul>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <strong>Default Template:</strong><br>
                            <code><?= htmlspecialchars($default_template) ?></code>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">SMS Templates by Working Point</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Organisation</th>
                                        <th>Working Point</th>
                                        <th>SMS Template</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($working_points as $wp): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($wp['organisation_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($wp['name_of_the_place']) ?></strong><br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($wp['address']) ?><br>
                                                    <?= htmlspecialchars($wp['phone_nr']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <form method="POST" id="form_<?= $wp['unic_id'] ?>">
                                                    <input type="hidden" name="action" value="update_template">
                                                    <input type="hidden" name="working_point_id" value="<?= $wp['unic_id'] ?>">
                                                    <textarea 
                                                        class="form-control" 
                                                        name="template_value" 
                                                        rows="3"
                                                        placeholder="Enter SMS template..."
                                                    ><?= htmlspecialchars($wp['sms_template'] ?? $default_template) ?></textarea>
                                                </form>
                                            </td>
                                            <td>
                                                <button 
                                                    type="submit" 
                                                    form="form_<?= $wp['unic_id'] ?>" 
                                                    class="btn btn-sm btn-primary"
                                                >
                                                    Save
                                                </button>
                                                <button 
                                                    type="button" 
                                                    class="btn btn-sm btn-secondary"
                                                    onclick="document.querySelector('#form_<?= $wp['unic_id'] ?> textarea').value = '<?= addslashes($default_template) ?>'"
                                                >
                                                    Reset to Default
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 mb-5">
                    <a href="../index.php" class="btn btn-secondary">Back to Calendar</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>