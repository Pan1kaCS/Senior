document.addEventListener('DOMContentLoaded', function() {
    const profileBtn = document.querySelector('.user-profile-btn');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    const arrowSvg = profileBtn.querySelector('svg');
    
    if (profileBtn) {
        profileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            dropdownMenu.classList.toggle('show');
            
            // Toggle SVG class for active state
            if (dropdownMenu.classList.contains('show')) {
                arrowSvg.classList.add('Header_active__Qjfb8');
            } else {
                arrowSvg.classList.remove('Header_active__Qjfb8');
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileBtn.contains(e.target)) {
                dropdownMenu.classList.remove('show');
                arrowSvg.classList.remove('Header_active__Qjfb8');
            }
        });
    }
});