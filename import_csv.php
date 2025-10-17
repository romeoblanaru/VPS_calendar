<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin_user') {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';

function parse_csv_sections($file_path) {
    $sections = [];
    $current_section = null;

    if (($handle = fopen($file_path, "r")) !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0])) continue;

            if (str_starts_with(trim($row[0]), '#')) {
                $current_section = trim($row[0]);
                $sections[$current_section] = [];
            } elseif ($current_section) {
                $sections[$current_section][] = $row;
            }
        }
        fclose($handle);
    }
    return $sections;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $sections = parse_csv_sections($file);
    $log = [];

    try {
        $pdo->beginTransaction();

        // 1. Insert ORGANISATION
        $org_row = $sections['# ORGANISATIONS'][1] ?? null;
        if (!$org_row || count($org_row) < 14) {
            throw new Exception("❌ Invalid organisation row. Expected 14 columns.");
        }

        $stmt = $pdo->prepare("INSERT INTO organisations 
            (user, pasword, contact_name, position, oficial_company_name, alias_name, company_head_office_address,
             company_phone_nr, owner_name, owner_phone_nr, email_address, www_address, country)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute(array_slice($org_row, 0, 13));
        $org_user = $org_row[0];
        $org_id = $pdo->lastInsertId();
        $log[] = "✅ Organisation '{$org_row[4]}' inserted with ID $org_id.";

        // 2. Insert WORKING POINTS
        $wp_map = [];
        foreach (array_slice($sections['# WORKING POINTS'], 1) as $i => $row) {
            if (count($row) < 9) {
                $log[] = "❌ Working Point row $i: expected 9 columns.";
                continue;
            }

            $wp_ident = $row[0];
            // Default country and language if not provided in CSV (can be updated later)
            $country = count($row) > 10 ? strtoupper(trim($row[10])) : 'GB';
            $language = count($row) > 11 ? strtoupper(trim($row[11])) : 'EN';
            
            $stmt = $pdo->prepare("INSERT INTO working_points 
                (name_of_the_place, address, user, password, lead_person_name, lead_person_phone_nr,
                 workplace_phone_nr, booking_phone_nr, email, organisation_id, country, language)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $org_id, $country, $language
            ]);
            $wp_map[$wp_ident] = $pdo->lastInsertId();
            $log[] = "✅ Working Point #$wp_ident inserted with ID {$wp_map[$wp_ident]}";
        }

        // 3. Insert SPECIALISTS
        $spec_map = [];
        foreach (array_slice($sections['# SPECIALISTS'], 1) as $i => $row) {
            if (count($row) < 10) {
                $log[] = "❌ Specialist row $i: expected 10 columns.";
                continue;
            }

            $spec_ident = $row[0];
            $wp_list = explode(';', $row[1]);
            $assigned_wp = implode(',', array_map(fn($id) => $wp_map[trim($id)] ?? '0', $wp_list));

            $stmt = $pdo->prepare("INSERT INTO specialists
                (organisation_id, user, password, name, speciality, email, phone_nr, h_of_email_schedule, m_of_email_schedule)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $org_id, $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9]
            ]);
            $spec_map[$spec_ident] = $pdo->lastInsertId();
            $log[] = "✅ Specialist #$spec_ident inserted with ID {$spec_map[$spec_ident]}";
        }

        // 4. Insert WORKING_PROGRAM
        foreach (array_slice($sections['# WORKING_PROGRAM'], 1) as $i => $row) {
            if (count($row) < 10) {
                $log[] = "❌ Working Program row $i: expected 10 columns.";
                continue;
            }

            $spec_id = $spec_map[$row[0]] ?? null;
            $wp_id = $wp_map[$row[1]] ?? null;
            if (!$spec_id || !$wp_id) {
                $log[] = "❌ Working Program row $i: invalid reference IDs.";
                continue;
            }

            $stmt = $pdo->prepare("INSERT INTO working_program
                (specialist_id, working_place_id, day_of_week, shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $spec_id, $wp_id, $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8]
            ]);
            $log[] = "✅ Working program added for specialist $row[0] at workplace $row[1]";
        }

        // 5. Insert SERVICES
        foreach (array_slice($sections['# SERVICES'], 1) as $i => $row) {
            if (count($row) < 7) {
                $log[] = "❌ Service row $i: expected 7 columns.";
                continue;
            }

            $spec_id = $spec_map[$row[0]] ?? null;
            $wp_id = $wp_map[$row[1]] ?? null;
            if (!$spec_id || !$wp_id) {
                $log[] = "❌ Service row $i: invalid reference IDs.";
                continue;
            }

            $stmt = $pdo->prepare("INSERT INTO services 
                (id_specialist, id_work_place, id_organisation, name_of_service, minutes_to_finish, price_of_service, vat_applied, procent_vat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $spec_id, $wp_id, $org_id, $row[2], $row[3], $row[4], $row[5], $row[6]
            ]);
            $log[] = "✅ Service '{$row[2]}' added for specialist $row[0] at workplace $row[1]";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $log[] = "❌ Fatal error: " . $e->getMessage();
    }

    $_SESSION['csv_import_status'] = ['success' => true, 'message' => implode("<br>", $log)];
    header("Location: admin/admin_dashboard.php#import_organisations");
    exit;
}
?>
