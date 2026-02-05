// Utilities Module
// Common utility functions used across the booking system

(function(window) {
    'use strict';

    // Track loaded modules to avoid duplicate loading
    const loadedModules = new Set();
    const loadingPromises = new Map();

    /**
     * Generic module loader for JavaScript files
     * @param {string} moduleName - Name of the module to load
     * @param {function} callback - Optional callback when module is loaded
     * @returns {Promise} Promise that resolves when module is loaded
     */
    function loadModule(moduleName, callback) {
        // Check if already loaded
        if (loadedModules.has(moduleName)) {
            if (callback) callback();
            return Promise.resolve();
        }

        // Check if currently loading
        if (loadingPromises.has(moduleName)) {
            return loadingPromises.get(moduleName).then(() => {
                if (callback) callback();
            });
        }

        // Create loading promise
        const promise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `assets/js/${moduleName}.js?v=${Date.now()}`;

            script.onload = () => {
                loadedModules.add(moduleName);
                loadingPromises.delete(moduleName);
                resolve();
                if (callback) callback();
            };

            script.onerror = () => {
                loadingPromises.delete(moduleName);
                console.error(`Failed to load module: ${moduleName}`);
                reject(new Error(`Failed to load module: ${moduleName}`));
            };

            document.head.appendChild(script);
        });

        loadingPromises.set(moduleName, promise);
        return promise;
    }

    /**
     * Load modal HTML content
     * @param {string} modalName - Name of the modal to load
     * @returns {Promise<string>} Promise that resolves with HTML content
     */
    function loadModalHtml(modalName) {
        return fetch(`assets/modals/${modalName}-modal.php`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .catch(error => {
                console.error(`Failed to load modal HTML: ${modalName}`, error);
                throw error;
            });
    }

    /**
     * AJAX POST wrapper
     * @param {string} endpoint - API endpoint
     * @param {Object|FormData} data - Data to send
     * @returns {Promise<Object>} Promise that resolves with JSON response
     */
    function ajaxPost(endpoint, data) {
        let formData;

        if (data instanceof FormData) {
            formData = data;
        } else {
            formData = new FormData();
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    formData.append(key, data[key]);
                }
            }
        }

        return fetch(endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error(`AJAX POST error for ${endpoint}:`, error);
            throw error;
        });
    }

    /**
     * AJAX GET wrapper
     * @param {string} url - URL to fetch
     * @returns {Promise<Object>} Promise that resolves with JSON response
     */
    function ajaxGet(url) {
        return fetch(url, {
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error(`AJAX GET error for ${url}:`, error);
            throw error;
        });
    }

    /**
     * Initialize Bootstrap tooltips in a container
     * @param {string|Element} container - Container selector or element
     * @param {number} delay - Delay before initialization (ms)
     */
    function initializeTooltips(container, delay = 50) {
        setTimeout(() => {
            let containerEl;

            if (typeof container === 'string') {
                containerEl = document.querySelector(container);
            } else {
                containerEl = container;
            }

            if (!containerEl) return;

            const tooltipTriggerList = containerEl.querySelectorAll('[data-bs-toggle="tooltip"]');
            [...tooltipTriggerList].map(tooltipTriggerEl => {
                // Dispose existing tooltip if any
                const existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }
                // Create new tooltip
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }, delay);
    }

    /**
     * Dispose Bootstrap tooltips in a container
     * @param {string|Element} container - Container selector or element
     */
    function disposeTooltips(container) {
        let containerEl;

        if (typeof container === 'string') {
            containerEl = document.querySelector(container);
        } else {
            containerEl = container;
        }

        if (!containerEl) return;

        const tooltipTriggerList = containerEl.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggerList].forEach(tooltipTriggerEl => {
            const tooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
            if (tooltip) {
                tooltip.dispose();
            }
        });
    }

    /**
     * Apply permission state to an element
     * @param {Element} element - Element to apply state to
     * @param {boolean} hasPermission - Whether user has permission
     * @param {string} tooltipText - Tooltip text for disabled state
     */
    function applyPermissionState(element, hasPermission, tooltipText) {
        if (!element) return;

        if (!hasPermission) {
            element.disabled = true;
            element.style.opacity = '0.5';
            element.style.cursor = 'not-allowed';

            if (tooltipText) {
                element.setAttribute('data-bs-toggle', 'tooltip');
                element.setAttribute('data-bs-placement', 'top');
                element.setAttribute('title', tooltipText);
                new bootstrap.Tooltip(element);
            }
        } else {
            element.disabled = false;
            element.style.opacity = '1';
            element.style.cursor = 'pointer';

            // Remove tooltip if exists
            const tooltip = bootstrap.Tooltip.getInstance(element);
            if (tooltip) {
                tooltip.dispose();
            }
        }
    }

    /**
     * Show loading indicator on a button
     * @param {Element} button - Button element
     * @param {string} loadingText - Text to show while loading
     * @returns {Object} Object with restore function
     */
    function showButtonLoading(button, loadingText = 'Loading...') {
        if (!button) return { restore: () => {} };

        const originalContent = button.innerHTML;
        const originalDisabled = button.disabled;

        button.disabled = true;
        button.innerHTML = `<i class="fas fa-spinner fa-spin" style="margin-right: 5px;"></i>${loadingText}`;

        return {
            restore: () => {
                button.innerHTML = originalContent;
                button.disabled = originalDisabled;
            }
        };
    }

    /**
     * Debounce function
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     * @returns {Function} Debounced function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Throttle function
     * @param {Function} func - Function to throttle
     * @param {number} limit - Time limit in milliseconds
     * @returns {Function} Throttled function
     */
    function throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Format date for display
     * @param {Date|string} date - Date to format
     * @param {Object} options - Formatting options
     * @returns {string} Formatted date string
     */
    function formatDate(date, options = {}) {
        const d = date instanceof Date ? date : new Date(date);

        const defaults = {
            includeTime: true,
            includeYear: true,
            shortMonth: true
        };

        const opts = Object.assign({}, defaults, options);

        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = opts.shortMonth ?
            ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] :
            ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        let result = '';

        if (opts.includeTime) {
            result += d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }) + ' ';
        }

        result += days[d.getDay()] + ' ';
        result += String(d.getDate()).padStart(2, '0') + '.';
        result += months[d.getMonth()];

        if (opts.includeYear) {
            const year = d.getFullYear();
            const currentYear = new Date().getFullYear();
            if (year !== currentYear || opts.forceYear) {
                result += ' ' + String(year).slice(-2);
            }
        }

        return result;
    }

    /**
     * Show notification message
     * @param {string} message - Message to show
     * @param {string} type - Type of notification (success, error, warning, info)
     * @param {number} duration - Duration in milliseconds (0 for permanent)
     */
    function showNotification(message, type = 'info', duration = 5000) {
        const container = document.getElementById('notification-container') || createNotificationContainer();

        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.setAttribute('role', 'alert');
        notification.style.marginBottom = '10px';

        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        notification.innerHTML = `
            <i class="fas ${icons[type] || icons.info}" style="margin-right: 10px;"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        container.appendChild(notification);

        if (duration > 0) {
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 150);
            }, duration);
        }

        return notification;
    }

    /**
     * Create notification container if it doesn't exist
     * @returns {Element} Notification container element
     */
    function createNotificationContainer() {
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            document.body.appendChild(container);
        }
        return container;
    }

    /**
     * Confirm action with modal dialog
     * @param {string} title - Dialog title
     * @param {string} message - Dialog message
     * @param {Function} onConfirm - Callback on confirmation
     * @param {Function} onCancel - Optional callback on cancel
     */
    function confirmAction(title, message, onConfirm, onCancel) {
        // Check if Bootstrap modal exists
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            // Fallback to native confirm
            if (confirm(`${title}\n\n${message}`)) {
                onConfirm();
            } else if (onCancel) {
                onCancel();
            }
            return;
        }

        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="utilityConfirmModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            ${message}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="utilityConfirmBtn">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existingModal = document.getElementById('utilityConfirmModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Initialize modal
        const modalElement = document.getElementById('utilityConfirmModal');
        const modal = new bootstrap.Modal(modalElement);

        // Handle confirm
        document.getElementById('utilityConfirmBtn').onclick = () => {
            modal.hide();
            if (onConfirm) onConfirm();
        };

        // Handle cancel
        modalElement.addEventListener('hidden.bs.modal', function() {
            modalElement.remove();
        });

        // Show modal
        modal.show();
    }

    /**
     * Get URL parameter value
     * @param {string} param - Parameter name
     * @returns {string|null} Parameter value or null if not found
     */
    function getUrlParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }

    /**
     * Update URL parameter without reload
     * @param {string} param - Parameter name
     * @param {string} value - Parameter value
     */
    function updateUrlParam(param, value) {
        const url = new URL(window.location);
        url.searchParams.set(param, value);
        window.history.pushState({}, '', url);
    }

    // Export utilities
    window.Utils = {
        loadModule,
        loadModalHtml,
        ajaxPost,
        ajaxGet,
        initializeTooltips,
        disposeTooltips,
        applyPermissionState,
        showButtonLoading,
        debounce,
        throttle,
        formatDate,
        showNotification,
        confirmAction,
        getUrlParam,
        updateUrlParam
    };

})(window);