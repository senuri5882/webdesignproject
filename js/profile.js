
document.getElementById('password-form')?.addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
    }
    
    if (newPassword.length < 8) {
        e.preventDefault();
        alert('New password must be at least 8 characters long!');
    }
});
