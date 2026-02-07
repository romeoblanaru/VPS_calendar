<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['admin_user', 'super_user'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../includes/db.php';

// Detect AJAX requests to avoid full page includes/redirects
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$import_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $import_error = 'No file uploaded or upload error.';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        if (!is_readable($file)) {
            $import_error = 'Uploaded file is not readable.';
        } else {
            $csv = array_map('str_getcsv', file($file));
            if (!$csv || count($csv) < 2) {
                $import_error = 'The CSV file appears to be empty or invalid.';
            } else {
                $sections = [];
                $current_section = null;
                foreach ($csv as $row) {
                    if (empty($row[0])) continue;
                    if (strpos(trim($row[0]), '#') === 0) {
                        $current_section = trim($row[0]);
                        $sections[$current_section] = [];
                    } elseif ($current_section) {
                        $sections[$current_section][] = $row;
                    }
                }
                // Pre-check for duplicate user/password pairs inside CSV and against DB
                // Collect pairs from ORGANISATIONS, SPECIALISTS, WORKING POINTS sections
                $csv_user_pass_pairs = [];
                $duplicate_messages = [];
                $pair_index = [];

                // Helper to register a pair
                $registerPair = function($user, $pass, $source, $label) use (&$csv_user_pass_pairs, &$pair_index, &$duplicate_messages) {
                    $user = trim((string)$user);
                    $pass = trim((string)$pass);
                    if ($user === '' || $pass === '') {
                        return; // ignore empty pairs
                    }
                    $key = $user . "|" . $pass;
                    if (isset($pair_index[$key])) {
                        $duplicate_messages[] = "Duplicate in CSV: '" . htmlspecialchars($user) . "'/'" . htmlspecialchars($pass) . "' appears in " . $pair_index[$key]['source'] . " and " . $source . ".";
                    } else {
                        $pair_index[$key] = ['source' => $source, 'label' => $label];
                    }
                    $csv_user_pass_pairs[$key] = ['user' => $user, 'pass' => $pass, 'source' => $source, 'label' => $label];
                };

                // ORGANISATIONS section (single row)
                $org_row_for_pairs = $sections['# ORGANISATIONS'][1] ?? null;
                if ($org_row_for_pairs && count($org_row_for_pairs) >= 2) {
                    $registerPair($org_row_for_pairs[0], $org_row_for_pairs[1], 'ORGANISATIONS', 'organisation');
                }

                // SPECIALISTS section
                if (!empty($sections['# SPECIALISTS'])) {
                    foreach (array_slice($sections['# SPECIALISTS'], 1) as $srow) {
                        if (count($srow) >= 4) {
                            $label = isset($srow[1]) ? ('specialist ' . $srow[1]) : 'specialist';
                            $registerPair($srow[2], $srow[3], 'SPECIALISTS', $label);
                        }
                    }
                }

                // WORKING POINTS section
                if (!empty($sections['# WORKING POINTS'])) {
                    foreach (array_slice($sections['# WORKING POINTS'], 1) as $wrow) {
                        if (count($wrow) >= 7) {
                            $label = isset($wrow[0]) ? ('workpoint ' . $wrow[0]) : 'workpoint';
                            $registerPair($wrow[5], $wrow[6], 'WORKING POINTS', $label);
                        }
                    }
                }

                // If internal duplicates detected, stop early
                if (!empty($duplicate_messages)) {
                    $import_error = implode("\n", $duplicate_messages) . "\nNo records were inserted due to duplicate user/password combinations.";
                }

                // Check against database if no internal duplicates
                if (!$import_error && !empty($csv_user_pass_pairs)) {
                    // Prepare statements
                    $stmt_org = $pdo->prepare("SELECT 'organisations' AS t FROM organisations WHERE user = ? AND pasword = ? LIMIT 1");
                    $stmt_spec = $pdo->prepare("SELECT 'specialists' AS t FROM specialists WHERE user = ? AND password = ? LIMIT 1");
                    $stmt_wp = $pdo->prepare("SELECT 'working_points' AS t FROM working_points WHERE user = ? AND password = ? LIMIT 1");
                    $stmt_su = $pdo->prepare("SELECT 'super_users' AS t FROM super_users WHERE user = ? AND pasword = ? LIMIT 1");

                    $db_conflicts = [];
                    foreach ($csv_user_pass_pairs as $pair) {
                        $u = $pair['user'];
                        $p = $pair['pass'];
                        if ($stmt_org->execute([$u, $p]) && $stmt_org->fetch()) {
                            $db_conflicts[] = "Conflict with DB: user/password '" . htmlspecialchars($u) . "'/'" . htmlspecialchars($p) . "' already exists in organisations.";
                        } elseif ($stmt_spec->execute([$u, $p]) && $stmt_spec->fetch()) {
                            $db_conflicts[] = "Conflict with DB: user/password '" . htmlspecialchars($u) . "'/'" . htmlspecialchars($p) . "' already exists in specialists.";
                        } elseif ($stmt_wp->execute([$u, $p]) && $stmt_wp->fetch()) {
                            $db_conflicts[] = "Conflict with DB: user/password '" . htmlspecialchars($u) . "'/'" . htmlspecialchars($p) . "' already exists in working_points.";
                        } elseif ($stmt_su->execute([$u, $p]) && $stmt_su->fetch()) {
                            $db_conflicts[] = "Conflict with DB: user/password '" . htmlspecialchars($u) . "'/'" . htmlspecialchars($p) . "' already exists in super_users.";
                        }
                    }

                    if (!empty($db_conflicts)) {
                        $import_error = implode("\n", $db_conflicts) . "\nNo records were inserted due to duplicate user/password combinations in the database.";
                    }
                }

                if ($import_error) {
                    // Skip insertion, show error modal below
                } else try {
                    $pdo->beginTransaction();
                    $org_row = $sections['# ORGANISATIONS'][1] ?? null;
                    if (!$org_row || count($org_row) < 13) {
                        throw new Exception('Invalid organisation row. Expected at least 13 columns.');
                    }
                    $sql = "INSERT INTO organisations (user, pasword, contact_name, position, oficial_company_name, alias_name, company_head_office_address, company_phone_nr, owner_name, owner_phone_nr, email_address, www_address, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = array_slice($org_row, 0, 13);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $org_id = $pdo->lastInsertId();
                    $spec_map = [];
                    if (!empty($sections['# SPECIALISTS'])) {
                        foreach (array_slice($sections['# SPECIALISTS'], 1) as $row) {
                            if (count($row) < 9) continue;
                            $spec_ident = $row[0];
                            $sql = "INSERT INTO specialists (organisation_id, user, password, name, speciality, email, phone_nr, h_of_email_schedule, m_of_email_schedule) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $params = [$org_id, $row[2], $row[3], $row[1], $row[4], $row[5], $row[6], $row[7], $row[8]];
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $spec_map[$spec_ident] = $pdo->lastInsertId();
                        }
                    }
                    $wp_map = [];
                    if (!empty($sections['# WORKING POINTS'])) {
                        foreach (array_slice($sections['# WORKING POINTS'], 1) as $i => $row) {
                            if (count($row) < 13) continue;

                            $wp_ident = trim($row[0]);

                            // Extract all fields from CSV (18 columns total)
                            $name_of_the_place = trim($row[0] ?? '');
                            $description = trim($row[1] ?? '');
                            $address = trim($row[2] ?? '');
                            $landmark = trim($row[3] ?? '');
                            $directions = trim($row[4] ?? '');
                            $user = trim($row[5] ?? '');
                            $password = trim($row[6] ?? '');
                            $lead_person_name = trim($row[7] ?? '');
                            $lead_person_phone_nr = trim($row[8] ?? '');
                            $workplace_phone_nr = trim($row[9] ?? '');
                            $booking_phone_nr = trim($row[10] ?? '');
                            $booking_sms_number = trim($row[11] ?? '');
                            $email = trim($row[12] ?? '');
                            $country = strtoupper(trim($row[13] ?? 'GB'));
                            $language = strtoupper(trim($row[14] ?? 'EN'));
                            $currency = strtoupper(trim($row[15] ?? 'EUR'));
                            $we_handling = trim($row[16] ?? 'specialisti');
                            $specialist_relevance = strtolower(trim($row[17] ?? 'medium'));

                            // Validate specialist_relevance
                            if (!in_array($specialist_relevance, ['strong', 'medium', 'low', ''])) {
                                $specialist_relevance = 'medium';
                            }

                            try {
                                $sql = "INSERT INTO working_points
                                    (name_of_the_place, description_of_the_place, address, landmark, directions,
                                     user, password, lead_person_name, lead_person_phone_nr, workplace_phone_nr,
                                     booking_phone_nr, booking_sms_number, email, organisation_id,
                                     country, language, curency, we_handling, specialist_relevance)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                $params = [
                                    $name_of_the_place, $description, $address, $landmark, $directions,
                                    $user, $password, $lead_person_name, $lead_person_phone_nr, $workplace_phone_nr,
                                    $booking_phone_nr, $booking_sms_number, $email, $org_id,
                                    $country, $language, $currency, $we_handling, $specialist_relevance
                                ];
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute($params);
                                $wp_id = $pdo->lastInsertId();
                                $wp_map[$wp_ident] = $wp_id;
                            } catch (PDOException $e) {
                                $debug_info = "SQL Error for Working Point Row " . ($i + 2) . ": " . $e->getMessage() . "\n";
                                $debug_info .= "Values: name='" . htmlspecialchars($name_of_the_place) . "', ";
                                $debug_info .= "description='" . htmlspecialchars($description) . "', ";
                                $debug_info .= "address='" . htmlspecialchars($address) . "', ";
                                $debug_info .= "landmark='" . htmlspecialchars($landmark) . "' (len:" . strlen($landmark) . "), ";
                                $debug_info .= "directions='" . htmlspecialchars($directions) . "'";
                                throw new Exception($debug_info);
                            }
                            $has_working_program = false;
                            if (!empty($sections['# WORKING_PROGRAM'])) {
                                foreach ($sections['# WORKING_PROGRAM'] as $prog_row) {
                                    $csv_spec_ref = $prog_row[0];
                                    $csv_wp_ref = $prog_row[1];
                                    $spec_id = isset($spec_map[$csv_spec_ref]) ? $spec_map[$csv_spec_ref] : null;
                                    $wp_id_current = $wp_id;
                                    $wp_id_prog = isset($wp_map[$csv_wp_ref]) ? $wp_map[$csv_wp_ref] : null;
                                    if ($wp_id_prog == $wp_id_current) {
                                        $has_working_program = true;
                                        if ($spec_id && $wp_id_prog) {
                                            $sql = "INSERT INTO working_program (specialist_id, working_place_id, organisation_id, day_of_week, shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                            $params = [$spec_id, $wp_id_prog, $org_id, $prog_row[2], $prog_row[3], $prog_row[4], $prog_row[5], $prog_row[6], $prog_row[7], $prog_row[8]];
                                            $stmt = $pdo->prepare($sql);
                                            $stmt->execute($params);
                                        }
                                    }
                                }
                            }
                            if (!empty($sections['# SERVICES'])) {
                                foreach ($sections['# SERVICES'] as $srv_row) {
                                    if ($srv_row[1] == $wp_ident) {
                                        $spec_id = isset($spec_map[$srv_row[0]]) ? $spec_map[$srv_row[0]] : 0;
                                        $sql = "INSERT INTO services (id_specialist, id_work_place, id_organisation, name_of_service, duration, price_of_service, procent_vat) VALUES (?, ?, ?, ?, ?, ?, ?)";
                                        $params = [$spec_id, $wp_id, $org_id, $srv_row[2], $srv_row[3], $srv_row[4], $srv_row[5]];
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute($params);
                                    }
                                }
                            }
                        }
                    }
                    $pdo->commit();
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true]);
                        exit;
                    } else {
                        header('Location: admin_dashboard.php?view=all_org');
                        exit;
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $import_error = $e->getMessage();
                }
            }
        }
    }
}

// Do not include navbar/footer when embedded in the admin bottom panel or on AJAX
if (basename($_SERVER['SCRIPT_NAME']) === 'import_organisation_csv.php' && !$is_ajax) {
    include '../templates/navbar.php';
}
?>
<style>
    .csv-import-center { display: flex; flex-direction: row; align-items: flex-start; justify-content: space-between; gap: 20px; width: 100%; box-sizing: border-box; }
    .csv-left { flex: 1 1 55%; min-width: 280px; }
    .csv-right { flex: 0 1 40%; min-width: 220px; }
    .csv-import-form { background: #f9f9f9; padding: 18px 24px; border-radius: 8px; box-shadow: 0 2px 8px #eee; display: flex; flex-direction: column; align-items: stretch; width: 100%; box-sizing: border-box; }
    .csv-import-actions { margin-top: 12px; display: flex; gap: 12px; justify-content: flex-start; flex-wrap: wrap; }
    .csv-example-section { width: 100%; max-width: 100%; overflow: hidden; }
    .csv-example-wrapper { width: 100%; max-width: 100%; margin: 0 auto; box-sizing: border-box; }
    @media (max-width: 900px) {
        .csv-import-center { flex-direction: column; }
        .csv-left, .csv-right { flex: 1 1 100%; min-width: auto; }
    }
</style>
<div class="csv-import-center">
    <div class="csv-left">
        <h2 style="margin-bottom:14px;">Import Organisation from CSV</h2>
        <form method="POST" enctype="multipart/form-data" action="import_organisation_csv.php" class="csv-import-form" id="csvImportForm">
            <label for="csv_file" class="form-label" style="margin-bottom:8px;"><strong>Upload CSV file</strong></label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" required class="form-control" style="margin-bottom:0; width:60%;" onchange="if(this.files.length){document.getElementById('csvImportForm').submit();}">
            <div class="csv-import-actions" style="margin-top: 14px;">
                <small style="color:#666;">Select a file to upload. Templates available at the bottom.</small>
            </div>
        </form>
    </div>
    <div class="csv-right">
        <div class="csv-example-section" style="margin:0; width:100%; text-align:center;">
            <div class="csv-example-wrapper" style="overflow:hidden;">
                <a href="../sample_csv/organisation_template.jpg" target="_blank" rel="noopener" style="display:inline-block; max-width:100%;">
                    <img src="../sample_csv/organisation_template.jpg" alt="CSV Example" style="display:block; width:100%; max-width:100%; height:auto; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; object-fit:contain;">
                </a>
            </div>
            <small style="display:block; color:#666; margin-top:6px;">Click image to view full size</small>
        </div>
    </div>
</div>
<div style="margin-top: 14px; width: 100%;">
    <div style="background:#f9f9f9; border:1px solid #eee; padding:12px 14px; border-radius:8px; display:flex; justify-content:center; align-items:center; gap:18px; flex-wrap:wrap;">
        <a href="../sample_csv/organisations_template.ods" download class="btn btn-outline-primary btn-sm" style="padding:8px 14px; min-width:180px; text-decoration:none;">Download ODS Template</a>
        <a href="../sample_csv/organisations_template.csv" download class="btn btn-outline-primary btn-sm" style="padding:8px 14px; min-width:180px; text-decoration:none;">Download CSV Template</a>
    </div>
</div>
<?php if ($import_error): ?>
<!-- Modal for error -->
<div id="errorModal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <div style="background:#fff;padding:22px 22px;border-radius:10px;max-width:560px;width:92%;box-shadow:0 8px 30px rgba(0,0,0,0.2);">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
            <div style="font-size:22px;">⚠️</div>
            <h4 style="color:#b71c1c;margin:0;font-size:18px;">CSV Import Failed</h4>
        </div>
        <div style="color:#7a1f1f;font-size:0.95em;margin:8px 0 14px 0;line-height:1.45;background:#fff5f5;border:1px solid #f1c0c0;border-radius:6px;padding:10px 12px;max-height:40vh;overflow:auto;">
            <?php $lines = preg_split("/\r\n|\r|\n/", $import_error);
            foreach ($lines as $line) {
                $t = trim($line);
                if ($t === '') continue; ?>
                <div style="display:flex;align-items:flex-start;gap:8px;margin:6px 0;">
                    <span style="line-height:1.2;">⚠️</span>
                    <span><?php echo htmlspecialchars($t); ?></span>
                </div>
            <?php } ?>
        </div>
        <div style="font-size:0.9em;color:#333;margin-bottom:12px;">
            <strong>No records were inserted</strong> due to the errors above. Please adjust the CSV and try again.
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;">
            <button onclick="document.getElementById('errorModal').style.display='none'" style="background:#6c757d;color:#fff;padding:8px 16px;border:none;border-radius:6px;">Close</button>
        </div>
    </div>
</div>
<script>window.scrollTo(0,0);</script>
<?php endif; ?>
<script>
(function(){
    var form = document.getElementById('csvImportForm');
    if (!form) return;
    var container = document.getElementById('bottom_panel_content');
    // If embedded in admin dashboard (container present), intercept submit to avoid full page reload
    if (container) {
        form.addEventListener('submit', function(e){
            e.preventDefault();
            var fd = new FormData(form);
            fetch('import_organisation_csv.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd })
                .then(function(res){
                    var ct = res.headers.get('content-type') || '';
                    if (ct.indexOf('application/json') !== -1) return res.json();
                    return res.text().then(function(t){ return { __html: t }; });
                })
                .then(function(payload){
                    if (!payload) return;
                    if (payload.__html !== undefined) {
                        container.innerHTML = payload.__html;
                        window.scrollTo(0,0);
                        return;
                    }
                    if (payload.success) {
                        if (typeof loadBottomPanel === 'function') {
                            loadBottomPanel('list_all_org');
                        }
                    }
                })
                .catch(function(err){ console.error('CSV upload failed', err); });
        });
    }
})();
</script>
<?php if (basename($_SERVER['SCRIPT_NAME']) === 'import_organisation_csv.php' && !$is_ajax) { include '../templates/footer.php'; } ?>








