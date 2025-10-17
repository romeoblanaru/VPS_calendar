document.getElementById('year') && (document.getElementById('year').textContent = new Date().getFullYear());
const t=document.getElementById('navToggle'),m=document.getElementById('navMenu'); if(t&&m){t.addEventListener('click',()=>{m.style.display=m.style.display==='flex'?'none':'flex';});}

// Login dropdown functionality
function setupLoginDropdown(btnId, formId) {
  const btn = document.getElementById(btnId);
  const form = document.getElementById(formId);
  
  if (btn && form) {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      // Close other dropdowns
      document.querySelectorAll('.login-form').forEach(f => {
        if (f !== form) f.classList.remove('show');
      });
      
      form.classList.toggle('show');
      if (form.classList.contains('show')) {
        const usernameField = form.querySelector('input[name="username"]');
        if (usernameField) usernameField.focus();
      }
    });
    
    form.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        const formElement = form.querySelector('form');
        if (formElement && formElement.checkValidity()) {
          formElement.submit();
        }
      }
    });
  }
}

// Setup both login dropdowns
setupLoginDropdown('enterBtn', 'loginForm');
setupLoginDropdown('heroEnterBtn', 'heroLoginForm');

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.login-dropdown') && !e.target.closest('.login-dropdown-hero')) {
    document.querySelectorAll('.login-form').forEach(f => f.classList.remove('show'));
  }
});