// Modal Loader Module
// Centralized lazy loading system for all modals in the booking system

(function(window) {
    'use strict';

    // Track loaded modal scripts and HTML
    const loadedScripts = new Set();
    const loadedHtml = new Set();
    const loadingPromises = new Map();

    // Modal configurations
    const modalConfigs = {
        'comprehensive-schedule-editor': {
            script: 'assets/js/modals/comprehensive-schedule-editor.js',
            html: 'assets/modals/comprehensive-schedule-editor-modal.php',
            functionName: 'openModifyScheduleModalReal'
        },
        'add-specialist': {
            script: 'assets/js/modals/add-specialist.js',
            html: null, // HTML included in script
            functionName: 'openAddSpecialistModal'
        },
        'modify-specialist': {
            script: 'assets/js/modals/modify-specialist.js',
            html: null, // HTML included in script
            functionName: 'openModifySpecialistModal'
        },
        'communication-setup': {
            script: 'assets/js/modals/communication-setup.js',
            html: 'assets/modals/communication-setup-modal.php',
            functionName: 'openCommunicationSetupReal'
        },
        'manage-services': {
            script: 'assets/js/modals/manage-services.js',
            html: 'assets/modals/manage-services-modal.php',
            functionName: 'openManageServicesReal'
        },
        'statistics': {
            script: 'assets/js/modals/statistics.js',
            html: 'assets/modals/statistics-modal.php',
            functionName: 'openStatisticsReal'
        },
        'timeoff': {
            script: 'assets/js/modals/timeoff.js',
            html: null, // HTML included in script
            functionName: 'openTimeOffModalReal'
        },
        'workpoint-holidays': {
            script: 'assets/js/modals/workpoint-holidays.js',
            html: null, // HTML included in script
            functionName: 'openWorkpointHolidaysReal'
        },
        'sms-templates': {
            script: 'assets/js/modals/sms-templates.js',
            html: null, // HTML included in script
            functionName: 'manageSMSTemplate'
        }
    };

    /**
     * Load a script file dynamically
     * @param {string} src - Script source path
     * @returns {Promise} Promise that resolves when script is loaded
     */
    function loadScript(src) {
        // Check if already loaded
        if (loadedScripts.has(src)) {
            return Promise.resolve();
        }

        // Check if currently loading
        const loadingKey = 'script:' + src;
        if (loadingPromises.has(loadingKey)) {
            return loadingPromises.get(loadingKey);
        }

        // Create loading promise
        const promise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src + '?v=' + Date.now();

            script.onload = () => {
                loadedScripts.add(src);
                loadingPromises.delete(loadingKey);
                resolve();
            };

            script.onerror = () => {
                loadingPromises.delete(loadingKey);
                console.error(`Failed to load script: ${src}`);
                reject(new Error(`Failed to load script: ${src}`));
            };

            document.head.appendChild(script);
        });

        loadingPromises.set(loadingKey, promise);
        return promise;
    }

    /**
     * Load HTML content for a modal
     * @param {string} url - HTML file URL
     * @returns {Promise<string>} Promise that resolves with HTML content
     */
    function loadHtml(url) {
        // Check if already loaded
        if (loadedHtml.has(url)) {
            return Promise.resolve();
        }

        // Check if currently loading
        const loadingKey = 'html:' + url;
        if (loadingPromises.has(loadingKey)) {
            return loadingPromises.get(loadingKey);
        }

        // Create loading promise
        const promise = fetch(url + '?v=' + Date.now())
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(html => {
                // Inject HTML into a container if not already present
                if (!document.getElementById('modal-container')) {
                    const container = document.createElement('div');
                    container.id = 'modal-container';
                    container.style.display = 'none';
                    document.body.appendChild(container);
                }

                const container = document.getElementById('modal-container');
                container.insertAdjacentHTML('beforeend', html);

                // Wait for the DOM to process the HTML before marking as loaded
                return new Promise((resolve) => {
                    // Use requestAnimationFrame to ensure the HTML is rendered
                    requestAnimationFrame(() => {
                        loadedHtml.add(url);
                        loadingPromises.delete(loadingKey);
                        resolve(html);
                    });
                });
            })
            .catch(error => {
                loadingPromises.delete(loadingKey);
                console.error(`Failed to load HTML: ${url}`, error);
                throw error;
            });

        loadingPromises.set(loadingKey, promise);
        return promise;
    }

    /**
     * Load a modal (both HTML and JavaScript)
     * @param {string} modalName - Name of the modal to load
     * @returns {Promise} Promise that resolves when modal is fully loaded
     */
    function loadModal(modalName) {
        const config = modalConfigs[modalName];

        if (!config) {
            console.error(`Unknown modal: ${modalName}`);
            return Promise.reject(new Error(`Unknown modal: ${modalName}`));
        }

        const promises = [];

        // Load HTML if needed
        if (config.html) {
            promises.push(loadHtml(config.html));
        }

        // Load script
        promises.push(loadScript(config.script));

        return Promise.all(promises);
    }

    /**
     * Open a modal with lazy loading
     * @param {string} modalName - Name of the modal
     * @param {...any} args - Arguments to pass to the modal function
     * @returns {Promise} Promise that resolves when modal is opened
     */
    function openModal(modalName, ...args) {
        const config = modalConfigs[modalName];

        if (!config) {
            console.error(`Unknown modal: ${modalName}`);
            return Promise.reject(new Error(`Unknown modal: ${modalName}`));
        }

        // Check if already loaded
        if (loadedScripts.has(config.script)) {
            // Call the function directly
            const modalFunction = window[config.functionName];
            if (typeof modalFunction === 'function') {
                modalFunction(...args);
                return Promise.resolve();
            } else {
                console.error(`Modal function not found: ${config.functionName}`);
                return Promise.reject(new Error(`Modal function not found: ${config.functionName}`));
            }
        }

        // Load and then open
        return loadModal(modalName).then(() => {
            // Add a small delay to ensure DOM is fully ready
            return new Promise((resolve) => {
                setTimeout(() => {
                    const modalFunction = window[config.functionName];
                    if (typeof modalFunction === 'function') {
                        modalFunction(...args);
                        resolve();
                    } else {
                        console.error(`Modal function not found after loading: ${config.functionName}`);
                        throw new Error(`Modal function not found after loading: ${config.functionName}`);
                    }
                }, 50); // 50ms delay to ensure DOM is ready
            });
        });
    }

    /**
     * Preload a modal for faster opening later
     * @param {string} modalName - Name of the modal to preload
     * @returns {Promise} Promise that resolves when modal is preloaded
     */
    function preloadModal(modalName) {
        return loadModal(modalName);
    }

    /**
     * Preload multiple modals
     * @param {Array<string>} modalNames - Array of modal names to preload
     * @returns {Promise} Promise that resolves when all modals are preloaded
     */
    function preloadModals(modalNames) {
        return Promise.all(modalNames.map(name => preloadModal(name)));
    }

    /**
     * Check if a modal is loaded
     * @param {string} modalName - Name of the modal
     * @returns {boolean} True if modal is loaded
     */
    function isModalLoaded(modalName) {
        const config = modalConfigs[modalName];
        if (!config) return false;

        const scriptLoaded = loadedScripts.has(config.script);
        const htmlLoaded = !config.html || loadedHtml.has(config.html);

        return scriptLoaded && htmlLoaded;
    }

    /**
     * Get loading status for all modals
     * @returns {Object} Object with modal names as keys and loading status as values
     */
    function getLoadingStatus() {
        const status = {};
        for (const modalName in modalConfigs) {
            status[modalName] = isModalLoaded(modalName);
        }
        return status;
    }

    /**
     * Clear loading cache (useful for development)
     */
    function clearCache() {
        loadedScripts.clear();
        loadedHtml.clear();
        loadingPromises.clear();
    }

    // Export the modal loader API
    window.ModalLoader = {
        load: loadModal,
        open: openModal,
        preload: preloadModal,
        preloadMultiple: preloadModals,
        isLoaded: isModalLoaded,
        getStatus: getLoadingStatus,
        clearCache: clearCache,

        // Direct access to low-level functions if needed
        loadScript: loadScript,
        loadHtml: loadHtml,

        // Configuration access
        configs: modalConfigs
    };

})(window);