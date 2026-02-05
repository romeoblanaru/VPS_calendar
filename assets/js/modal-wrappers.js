// Modal Wrappers
// Simplified wrapper functions for all modals using the centralized modal loader

(function(window) {
    'use strict';

    // Comprehensive Schedule Editor Modal
    window.openModifyScheduleModal = function(specialistId, workpointId) {
        // Store values for use after modal loads
        window.modifyScheduleSpecialistId = specialistId;
        window.modifyScheduleWorkpointId = workpointId;

        ModalLoader.open('comprehensive-schedule-editor', specialistId, workpointId).catch(error => {
            console.error('Failed to open Schedule Editor:', error);
            alert('Failed to load Schedule Editor. Please try again.');
        });
    };

    // Add Specialist Modal
    window.openAddSpecialistModal = function(workpointId, organisationId) {
        ModalLoader.open('add-specialist', workpointId, organisationId).catch(error => {
            console.error('Failed to open Add Specialist modal:', error);
            alert('Failed to load Add Specialist functionality. Please try again.');
        });
    };

    // Modify Specialist Modal
    window.openModifySpecialistModal = function(specialistId, specialistName, workpointId) {
        ModalLoader.open('modify-specialist', specialistId, specialistName, workpointId).catch(error => {
            console.error('Failed to open Modify Specialist modal:', error);
            alert('Failed to load Modify Specialist functionality. Please try again.');
        });
    };

    // Communication Setup Modal
    window.openCommunicationSetup = function() {
        ModalLoader.open('communication-setup').catch(error => {
            console.error('Failed to open Communication Setup:', error);
            alert('Failed to load Communication Setup. Please try again.');
        });
    };

    // Manage Services Modal
    window.openManageServices = function() {
        ModalLoader.open('manage-services').catch(error => {
            console.error('Failed to open Manage Services:', error);
            alert('Failed to load Manage Services. Please try again.');
        });
    };

    // Statistics Modal
    window.openStatistics = function() {
        ModalLoader.open('statistics').catch(error => {
            console.error('Failed to open Statistics:', error);
            alert('Failed to load Statistics. Please try again.');
        });
    };

    // Time Off Modal
    window.openTimeOffModal = function() {
        // Set specialist ID immediately for autoSave functions
        const specialistIdEl = document.getElementById('modifyScheduleSpecialistId');
        if (specialistIdEl) {
            window.timeOffSpecialistId = specialistIdEl.value;
        }

        ModalLoader.open('timeoff').catch(error => {
            console.error('Failed to open Time Off modal:', error);
            alert('Failed to load Time Off functionality. Please try again.');
        });
    };

    // Workpoint Holidays Modal
    window.openWorkpointHolidays = function() {
        // Use the workpoint ID from window object or PHP
        if (!window.currentWorkpointId) {
            console.error('No workpoint ID available');
            alert('Unable to open Workpoint Holidays: No workpoint selected.');
            return;
        }

        ModalLoader.open('workpoint-holidays').catch(error => {
            console.error('Failed to open Workpoint Holidays:', error);
            alert('Failed to load Workpoint Holidays. Please try again.');
        });
    };

    // SMS Templates Modal
    window.openSMSConfirmationSetup = function() {
        console.log('Opening SMS Templates with workpoint ID:', window.currentWorkpointId);

        ModalLoader.open('sms-templates').catch(error => {
            console.error('Failed to open SMS Templates:', error);
            alert('Failed to load SMS Templates. Please try again.');
        });
    };

    // Helper function to ensure modal is loaded before calling its functions
    function ensureModalLoaded(modalName, functionName, ...args) {
        if (ModalLoader.isLoaded(modalName)) {
            const func = window[functionName];
            if (typeof func === 'function') {
                return func(...args);
            }
        } else {
            return ModalLoader.load(modalName).then(() => {
                const func = window[functionName];
                if (typeof func === 'function') {
                    return func(...args);
                }
            });
        }
    }

    // Stub functions for Add Specialist form
    window.submitAddSpecialist = function() {
        ensureModalLoaded('add-specialist', 'submitAddSpecialist');
    };

    window.handleSpecialistSelection = function() {
        ensureModalLoaded('add-specialist', 'handleSpecialistSelectionReal');
    };

    window.clearShift = function(button, shiftNum) {
        ensureModalLoaded('add-specialist', 'clearShift', button, shiftNum);
    };

    window.applyAllShifts = function() {
        ensureModalLoaded('add-specialist', 'applyAllShifts');
    };

    window.closeAddSpecialistModal = function() {
        if (ModalLoader.isLoaded('add-specialist')) {
            if (typeof window.closeAddSpecialistModal === 'function') {
                window.closeAddSpecialistModal();
            }
        } else {
            // If module not loaded, just hide the modal directly
            const modal = document.getElementById('addSpecialistModal');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            }
        }
    };

    // Stub functions for Modify Specialist form
    window.submitModifySpecialist = function() {
        ensureModalLoaded('modify-specialist', 'submitModifySpecialist');
    };

    window.deleteSpecialist = function() {
        ensureModalLoaded('modify-specialist', 'deleteSpecialist');
    };

    window.closeModifySpecialistModal = function() {
        if (ModalLoader.isLoaded('modify-specialist')) {
            if (typeof window.closeModifySpecialistModal === 'function') {
                window.closeModifySpecialistModal();
            }
        } else {
            // If module not loaded, just hide the modal directly
            const modal = document.getElementById('modifySpecialistModal');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            }
        }
    };

    // Function stub for onSelectUnassignedSpecialist
    window.onSelectUnassignedSpecialist = function(specialistId) {
        if (!specialistId) return;
        // Use the current workpoint ID from window object
        const currentWorkpointId = window.currentWorkpointId || 0;
        openModifyScheduleModal(specialistId, currentWorkpointId);
        // Reset selection so it can be re-used
        const sel = document.getElementById('unassignedSpecialistsSelect');
        if (sel) sel.value = '';
    };

    // Stub functions for Schedule Editor
    window.autoSaveSchedule = function() {
        ensureModalLoaded('comprehensive-schedule-editor', 'autoSaveSchedule');
    };

    window.applyQuickOption = function(option) {
        ensureModalLoaded('comprehensive-schedule-editor', 'applyQuickOption', option);
    };

    window.clearAllFields = function() {
        ensureModalLoaded('comprehensive-schedule-editor', 'clearAllFields');
    };

    window.clearSpecialistSchedule = function() {
        ensureModalLoaded('comprehensive-schedule-editor', 'clearSpecialistSchedule');
    };

    window.reloadScheduleData = function() {
        ensureModalLoaded('comprehensive-schedule-editor', 'reloadScheduleData');
    };

    window.closeScheduleModal = function() {
        ensureModalLoaded('comprehensive-schedule-editor', 'closeScheduleModal');
    };

    // Stub functions for Manage Services
    window.filterServices = function(category) {
        ensureModalLoaded('manage-services', 'filterServices', category);
    };

    window.searchServices = function() {
        ensureModalLoaded('manage-services', 'searchServices');
    };

    window.addService = function() {
        ensureModalLoaded('manage-services', 'addService');
    };

    window.editService = function(serviceId) {
        ensureModalLoaded('manage-services', 'editService', serviceId);
    };

    window.deleteService = function(serviceId) {
        ensureModalLoaded('manage-services', 'deleteService', serviceId);
    };

    window.saveService = function() {
        ensureModalLoaded('manage-services', 'saveService');
    };

    window.cancelEdit = function() {
        ensureModalLoaded('manage-services', 'cancelEdit');
    };

    // Stub functions for Statistics
    window.switchStatTab = function(tab) {
        ensureModalLoaded('statistics', 'switchStatTab', tab);
    };

    window.applyPeriodFilter = function() {
        ensureModalLoaded('statistics', 'applyPeriodFilter');
    };

    window.clearFilters = function() {
        ensureModalLoaded('statistics', 'clearFilters');
    };

    window.exportStatistics = function(format) {
        ensureModalLoaded('statistics', 'exportStatistics', format);
    };

    // Stub functions for Time Off
    window.addTimeOffRow = function() {
        ensureModalLoaded('timeoff', 'addTimeOffRow');
    };

    window.saveTimeOff = function() {
        ensureModalLoaded('timeoff', 'saveTimeOff');
    };

    window.autoSaveTimeOff = function() {
        ensureModalLoaded('timeoff', 'autoSaveTimeOff');
    };

    window.autoDeleteTimeOff = function(button, timeOffId) {
        ensureModalLoaded('timeoff', 'autoDeleteTimeOff', button, timeOffId);
    };

    window.closeTimeOffModal = function() {
        ensureModalLoaded('timeoff', 'closeTimeOffModal');
    };

    // Stub functions for Workpoint Holidays
    window.addHolidayRow = function() {
        ensureModalLoaded('workpoint-holidays', 'addHolidayRow');
    };

    window.saveHolidays = function() {
        ensureModalLoaded('workpoint-holidays', 'saveHolidays');
    };

    window.autoSaveHoliday = function(row) {
        ensureModalLoaded('workpoint-holidays', 'autoSaveHoliday', row);
    };

    window.autoDeleteHoliday = function(button, holidayId) {
        ensureModalLoaded('workpoint-holidays', 'autoDeleteHoliday', button, holidayId);
    };

    window.closeHolidaysModal = function() {
        ensureModalLoaded('workpoint-holidays', 'closeHolidaysModal');
    };

    // Stub functions for SMS Templates
    window.manageSMSTemplate = function(templateType) {
        ensureModalLoaded('sms-templates', 'manageSMSTemplate', templateType);
    };

    window.saveSMSTemplate = function(templateType) {
        ensureModalLoaded('sms-templates', 'saveSMSTemplate', templateType);
    };

    window.resetSMSTemplate = function(templateType) {
        ensureModalLoaded('sms-templates', 'resetSMSTemplate', templateType);
    };

    window.insertSMSVariable = function(variable, templateType) {
        ensureModalLoaded('sms-templates', 'insertSMSVariable', variable, templateType);
    };

    window.testSMSTemplate = function(templateType) {
        ensureModalLoaded('sms-templates', 'testSMSTemplate', templateType);
    };

    // Preload commonly used modals on page load (optional)
    document.addEventListener('DOMContentLoaded', function() {
        // Preload schedule editor if we're on the supervisor view
        if (window.location.pathname.includes('booking_supervisor_view')) {
            // Delay preloading to not interfere with initial page load
            setTimeout(() => {
                ModalLoader.preload('comprehensive-schedule-editor').catch(err => {
                    console.log('Failed to preload schedule editor:', err);
                });
            }, 3000);
        }
    });

})(window);