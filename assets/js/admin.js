document.addEventListener('DOMContentLoaded', function() {
    // Find all shortcode elements
    const shortcodeElements = document.querySelectorAll('.rep-group-shortcode');

    shortcodeElements.forEach(element => {
        element.addEventListener('click', async function() {
            const shortcode = this.textContent.trim();
            const feedback = this.nextElementSibling;

            try {
                // Try to use the modern clipboard API first
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(shortcode);
                } else {
                    // Fallback for older browsers
                    const textarea = document.createElement('textarea');
                    textarea.value = shortcode;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                }

                // Show feedback
                if (feedback && feedback.classList.contains('shortcode-copied')) {
                    feedback.style.display = 'block';
                    
                    // Visual feedback on the shortcode element
                    element.style.backgroundColor = '#e0e0e1';
                    
                    // Reset after 2 seconds
                    setTimeout(() => {
                        feedback.style.display = 'none';
                        element.style.backgroundColor = '';
                    }, 2000);
                }
            } catch (err) {
                console.error('Failed to copy shortcode:', err);
                
                // Show error feedback
                if (feedback) {
                    feedback.textContent = 'Failed to copy. Please select and copy manually.';
                    feedback.style.color = '#d63638';
                    feedback.style.display = 'block';
                    
                    setTimeout(() => {
                        feedback.style.display = 'none';
                        feedback.textContent = 'Shortcode copied to clipboard!';
                        feedback.style.color = '';
                    }, 3000);
                }
            }
        });

        // Add hover title
        element.title = 'Click to copy shortcode';
    });
});
