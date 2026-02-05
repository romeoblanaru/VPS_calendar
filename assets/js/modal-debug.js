// Modal Debug Helper
// This helps diagnose modal loading issues

(function(window) {
    'use strict';

    window.ModalDebug = {
        // Check if a modal exists in DOM
        checkModal: function(modalId) {
            const modal = document.getElementById(modalId);
            console.log(`Modal '${modalId}' exists:`, !!modal);
            if (modal) {
                console.log(`Modal classes:`, modal.className);
                console.log(`Modal display:`, window.getComputedStyle(modal).display);
                console.log(`Modal visibility:`, window.getComputedStyle(modal).visibility);
                console.log(`Modal has Bootstrap instance:`, !!bootstrap.Modal.getInstance(modal));
            }
            return !!modal;
        },

        // Check all Bootstrap modals in page
        checkAllModals: function() {
            const modals = document.querySelectorAll('.modal');
            console.log(`Found ${modals.length} modal(s) in DOM:`);
            modals.forEach((modal, index) => {
                console.log(`  ${index + 1}. ID: ${modal.id || '(no id)'}, Classes: ${modal.className}`);
            });
        },

        // Check if Bootstrap backdrop exists
        checkBackdrop: function() {
            const backdrop = document.querySelector('.modal-backdrop');
            console.log('Modal backdrop exists:', !!backdrop);
            if (backdrop) {
                console.log('Backdrop classes:', backdrop.className);
                console.log('Backdrop z-index:', window.getComputedStyle(backdrop).zIndex);
            }
            return !!backdrop;
        },

        // Force remove stuck backdrop
        removeStuckBackdrop: function() {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => {
                console.log('Removing stuck backdrop...');
                backdrop.remove();
            });
            // Also remove the modal-open class from body
            document.body.classList.remove('modal-open');
            console.log(`Removed ${backdrops.length} backdrop(s)`);
        },

        // Force close all Bootstrap modals
        closeAllModals: function() {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    console.log(`Closing modal: ${modal.id}`);
                    bsModal.hide();
                }
            });
        },

        // Diagnose modal state
        diagnose: function() {
            console.log('=== Modal Diagnosis ===');
            console.log('Body classes:', document.body.className);
            console.log('Body style overflow:', document.body.style.overflow);

            this.checkAllModals();
            this.checkBackdrop();

            console.log('Modal container exists:', !!document.getElementById('modal-container'));

            // Check ModalLoader status
            if (window.ModalLoader) {
                console.log('ModalLoader status:', window.ModalLoader.getStatus());
            }

            console.log('======================');
        }
    };

    // Auto-diagnose if stuck backdrop detected
    window.addEventListener('keydown', function(e) {
        // Press Ctrl+Shift+M to diagnose modals
        if (e.ctrlKey && e.shiftKey && e.key === 'M') {
            console.log('Manual modal diagnosis triggered');
            window.ModalDebug.diagnose();
        }

        // Press Escape twice quickly to force remove stuck backdrop
        if (e.key === 'Escape') {
            if (window.lastEscapeTime && (Date.now() - window.lastEscapeTime < 500)) {
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop && !document.querySelector('.modal.show')) {
                    console.log('Stuck backdrop detected, removing...');
                    window.ModalDebug.removeStuckBackdrop();
                }
            }
            window.lastEscapeTime = Date.now();
        }
    });

    console.log('Modal Debug Helper loaded. Press Ctrl+Shift+M to diagnose, or double-tap Escape to remove stuck backdrop.');

})(window);