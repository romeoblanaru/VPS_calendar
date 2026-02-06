<?php
// load_bottom_panel.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/db.php';

function shortTime($time) {
	// Accepts a string like '09:00:00' and returns '09:00'
	return substr($time, 0, 5);
}

if (isset($_GET['action']) && $_GET['action'] === 'list_all_org') {
	// Fetch all data
	$orgs = $pdo->query("SELECT * FROM organisations ORDER BY alias_name, oficial_company_name")->fetchAll(PDO::FETCH_ASSOC);
	$wps = [];
	foreach ($pdo->query("SELECT wp.*, o.country FROM working_points wp LEFT JOIN organisations o ON wp.organisation_id = o.unic_id ORDER BY wp.organisation_id, wp.name_of_the_place") as $row) {
		$wps[$row['organisation_id']][] = $row;
	}
	$specs = [];
	foreach ($pdo->query("SELECT * FROM specialists ORDER BY organisation_id, name") as $row) {
		$specs[$row['organisation_id']][] = $row;
	}
	$wpgr = [];
	foreach ($pdo->query("SELECT * FROM working_program ORDER BY specialist_id, day_of_week") as $row) {
		$wpgr[$row['specialist_id']][] = $row;
	}

	// Minimal CSS to ensure working
	echo '<link rel="stylesheet" href="../assets/css/main.css">';
	echo '<style>
		.specialist-name-link {
			text-decoration: none;
			color: inherit;
			transition: color 0.2s ease;
		}
		.specialist-name-link:hover {
			color: #6c757d;
		}
	</style>';

	// Helper to detect if a working_program row has any non-zero shift
	$hasNonZeroShift = function(array $pr): bool {
		return (
			(($pr['shift1_start'] ?? '00:00:00') !== '00:00:00' && ($pr['shift1_end'] ?? '00:00:00') !== '00:00:00') ||
			(($pr['shift2_start'] ?? '00:00:00') !== '00:00:00' && ($pr['shift2_end'] ?? '00:00:00') !== '00:00:00') ||
			(($pr['shift3_start'] ?? '00:00:00') !== '00:00:00' && ($pr['shift3_end'] ?? '00:00:00') !== '00:00:00')
		);
	};

	// List all organisations
	foreach ($orgs as $org):
		$org_id = $org['unic_id'];
?>
    <div class="org-card" id="org-card-<?=$org_id?>">
        <div class="org-header" onclick="toggleCard(event, <?=intval($org_id)?>)">
            <span class="caret">&#9654;</span>
            <div style="display: flex; width: 100%; justify-content: space-between; align-items: flex-start; padding: 8px;">
                <!-- Left Side -->
                <div style="flex: 1; padding-right: 20px;">
                    <div style="margin-bottom: 8px;">
                        <span style="color: #666; font-size: 14px;">[<?=htmlspecialchars($org_id)?>]</span>
                        <span style="font-weight: bold; font-size: 18px; color: #333; cursor: pointer; margin-left: 8px; transition: color 0.2s ease, background-color 0.2s ease; padding: 2px 4px; border-radius: 3px;" 
                              onclick="modifyOrganisation(<?=intval($org_id)?>, <?=htmlspecialchars(json_encode($org['alias_name']))?>); event.stopPropagation();"
                              onmouseover="this.style.backgroundColor='#f0f8ff'; this.style.color='#0066cc';"
                              onmouseout="this.style.backgroundColor='transparent'; this.style.color='#333';">
                            <?=htmlspecialchars($org['alias_name'])?>
                        </span>
                        <span style="color: #555; font-size: 15px; margin-left: 8px;">
                            ( <span style="cursor: pointer; transition: color 0.2s ease, background-color 0.2s ease; padding: 2px 4px; border-radius: 3px;" 
                                   onclick="modifyOrganisation(<?=intval($org_id)?>, <?=htmlspecialchars(json_encode($org['alias_name']))?>); event.stopPropagation();"
                                   onmouseover="this.style.backgroundColor='#f0f8ff'; this.style.color='#0066cc';"
                                   onmouseout="this.style.backgroundColor='transparent'; this.style.color='#555';">
                                <?=htmlspecialchars($org['oficial_company_name'])?>
                            </span> )
                        </span>
                    </div>
                    <div style="margin-bottom: 4px; color: #666; font-size: 14px;">
                        <span style="font-weight: 600;"><?=htmlspecialchars($org['position'])?>:</span>
                        <?=htmlspecialchars($org['contact_name'])?> | <?=htmlspecialchars($org['company_phone_nr'])?>
                    </div>
                    <div style="color: #666; font-size: 14px;">
                        <span style="font-weight: 600;">Owner:</span>
                        <?=htmlspecialchars($org['owner_name'])?> | <?=htmlspecialchars($org['owner_phone_nr'])?>
                    </div>
                </div>
                
                <!-- Right Side -->
                <div style="flex: 1; text-align: right; padding-left: 20px; border-left: 1px solid #eee;">
                    <div style="margin-bottom: 4px; color: #666; font-size: 14px;">
                        <span style="font-weight: 600;">Address:</span> <?=htmlspecialchars($org['company_head_office_address'])?>, 
                        <span style="font-weight: 600;">Country:</span> <?=htmlspecialchars($org['country'])?>
                    </div>
                    <div style="margin-bottom: 4px; color: #666; font-size: 14px;">
                        <span style="font-weight: 600;">Web:</span> <?=htmlspecialchars($org['www_address'])?>
                    </div>
                    <div style="color: #666; font-size: 14px;">
                        <span style="font-weight: 600;">Email:</span> <?=htmlspecialchars($org['email_address'])?>
                    </div>
                </div>
            </div>
        </div>
        <div class="dropdown-content">
            
            <div class="sub-section">
                <?php if(!empty($wps[$org_id])): 
                    $wpCounter = 1;
                    foreach($wps[$org_id] as $wp): ?>
                    <div class="wp-toggle-container" id="wp-toggle-container-<?=$wp['unic_id']?>" style="margin-bottom: 18px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="label" style="user-select:none; cursor: pointer; padding: 2px 4px; border-radius: 4px; transition: background 0.2s;"
                                    onclick="toggleWpDropdown(<?=$wp['unic_id']?>, event)"
                                    onmouseover="this.style.background='#f5faff'"
                                    onmouseout="this.style.background='transparent'"><?=$wpCounter?>. 🏢 <span style="text-decoration:underline;"><?=htmlspecialchars($wp['name_of_the_place'])?></span></span> --
                                <span style="cursor: pointer; color: #007bff; text-decoration: underline; font-size: 12px;"
                                    onclick="modifyWorkingPoint(<?=intval($wp['unic_id'])?>, <?=htmlspecialchars(json_encode($wp['name_of_the_place']))?>); event.stopPropagation();"
                                    onmouseover="this.style.background='#e6f2ff'; this.style.color='#0056b3';"
                                    onmouseout="this.style.background='transparent'; this.style.color='#007bff';"
                                    style="cursor: pointer; color: #007bff; text-decoration: underline; border-radius: 4px; transition: background 0.2s, color 0.2s; padding: 2px 4px; font-size: 12px;"><?=htmlspecialchars($wp['address'])?> <?=htmlspecialchars($wp['country'] ?? 'N/A')?></span>
                                <span style="font-size: 12px; color: #333;"> / <?=htmlspecialchars($wp['email'])?> / <strong>Language:</strong> <?=htmlspecialchars($wp['language'] ?: 'N/A')?></span>
                            </div>
                            <a href="../booking_supervisor_view.php?working_point_user_id=<?=intval($wp['unic_id'])?>" target="_blank" style="color: #28a745; text-decoration: none; font-size: 12px; font-weight: 600; padding: 4px 8px; border-radius: 4px; background: #e8f5e9; border: 1px solid #28a745; margin-left: auto;"
                               onclick="event.stopPropagation();"
                               onmouseover="this.style.background='#c8e6c9'; this.style.textDecoration='underline';"
                               onmouseout="this.style.background='#e8f5e9'; this.style.textDecoration='none';"
                               title="View as Supervisor">
                                📋 Supervisor View
                            </a>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <div style="background: #e3f2fd; color: #1565c0; border-radius: 4px; padding: 4px 12px; font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.2s; border: 1px solid #bbdefb; position: relative;" onclick="editTelnyxPhone(<?=intval($wp['unic_id'])?>, <?=htmlspecialchars(json_encode($wp['booking_phone_nr'] ?? ''))?>); event.stopPropagation();" onmouseover="this.style.background='#bbdefb'; document.getElementById('tooltip-booking-<?=$wp['unic_id']?>').style.display='block';" onmouseout="this.style.background='#e3f2fd'; document.getElementById('tooltip-booking-<?=$wp['unic_id']?>').style.display='none';">
                                    <?php if (!empty($wp['booking_phone_nr'])): ?>
                                        <?=htmlspecialchars($wp['booking_phone_nr'])?>
                                    <?php else: ?>
                                        <span style="font-weight: 500;">Booking Ph.Nr</span>
                                    <?php endif; ?>
                                    <div id="tooltip-booking-<?=$wp['unic_id']?>" style="display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 6px 10px; border-radius: 4px; font-size: 11px; white-space: nowrap; margin-bottom: 5px; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                        <b>Phone Booking Number</b> <i>(click to edit)</i>
                                        <div style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 5px solid #333;"></div>
                                    </div>
                                </div>
                                <div style="background: #ffe0b2; color: #e65100; border-radius: 4px; padding: 4px 12px; font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.2s; border: 1px solid #ffcc80; position: relative;" onclick="editTelnyxPhone(<?=intval($wp['unic_id'])?>, <?=htmlspecialchars(json_encode($wp['booking_phone_nr'] ?? ''))?>); event.stopPropagation();" onmouseover="this.style.background='#ffcc80'; document.getElementById('tooltip-sms-<?=$wp['unic_id']?>').style.display='block';" onmouseout="this.style.background='#ffe0b2'; document.getElementById('tooltip-sms-<?=$wp['unic_id']?>').style.display='none';">
                                    <?php if (!empty($wp['booking_sms_number'])): ?>
                                        <?=htmlspecialchars($wp['booking_sms_number'])?>
                                    <?php else: ?>
                                        <span style="font-weight: 500;">SMS Ph.Nr</span>
                                    <?php endif; ?>
                                    <div id="tooltip-sms-<?=$wp['unic_id']?>" style="display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 6px 10px; border-radius: 4px; font-size: 11px; white-space: nowrap; margin-bottom: 5px; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                        <b>SMS Phone Number</b> <i>(click to edit)</i>
                                        <div style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 5px solid #333;"></div>
                                    </div>
                                </div>
                                <div style="background: #fff9c4; color: #f57f17; border-radius: 4px; padding: 4px 12px; font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.2s; border: 1px solid #fff176;" onclick="openDroidConfigModal(<?=intval($wp['unic_id'])?>, <?=htmlspecialchars(json_encode($wp['name_of_the_place']))?>); event.stopPropagation();" onmouseover="this.style.background='#fff176'" onmouseout="this.style.background='#fff9c4'">
                                    🤖 Droid.Config
                                </div>
                            </div>
                            <div onclick="toggleWpDropdown(<?=$wp['unic_id']?>, event)" style="cursor:pointer; padding: 4px 0 4px 0; border-radius: 4px; transition: background 0.2s; min-height: 18px; margin-left: auto;" onmouseover="this.style.background='#f5faff'" onmouseout="this.style.background='transparent'">
                                <small>Lead Person: <?=htmlspecialchars($wp['lead_person_name'])?> (<?=htmlspecialchars($wp['lead_person_phone_nr'])?>) | Phone: <?=htmlspecialchars($wp['workplace_phone_nr'])?></small>
                            </div>
                        </div>
                        <div class="wp-specialists-dropdown" id="wp-specialists-dropdown-<?=$wp['unic_id']?>" style="display:none;">
                        <div class="wp-card">
                            <div class="sub-section">
                                <?php
                                $hasSpecialists = false;
                                if (!empty($specs[$org_id])):
                                    foreach ($specs[$org_id] as $sp):
                                        // Check if specialist has any non-zero working program entry for this working point
                                        $hasWorkingProgram = false;
                                        if (!empty($wpgr[$sp['unic_id']])):
                                            foreach ($wpgr[$sp['unic_id']] as $pr):
                                                if ($pr['working_place_id'] == $wp['unic_id'] && $hasNonZeroShift($pr)) {
                                                    $hasWorkingProgram = true;
                                                    break;
                                                }
                                            endforeach;
                                        endif;
                                        
                                        if (!$hasWorkingProgram) continue;
                                        
                                        if (!$hasSpecialists) {
                                            $hasSpecialists = true;
                                        }   ?>
                                        
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <a href="#" class="specialist-name-link" onclick="event.preventDefault(); modifySpecialist(<?=intval($sp['unic_id'])?>, <?=htmlspecialchars(json_encode($sp['name']))?>); event.stopPropagation();" title="Modify specialist details" onmouseover="this.style.backgroundColor='#ffffff'; this.style.color='#333';" onmouseout="this.style.backgroundColor='transparent'; this.style.color='inherit';">
                                                <span class="section-title mt-2" style="color: #808080;"><span style="color: #808080;">👥</span> Specialist - <?= htmlspecialchars($sp['name']) ?> ( <i><?= htmlspecialchars($sp['speciality']) ?></i> )</span>
                                            </a>
                                            <a href="../booking_specialist_view.php?specialist_id=<?=intval($sp['unic_id'])?>" target="_blank" style="color: #17a2b8; text-decoration: none; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 4px; background: #d1ecf1; border: 1px solid #17a2b8; margin-left: 10px;"
                                               onclick="event.stopPropagation();"
                                               onmouseover="this.style.background='#bee5eb'; this.style.textDecoration='underline';"
                                               onmouseout="this.style.background='#d1ecf1'; this.style.textDecoration='none';"
                                               title="View as Specialist">
                                                👤 Specialist View
                                            </a>
                                        </div>
                                        </small><br>

                                        <div class="indent">
                                            <div class="mb-2">
                                                <small><?= htmlspecialchars($sp['email']) ?> | Phone: <?= htmlspecialchars($sp['phone_nr']) ?> | Login: <?= htmlspecialchars($sp['user']) ?> / <?= htmlspecialchars($sp['password']) ?></small>
                                                <br><br>
                                                
                                                <table class="table table-sm table-bordered mt-2" style="font-size: 13px; cursor: pointer;" onclick="showComprehensiveScheduleEditor(<?=intval($wp['unic_id'])?>, <?=intval($sp['unic_id'])?>, <?=htmlspecialchars(json_encode($wp['name_of_the_place']))?>)">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="font-size: 13px;"></th>
                                                            <th style="font-size: 13px;">Monday</th>
                                                            <th style="font-size: 13px;">Tuesday</th>
                                                            <th style="font-size: 13px;">Wednesday</th>
                                                            <th style="font-size: 13px;">Thursday</th>
                                                            <th style="font-size: 13px;">Friday</th>
                                                            <th style="font-size: 13px;">Saturday</th>
                                                            <th style="font-size: 13px;">Sunday</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $workingProgramsForThisWp = [];
                                                        if(!empty($wpgr[$sp['unic_id']])): 
                                                            foreach ($wpgr[$sp['unic_id']] as $pr):
                                                                if ($pr['working_place_id'] == $wp['unic_id'] && $hasNonZeroShift($pr)) {
                                                                    $workingProgramsForThisWp[] = $pr;
                                                                }
                                                            endforeach;
                                                        endif;
                                                        
                                                        // Create a structured array for easy access
                                                        $scheduleData = [];
                                                        $daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                                        
                                                        foreach ($daysOrder as $day) {
                                                            $scheduleData[$day] = [
                                                                'shift1' => ['start' => '--:--', 'end' => '--:--'],
                                                                'shift2' => ['start' => '--:--', 'end' => '--:--'],
                                                                'shift3' => ['start' => '--:--', 'end' => '--:--']
                                                            ];
                                                        }
                                                        
                                                        // Populate with actual data
                                                        if(!empty($workingProgramsForThisWp)): 
                                                            foreach ($workingProgramsForThisWp as $pr):
                                                                $day = $pr['day_of_week'];
                                                                if (isset($scheduleData[$day])) {
                                                                    $scheduleData[$day]['shift1'] = [
                                                                        'start' => shortTime($pr['shift1_start']),
                                                                        'end' => shortTime($pr['shift1_end'])
                                                                    ];
                                                                    $scheduleData[$day]['shift2'] = [
                                                                        'start' => shortTime($pr['shift2_start']),
                                                                        'end' => shortTime($pr['shift2_end'])
                                                                    ];
                                                                    $scheduleData[$day]['shift3'] = [
                                                                        'start' => shortTime($pr['shift3_start']),
                                                                        'end' => shortTime($pr['shift3_end'])
                                                                    ];
                                                                }
                                                            endforeach;
                                                        endif;
                                                        
                                                        // Check which shifts have at least one scheduled time
                                                        $activeShifts = [];
                                                        for ($shift = 1; $shift <= 3; $shift++) {
                                                            $shiftKey = 'shift' . $shift;
                                                            $hasScheduledTime = false;
                                                            
                                                            foreach ($daysOrder as $day) {
                                                                $shiftData = $scheduleData[$day][$shiftKey];
                                                                if ($shiftData['start'] !== '--:--' && $shiftData['start'] !== '00:00') {
                                                                    $hasScheduledTime = true;
                                                                    break;
                                                                }
                                                            }
                                                            
                                                            if ($hasScheduledTime) {
                                                                $activeShifts[] = $shift;
                                                            }
                                                        }
                                                        
                                                        // Display only active shifts as rows
                                                        if (!empty($activeShifts)):
                                                            foreach ($activeShifts as $shift):
                                                                $shiftKey = 'shift' . $shift;
                                                        ?>
                                                            <tr>
                                                                <td style="font-size: 13px;"><strong>Shift <?=$shift?></strong></td>
                                                                <?php foreach ($daysOrder as $day): ?>
                                                                    <td style="font-size: 13px;">
                                                                        <?php 
                                                                        $shiftData = $scheduleData[$day][$shiftKey];
                                                                        $startTime = $shiftData['start'];
                                                                        $endTime = $shiftData['end'];
                                                                        
                                                                        // Show "--:--" for empty times
                                                                        if ($startTime === '00:00' || $startTime === '--:--') {
                                                                            $startTime = '--:--';
                                                                        }
                                                                        if ($endTime === '00:00' || $endTime === '--:--') {
                                                                            $endTime = '--:--';
                                                                        }
                                                                        
                                                                        echo $startTime . '–' . $endTime;
                                                                        ?>
                                                                    </td>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        <?php 
                                                            endforeach;
                                                        endif;
                                                        ?>
                                                        
                                                        <?php if(empty($workingProgramsForThisWp)): ?>
                                                            <tr>
                                                                <td colspan="8" style="font-size: 13px;">No program set</td>
                                                                </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                                </small>
                                            </div>
                                        </div>
                                        <br>
                                        <br>
                                <?php 
                                    endforeach;
                                endif;
                                
                                if (!$hasSpecialists): ?>
                                    <div>No specialists assigned.</div>
                                <?php endif; ?>
                                
                                <!-- Add New Specialist Button -->
                                <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #c0c0c0;">
                                    <button type="button" class="btn btn-success" 
                                            style="font-size: 0.9rem; padding: 8px 16px; text-decoration: none; background: #c0c0c0; color: #000; border: 1px solid #c0c0c0;"
                                            onclick="openAddSpecialistModal(<?=intval($wp['unic_id'])?>, <?=intval($org_id)?>)">
                                        <i class="fas fa-plus-circle"></i> Add New Specialist
                                    </button>
                                    <div style="margin-top: 5px; font-size: 0.8rem; color: #666;">
                                        <i class="fas fa-info-circle"></i> Add specialist and schedule for this workpoint
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                    
                    </br>  </br>
                    </div>
                <?php 
                    $wpCounter++;
                    endforeach; else: ?>
                    <div>No working points.</div>
                <?php endif; ?>
                
                <!-- Orphaned Specialists Section -->
                <?php
                // Find orphaned specialists (no non-zero shifts anywhere in the organisation)
                $orphanedSpecialists = [];
                if (!empty($specs[$org_id])):
                    foreach ($specs[$org_id] as $sp):
                        $hasAnyActiveProgram = false;
                        if (!empty($wpgr[$sp['unic_id']])):
                            foreach ($wpgr[$sp['unic_id']] as $pr):
                                if ($hasNonZeroShift($pr)) {
                                    $hasAnyActiveProgram = true;
                                    break;
                                }
                            endforeach;
                        endif;
                        
                        if (!$hasAnyActiveProgram) {
                            $orphanedSpecialists[] = $sp;
                        }
                    endforeach;
                endif;
                
                if (!empty($orphanedSpecialists)): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
                        <h4 style="margin: 0 0 15px 0; color: #856404; font-size: 16px;">
                            <span style="color: #856404;">⚠️</span> Orphaned Specialists (<?= count($orphanedSpecialists) ?>)
                        </h4>
                        <div style="font-size: 13px; color: #856404; margin-bottom: 15px;">
                            These specialists belong to this organisation but are not assigned to any working point.
                        </div>
                        
                        <?php foreach ($orphanedSpecialists as $sp): ?>
                            <div style="margin-bottom: 15px; padding: 12px; background: #fff; border: 1px solid #ffeaa7; border-radius: 4px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div style="flex: 1;">
                                        <span style="color: #808080;">👥</span>
                                        <span style="font-weight: 600; color: #333;"><?= htmlspecialchars($sp['name']) ?></span>
                                        <span style="color: #666; font-style: italic;">(<?= htmlspecialchars($sp['speciality']) ?>)</span>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <button type="button" 
                                                style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; border-radius: 4px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: background 0.2s;"
                                                onclick="assignOrphanedSpecialist(<?php echo intval($sp['unic_id']); ?>, '<?php echo htmlspecialchars($sp['name']); ?>', <?php echo intval($org_id); ?>);"
                                                onmouseover="this.style.background='#ffeaa7'" 
                                                onmouseout="this.style.background='#fff3cd'">
                                            📅 Assign Schedule
                                        </button>
                                        <button type="button" 
                                                style="background: #dc3545; color: #fff; border: 1px solid #dc3545; border-radius: 4px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: background 0.2s;"
                                                onclick="deleteOrphanedSpecialist(<?=intval($sp['unic_id'])?>, <?=htmlspecialchars(json_encode($sp['name']))?>);"
                                                onmouseover="this.style.background='#c82333'" 
                                                onmouseout="this.style.background='#dc3545'">
                                            🗑️ Delete
                                        </button>
                                    </div>
                                </div>
                                <div style="font-size: 12px; color: #666;">
                                    <span style="font-weight: 600;">Email:</span> <?= htmlspecialchars($sp['email']) ?> | 
                                    <span style="font-weight: 600;">Phone:</span> <?= htmlspecialchars($sp['phone_nr']) ?> | 
                                    <span style="font-weight: 600;">Login:</span> <?= htmlspecialchars($sp['user']) ?> / <?= htmlspecialchars($sp['password']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Add New Working Point Button -->
                <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #000;">
                    <button type="button" class="btn btn-primary" 
                            style="font-size: 0.9rem; padding: 8px 16px; text-decoration: none; background: #000; color: #fff; border: 1px solid #000;"
                            onclick="openAddWorkingPointModal(<?=intval($org_id)?>)">
                        <i class="fas fa-plus-circle"></i> Add New Working Point
                    </button>
                    <div style="margin-top: 5px; font-size: 0.8rem; color: #666;">
                        <i class="fas fa-info-circle"></i> Add new working point for this organisation
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
	endforeach;
	
	exit;
}
?>

<?php
if ($_GET['action'] === 'admin_logs') { include 'admin_logs.php'; } 
elseif ($_GET['action']=== 'webhook_dashboard') { include 'webhook_dashboard.php';} 
elseif ($_GET['action'] === 'csv_files' || $_GET['action'] === 'csv') {  include 'import_organisation_csv.php'; }
elseif ($_GET['action'] === 'google_calendar') { include 'google_calendar_management.php'; }
elseif ($_GET['action'] === 'php_workers') { include 'php_workers_simple.php'; }
elseif ($_GET['action'] === 'server_tools') { include 'server_tools.php'; }
?>


