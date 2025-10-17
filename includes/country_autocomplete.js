/**
 * Country Autocomplete Component
 * Provides searchable dropdown with extensive country list
 */

// Comprehensive country list with regions
const COUNTRIES = [
    // Europe
    { code: 'AD', name: 'Andorra', region: 'Europe' },
    { code: 'AL', name: 'Albania', region: 'Europe' },
    { code: 'AT', name: 'Austria', region: 'Europe' },
    { code: 'BA', name: 'Bosnia and Herzegovina', region: 'Europe' },
    { code: 'BE', name: 'Belgium', region: 'Europe' },
    { code: 'BG', name: 'Bulgaria', region: 'Europe' },
    { code: 'BY', name: 'Belarus', region: 'Europe' },
    { code: 'CH', name: 'Switzerland', region: 'Europe' },
    { code: 'CY', name: 'Cyprus', region: 'Europe' },
    { code: 'CZ', name: 'Czech Republic', region: 'Europe' },
    { code: 'DE', name: 'Germany', region: 'Europe' },
    { code: 'DK', name: 'Denmark', region: 'Europe' },
    { code: 'EE', name: 'Estonia', region: 'Europe' },
    { code: 'ES', name: 'Spain', region: 'Europe' },
    { code: 'FI', name: 'Finland', region: 'Europe' },
    { code: 'FR', name: 'France', region: 'Europe' },
    { code: 'GB', name: 'United Kingdom', region: 'Europe' },
    { code: 'GR', name: 'Greece', region: 'Europe' },
    { code: 'HR', name: 'Croatia', region: 'Europe' },
    { code: 'HU', name: 'Hungary', region: 'Europe' },
    { code: 'IE', name: 'Ireland', region: 'Europe' },
    { code: 'IS', name: 'Iceland', region: 'Europe' },
    { code: 'IT', name: 'Italy', region: 'Europe' },
    { code: 'LI', name: 'Liechtenstein', region: 'Europe' },
    { code: 'LT', name: 'Lithuania', region: 'Europe' },
    { code: 'LU', name: 'Luxembourg', region: 'Europe' },
    { code: 'LV', name: 'Latvia', region: 'Europe' },
    { code: 'MC', name: 'Monaco', region: 'Europe' },
    { code: 'MD', name: 'Moldova', region: 'Europe' },
    { code: 'ME', name: 'Montenegro', region: 'Europe' },
    { code: 'MK', name: 'North Macedonia', region: 'Europe' },
    { code: 'MT', name: 'Malta', region: 'Europe' },
    { code: 'NL', name: 'Netherlands', region: 'Europe' },
    { code: 'NO', name: 'Norway', region: 'Europe' },
    { code: 'PL', name: 'Poland', region: 'Europe' },
    { code: 'PT', name: 'Portugal', region: 'Europe' },
    { code: 'RO', name: 'Romania', region: 'Europe' },
    { code: 'RS', name: 'Serbia', region: 'Europe' },
    { code: 'SE', name: 'Sweden', region: 'Europe' },
    { code: 'SI', name: 'Slovenia', region: 'Europe' },
    { code: 'SK', name: 'Slovakia', region: 'Europe' },
    { code: 'SM', name: 'San Marino', region: 'Europe' },
    { code: 'UA', name: 'Ukraine', region: 'Europe' },
    { code: 'UK', name: 'United Kingdom', region: 'Europe' },
    { code: 'VA', name: 'Vatican City', region: 'Europe' },
    
    // North America
    { code: 'CA', name: 'Canada', region: 'North America' },
    { code: 'MX', name: 'Mexico', region: 'North America' },
    { code: 'US', name: 'United States', region: 'North America' },
    
    // Asia
    { code: 'CN', name: 'China', region: 'Asia' },
    { code: 'IN', name: 'India', region: 'Asia' },
    { code: 'JP', name: 'Japan', region: 'Asia' },
    { code: 'KR', name: 'South Korea', region: 'Asia' },
    { code: 'SG', name: 'Singapore', region: 'Asia' },
    { code: 'TH', name: 'Thailand', region: 'Asia' },
    { code: 'TR', name: 'Turkey', region: 'Asia' },
    
    // Oceania
    { code: 'AU', name: 'Australia', region: 'Oceania' },
    { code: 'NZ', name: 'New Zealand', region: 'Oceania' },
    
    // Africa
    { code: 'EG', name: 'Egypt', region: 'Africa' },
    { code: 'MA', name: 'Morocco', region: 'Africa' },
    { code: 'ZA', name: 'South Africa', region: 'Africa' },
    
    // South America
    { code: 'AR', name: 'Argentina', region: 'South America' },
    { code: 'BR', name: 'Brazil', region: 'South America' },
    { code: 'CL', name: 'Chile', region: 'South America' },
    { code: 'CO', name: 'Colombia', region: 'South America' },
];

/**
 * Create country autocomplete for a given input field
 * @param {string} inputId - ID of the input field
 * @param {string} hiddenInputId - ID of hidden input to store country code
 */
function createCountryAutocomplete(inputId, hiddenInputId = null) {
    const input = document.getElementById(inputId);
    if (!input) {
        console.error('Country autocomplete: Input field not found:', inputId);
        return;
    }

    // Create dropdown container
    const container = document.createElement('div');
    container.className = 'country-autocomplete-container';
    container.style.position = 'relative';
    container.style.width = '100%';

    // Wrap the input
    input.parentNode.insertBefore(container, input);
    container.appendChild(input);

    // Create dropdown
    const dropdown = document.createElement('div');
    dropdown.className = 'country-autocomplete-dropdown';
    dropdown.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-top: none;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    `;
    container.appendChild(dropdown);

    // Hidden input for country code
    let hiddenInput = null;
    if (hiddenInputId) {
        hiddenInput = document.getElementById(hiddenInputId);
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.id = hiddenInputId;
            hiddenInput.name = hiddenInputId;
            container.appendChild(hiddenInput);
        }
    }

    // Filter and display countries
    function filterCountries(searchTerm) {
        const filtered = COUNTRIES.filter(country => {
            const searchLower = searchTerm.toLowerCase();
            return country.name.toLowerCase().includes(searchLower) ||
                   country.code.toLowerCase().includes(searchLower) ||
                   country.region.toLowerCase().includes(searchLower) ||
                   (country.name + '/' + country.region).toLowerCase().includes(searchLower);
        });

        dropdown.innerHTML = '';
        
        if (filtered.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        filtered.slice(0, 10).forEach(country => { // Limit to 10 results
            const item = document.createElement('div');
            item.className = 'country-autocomplete-item';
            item.style.cssText = `
                padding: 8px 12px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
                transition: background-color 0.2s;
            `;
            item.innerHTML = `<strong>${country.name}</strong> <span style="color: #666;">/ ${country.region}</span> <span style="color: #999; font-size: 0.9em;">(${country.code})</span>`;
            
            item.addEventListener('mouseenter', () => {
                item.style.backgroundColor = '#f0f0f0';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.backgroundColor = 'white';
            });
            
            item.addEventListener('click', () => {
                input.value = `${country.name}/${country.region}`;
                if (hiddenInput) {
                    hiddenInput.value = country.code;
                }
                dropdown.style.display = 'none';
                
                // Trigger change event
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            
            dropdown.appendChild(item);
        });

        dropdown.style.display = 'block';
    }

    // Input event handlers
    input.addEventListener('input', (e) => {
        const value = e.target.value.trim();
        if (value.length >= 1) {
            filterCountries(value);
        } else {
            dropdown.style.display = 'none';
            if (hiddenInput) {
                hiddenInput.value = '';
            }
        }
    });

    input.addEventListener('focus', (e) => {
        if (e.target.value.trim().length >= 1) {
            filterCountries(e.target.value.trim());
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Handle keyboard navigation
    input.addEventListener('keydown', (e) => {
        const items = dropdown.querySelectorAll('.country-autocomplete-item');
        let selectedIndex = -1;
        
        items.forEach((item, index) => {
            if (item.style.backgroundColor === 'rgb(240, 240, 240)') {
                selectedIndex = index;
            }
        });

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const nextIndex = Math.min(selectedIndex + 1, items.length - 1);
            selectItem(items, nextIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prevIndex = Math.max(selectedIndex - 1, 0);
            selectItem(items, prevIndex);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && items[selectedIndex]) {
                items[selectedIndex].click();
            }
        } else if (e.key === 'Escape') {
            dropdown.style.display = 'none';
        }
    });

    function selectItem(items, index) {
        items.forEach((item, i) => {
            item.style.backgroundColor = i === index ? '#f0f0f0' : 'white';
        });
    }
}

/**
 * Set country value programmatically
 * @param {string} inputId - ID of the input field
 * @param {string} countryCode - Country code to set
 */
function setCountryValue(inputId, countryCode) {
    const input = document.getElementById(inputId);
    if (!input) return;

    // Handle both uppercase and lowercase country codes
    const upperCode = countryCode.toUpperCase();
    const country = COUNTRIES.find(c => c.code === upperCode);
    if (country) {
        input.value = `${country.name}/${country.region}`;
        
        // Set hidden input if exists
        const hiddenInput = input.parentNode.querySelector(`input[type="hidden"]`);
        if (hiddenInput) {
            hiddenInput.value = country.code;
        }
    }
}

/**
 * Get country code from display value
 * @param {string} displayValue - Display value like "Romania/Europe"
 * @returns {string} Country code or empty string
 */
function getCountryCodeFromDisplay(displayValue) {
    if (!displayValue) return '';
    
    for (const country of COUNTRIES) {
        if (displayValue === `${country.name}/${country.region}`) {
            return country.code;
        }
    }
    return '';
} 