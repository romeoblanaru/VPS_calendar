// Color Picker Modal Functions

window.openColorPickerModal = function(specialistId, specialistName, currentBackColor, currentForeColor) {
    document.getElementById('colorSpecialistId').value = specialistId;
    document.getElementById('colorSpecialistName').value = specialistName;
    document.getElementById('colorSpecialistNameDisplay').textContent = specialistName;
    
    // Set current colors
    document.getElementById('backColorPicker').value = currentBackColor;
    document.getElementById('foreColorPicker').value = currentForeColor;
    
    // Update preview
    window.updateColorPreview(currentBackColor, currentForeColor);
    window.generateColorVariations(currentBackColor);
    
    new bootstrap.Modal(document.getElementById('colorPickerModal')).show();
};

// Update color preview
window.updateColorPreview = function(backColor, foreColor) {
    const preview = document.getElementById('colorPreview');
    preview.style.backgroundColor = backColor;
    preview.style.color = foreColor;
};

// Set preset colors
window.setPresetColors = function(backColor, foreColor) {
    document.getElementById('backColorPicker').value = backColor;
    document.getElementById('foreColorPicker').value = foreColor;
    window.updateColorPreview(backColor, foreColor);
    window.generateColorVariations(backColor);
};

// Generate random colors
window.generateRandomColors = function() {
    const colors = [
        '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe',
        '#43e97b', '#38f9d7', '#fa709a', '#fee140', '#a8edea', '#fed6e3',
        '#ffecd2', '#fcb69f', '#ff9a9e', '#fecfef', '#fecfef', '#fad0c4',
        '#ffd1ff', '#a8caba', '#5d4e75', '#ffecd2', '#fcb69f', '#667eea'
    ];
    
    const randomBackColor = colors[Math.floor(Math.random() * colors.length)];
    const randomForeColor = window.getContrastColor(randomBackColor);
    
    document.getElementById('backColorPicker').value = randomBackColor;
    document.getElementById('foreColorPicker').value = randomForeColor;
    window.updateColorPreview(randomBackColor, randomForeColor);
    window.generateColorVariations(randomBackColor);
};

// Generate color variations for the same family
window.generateColorVariations = function(baseColor) {
    const variationsContainer = document.getElementById('colorVariations');
    const variations = window.getColorVariations(baseColor);
    
    let html = '<div class="row">';
    variations.forEach((variation, index) => {
        const contrastColor = window.getContrastColor(variation);
        html += `
            <div class="col-6 mb-2">
                <button type="button" class="btn btn-sm w-100" 
                        style="background-color: ${variation}; color: ${contrastColor}; border: 1px solid #dee2e6;" 
                        onclick="setPresetColors('${variation}', '${contrastColor}')" 
                        title="Variation ${index + 1}">
                    <small>Variation ${index + 1}</small>
                </button>
            </div>
        `;
    });
    html += '</div>';
    
    variationsContainer.innerHTML = html;
};

// Get color variations (lighter and darker shades)
window.getColorVariations = function(baseColor) {
    const hex = baseColor.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    
    const variations = [];
    
    // Original color
    variations.push(baseColor);
    
    // Lighter variations (add 20%, 40%, 60%)
    for (let i = 1; i <= 3; i++) {
        const factor = 0.2 * i;
        const newR = Math.min(255, Math.round(r + (255 - r) * factor));
        const newG = Math.min(255, Math.round(g + (255 - g) * factor));
        const newB = Math.min(255, Math.round(b + (255 - b) * factor));
        variations.push(`#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`);
    }
    
    // Darker variations (subtract 20%, 40%, 60%)
    for (let i = 1; i <= 3; i++) {
        const factor = 0.2 * i;
        const newR = Math.max(0, Math.round(r * (1 - factor)));
        const newG = Math.max(0, Math.round(g * (1 - factor)));
        const newB = Math.max(0, Math.round(b * (1 - factor)));
        variations.push(`#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`);
    }
    
    return variations;
};

// Submit color change
window.submitColorChange = function() {
    const specialistId = document.getElementById('colorSpecialistId').value;
    const backColor = document.getElementById('backColorPicker').value;
    const foreColor = document.getElementById('foreColorPicker').value;
    
    const formData = new FormData();
    formData.append('specialist_id', specialistId);
    formData.append('back_color', backColor);
    formData.append('foreground_color', foreColor);
    
    fetch('admin/update_specialist_colors.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('colorPickerModal')).hide();
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update colors'));
        }
    })
    .catch(error => {
        console.error('Error updating colors:', error);
        alert('Error updating colors: ' + error.message);
    });
};

// Add event listeners for color picker inputs
document.addEventListener('DOMContentLoaded', function() {
    const backColorPicker = document.getElementById('backColorPicker');
    const foreColorPicker = document.getElementById('foreColorPicker');
    
    if (backColorPicker) {
        backColorPicker.addEventListener('input', function() {
            window.updateColorPreview(this.value, foreColorPicker.value);
            window.generateColorVariations(this.value);
        });
    }
    
    if (foreColorPicker) {
        foreColorPicker.addEventListener('input', function() {
            window.updateColorPreview(backColorPicker.value, this.value);
        });
    }
});
