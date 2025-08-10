$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Smooth scrolling for anchor links
    $('a[href*="#"]').on('click', function(e) {
        if(this.hash !== '') {
            e.preventDefault();
            const hash = this.hash;
            $('html, body').animate({
                scrollTop: $(hash).offset().top - 70
            }, 800);
        }
    });
    
    // Fade in animations
    $('.fade-in').each(function(index) {
        $(this).delay(index * 100).queue(function(next) {
            $(this).addClass('animate__animated animate__fadeInUp');
            next();
        });
    });
    
    // Alert auto-hide
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Loading button states
    $('.btn-loading').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $btn.html('<span class="spinner-border spinner-border-sm me-2"></span>กำลังดำเนินการ...');
    });
});

// Utility functions
function showAlert(message, type = 'info', duration = 5000) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('.container').prepend(alertHtml);
    
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, duration);
}

function formatPrice(price) {
    return '฿' + parseFloat(price).toLocaleString('th-TH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function validatePhone(phone) {
    const phoneRegex = /^[0-9]{10}$/;
    return phoneRegex.test(phone.replace(/[-\s]/g, ''));
}