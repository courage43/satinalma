            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Additional JavaScript -->
    <script>
        // CSRF Token for AJAX requests
        window.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Configure axios defaults if using axios
        if (typeof axios !== 'undefined') {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = window.csrfToken;
        }
        
        // Simple notification system
        function showNotification(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }
        
        // Auto-submit CSRF for all forms
        document.addEventListener('submit', function(e) {
            if (e.target.tagName === 'FORM') {
                const csrfInput = e.target.querySelector('input[name="csrf_token"]');
                if (!csrfInput) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'csrf_token';
                    hiddenInput.value = window.csrfToken;
                    e.target.appendChild(hiddenInput);
                }
            }
        });
        
        // Loading states for buttons
        function setButtonLoading(button, loading = true) {
            if (loading) {
                button.disabled = true;
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>İşleniyor...';
            } else {
                button.disabled = false;
                button.innerHTML = button.dataset.originalText || button.innerHTML;
            }
        }
    </script>
</body>
</html>