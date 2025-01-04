document.addEventListener('DOMContentLoaded', function() {
    const map = document.querySelector('svg');
    const resultsContainer = document.getElementById('rep-group-results');
    let activeState = null;

    // Add hover effects
    map.querySelectorAll('path[id^="US-"]').forEach(state => {
        state.addEventListener('mouseenter', function() {
            this.style.opacity = '0.8';
            this.style.cursor = 'pointer';
        });

        state.addEventListener('mouseleave', function() {
            if (this !== activeState) {
                this.style.opacity = '1';
            }
        });

        state.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Reset previous active state
            if (activeState) {
                activeState.style.opacity = '1';
                activeState.classList.remove('active');
            }
            
            // Set new active state
            this.style.opacity = '0.8';
            this.classList.add('active');
            activeState = this;

            // Show loading state
            if (resultsContainer) {
                resultsContainer.innerHTML = '<div class="loading">Loading...</div>';
            }

            // Fetch rep groups for this state
            const formData = new FormData();
            formData.append('action', 'get_rep_groups_by_state');
            formData.append('state', this.id);
            formData.append('nonce', repGroupsData.nonce);

            fetch(repGroupsData.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && resultsContainer) {
                    resultsContainer.innerHTML = data.data.html;
                    resultsContainer.scrollIntoView({ behavior: 'smooth' });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (resultsContainer) {
                    resultsContainer.innerHTML = '<p class="error">Error loading representatives.</p>';
                }
            });
        });
    });
}); 