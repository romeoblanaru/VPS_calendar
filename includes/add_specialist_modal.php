<div id="addSpecialistModal" class="modify-modal-overlay">
    <div class="modify-modal">
        <div class="modify-modal-header">
            <h3>👨‍⚕️ ADD/REACTIVATE SPECIALIST</h3>
            <span class="modify-modal-close" onclick="closeAddSpecialistModal()">&times;</span>
        </div>
        <div class="modify-modal-body">
            <div class="org-name-row">
                <span class="modify-icon-inline">👨‍⚕️</span>
                <div class="org-name-large">Specialist Selection</div>
            </div>
            <form id="addSpecialistForm">
                <input type="hidden" id="workpointId">
                <input type="hidden" id="organisationId">
                <!-- Specialist Selection -->
                <div class="form-group">
                    <label for="specialistSelection">Select Specialist *</label>
                    <select class="form-control" id="specialistSelection" required onchange="handleSpecialistSelection()">
                        <option value="new" selected style="color: #dc3545; font-weight: bold;">👨‍⚕️ New Specialist Registration</option>
                        <option value="" disabled>─────────── Existing Specialists ───────────</option>
                    </select>
                </div>
                <!-- Specialist Details -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="specialistName">Full Name *</label>
                        <input type="text" class="form-control" id="specialistName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="specialistSpeciality">Speciality *</label>
                        <input type="text" class="form-control" id="specialistSpeciality" name="speciality" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="specialistEmail">Email *</label>
                        <input type="email" class="form-control" id="specialistEmail" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="specialistPhone">Phone Number</label>
                        <input type="text" class="form-control" id="specialistPhone" name="phone_nr">
                    </div>
                </div>

                <!-- Compact row for username/password and email schedule -->
                <div class="compact-row">
                    <div class="left-column">
                        <div class="form-group">
                            <label for="emailScheduleHour">Send Email at Hour *</label>
                            <input type="number" class="form-control" id="emailScheduleHour" name="h_of_email_schedule" min="0" max="23" value="9" required>
                        </div>
                        <div class="form-group">
                            <label for="emailScheduleMinute">Minutes *</label>
                            <input type="number" class="form-control" id="emailScheduleMinute" name="m_of_email_schedule" min="0" max="59" value="0" required>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="form-group">
                            <label for="specialistUser">Username *</label>
                            <input type="text" class="form-control login-field" id="specialistUser" name="user" required>
                        </div>
                        <div class="form-group">
                            <label for="specialistPassword">Password *</label>
                            <input type="text" class="form-control login-field" id="specialistPassword" name="password" required>
                        </div>
                    </div>
                </div>

                <!-- Working Point Assignment -->
                <div class="form-group">
                    <label id="workpointLabel">Assign to Working Point *</label>
                    <select class="form-control" id="workpointSelect" required>
                        <option value="">Loading working points...</option>
                    </select>
                </div>
                <!-- Individual Day Editor -->
                <div class="individual-edit-section">
                    <h4 id="workingScheduleTitle">📋 Working Schedule</h4>
                    <div class="schedule-editor-table-container">
                        <table class="schedule-editor-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th colspan="3">Shift 1</th>
                                    <th colspan="3">Shift 2</th>
                                    <th colspan="3">Shift 3</th>
                                </tr>
                                <tr>
                                    <th></th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th></th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th></th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="scheduleEditorTableBody">
                                <!-- Days will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Quick Options Section -->
                <div class="individual-edit-section">
                    <h4 style="font-size: 11px; margin-bottom: 10px;">⚡ Quick Options</h4>
                    <div class="schedule-editor-table-container">
                        <div class="quick-options-compact">
                            <div class="quick-options-row">
                                <div class="quick-option-group">
                                    <select id="quickOptionsDaySelect" class="form-control" style="font-size: 11px; width: 66px;">
                                        <option value="mondayToFriday">Mon-Fri</option>
                                        <option value="saturday">Saturday</option>
                                        <option value="sunday">Sunday</option>
                                    </select>
                                </div>
                                <div class="quick-option-group">
                                    <label style="font-size: 11px; margin-right: 2px; text-align: right; min-width: 50px; display: inline-block;">Shift 1:</label>
                                    <div class="time-inputs">
                                        <input type="time" id="shift1Start" class="form-control" placeholder="S">
                                        <input type="time" id="shift1End" class="form-control" placeholder="E">
                                    </div>
                                </div>
                                <div class="quick-option-group">
                                    <label style="font-size: 11px; margin-right: 2px; text-align: right; min-width: 50px; display: inline-block;">Shift 2:</label>
                                    <div class="time-inputs">
                                        <input type="time" id="shift2Start" class="form-control" placeholder="S">
                                        <input type="time" id="shift2End" class="form-control" placeholder="E">
                                    </div>
                                </div>
                                <div class="quick-option-group">
                                    <label style="font-size: 11px; margin-right: 2px; text-align: right; min-width: 50px; display: inline-block;">Shift 3:</label>
                                    <div class="time-inputs">
                                        <input type="time" id="shift3Start" class="form-control" placeholder="S">
                                        <input type="time" id="shift3End" class="form-control" placeholder="E">
                                    </div>
                                </div>
                                <div class="quick-option-group">
                                    <button type="button" onclick="applyAllShifts()" style="background: #007bff; color: white; border: none; padding: 4px 12px; border-radius: 4px; font-size: 11px; cursor: pointer;">Apply</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="addSpecialistError" class="modify-error" style="display: none;"></div>
                <div class="modify-modal-buttons">
                    <button type="button" class="btn-modify" onclick="submitAddSpecialist()">Add Specialist</button>
                </div>
            </form>
        </div>
    </div>
</div> 