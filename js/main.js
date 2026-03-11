document.addEventListener('DOMContentLoaded', function() {
    // Sticky navbar that shows on scroll up
    let lastScrollTop = 0;
    const navbar = document.querySelector('.navbar');
    const body = document.body;
    
    // Handle navbar toggler click - ensure overlay is handled correctly
    const navbarToggler = document.querySelector('.navbar-toggler');
    if (navbarToggler) {
        // Initial setup of overlay visibility based on current state
        const initialState = document.querySelector('.navbar-collapse').classList.contains('show');
        if (!initialState) {
            document.querySelector('.nav-overlay').style.backgroundColor = 'transparent';
        }
        
        navbarToggler.addEventListener('click', function() {
            // Toggle a class to control overlay visibility
            setTimeout(function() {
                const isExpanded = navbarToggler.getAttribute('aria-expanded') === 'true';
                if (isExpanded) {
                    navbar.classList.add('menu-expanded');
                    document.querySelector('.nav-overlay').style.backgroundColor = 'rgba(0, 0, 0, 0.1)';
                } else {
                    navbar.classList.remove('menu-expanded');
                    document.querySelector('.nav-overlay').style.backgroundColor = 'transparent';
                }
            }, 10); // Small timeout to ensure DOM is updated
        });
    }
    
    // Add sticky navbar functionality only if navbar exists
    if (navbar) {
        // Add a spacer element after navbar to prevent content jump
        const spacer = document.createElement('div');
        spacer.className = 'sticky-spacer';
        navbar.parentNode.insertBefore(spacer, navbar.nextSibling);
        
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            // Initial state - when at the top of the page
            if (scrollTop <= 50) {
                navbar.classList.remove('navbar-sticky');
                navbar.classList.remove('navbar-sticky-hide');
                body.classList.remove('has-sticky-navbar');
                return;
            }
            
            // Add sticky class when not at the top
            if (!navbar.classList.contains('navbar-sticky')) {
                navbar.classList.add('navbar-sticky');
                body.classList.add('has-sticky-navbar');
            }
            
            // Check scroll direction
            if (scrollTop > lastScrollTop) {
                // Scrolling DOWN - hide navbar
                navbar.classList.add('navbar-sticky-hide');
            } else {
                // Scrolling UP - show navbar
                navbar.classList.remove('navbar-sticky-hide');
            }
            
            lastScrollTop = scrollTop;
        });
    }
}); 