// Specialist Permissions Module
// Handles specialist permissions toggles with instant AJAX updates

(function(window) {
    'use strict';

    /**
     * Toggle a specialist permission
     * @param {string} specialistId - The specialist ID
     * @param {string} permissionField - The permission field name
     * @param {boolean} enabled - Whether the permission is enabled
     */
    function togglePermission(specialistId, permissionField, enabled) {
        console.log('Toggling permission:', specialistId, permissionField, enabled);

        const formData = new FormData();
        formData.append('specialist_id', specialistId);
        formData.append('permission_field', permissionField);
        formData.append('permission_value', enabled ? 1 : 0);

        // Map permission fields to checkbox IDs
        const checkboxId = getCheckboxId(permissionField, specialistId);

        const checkbox = document.getElementById(checkboxId);
        if (!checkbox) {
            console.error('Checkbox not found for ID:', checkboxId);
            alert('Error: Checkbox element not found');
            return;
        }

        const originalChecked = checkbox.checked;
        checkbox.disabled = true;

        fetch('admin/update_specialist_permissions_enhanced.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => {
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            if (data.success) {
                console.log('Permission updated successfully');
                checkbox.disabled = false;
            } else {
                console.error('Permission update failed:', data.message);
                checkbox.checked = originalChecked;
                checkbox.disabled = false;
                alert('Error: ' + (data.message || 'Failed to update permission settings'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            checkbox.checked = originalChecked;
            checkbox.disabled = false;
            alert('Error updating permission settings: ' + error.message);
        });
    }

    /**
     * Get checkbox ID for a permission field
     * @param {string} permissionField - The permission field name
     * @param {string} specialistId - The specialist ID
     * @returns {string} The checkbox ID
     */
    function getCheckboxId(permissionField, specialistId) {
        const idMap = {
            'specialist_can_delete_booking': 'can_delete_booking_',
            'specialist_can_modify_booking': 'can_modify_booking_',
            'specialist_can_add_services': 'can_add_services_',
            'specialist_can_modify_services': 'can_modify_services_',
            'specialist_can_delete_services': 'can_delete_services_',
            'specialist_nr_visible_to_client': 'nr_visible_',
            'specialist_email_visible_to_client': 'email_visible_'
        };

        const prefix = idMap[permissionField] || permissionField + '_';
        return prefix + specialistId;
    }

    // Export the function to window
    window.togglePermission = togglePermission;

    console.log('Specialist Permissions Module loaded successfully');

})(window);
