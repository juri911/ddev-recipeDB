// Magnet rotate functionality
document.addEventListener('DOMContentLoaded', function() {
    const magnetRotateElements = document.querySelectorAll('.magnet-rotate');
    
    magnetRotateElements.forEach(function(element) {
        element.addEventListener('click', function() {
            this.classList.toggle('down');
        });
    });
});