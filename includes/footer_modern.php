            </div>
        </main>
    </div>
    
    <!-- Notification Container -->
    <div id="notification-container" class="fixed inset-0 z-50 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end">
        <!-- Notifications will be inserted here dynamically -->
    </div>
    
    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center">
                        <div class="h-8 w-8 bg-primary-600 rounded-lg flex items-center justify-center">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                        </div>
                        <span class="ml-3 text-lg font-semibold text-gray-900"><?= EnvConfig::get('APP_NAME', 'Satın Alma Sistemi') ?></span>
                    </div>
                    <p class="mt-4 text-sm text-gray-600 max-w-md">
                        Kurumsal satın alma süreçlerinizi dijitalleştirin. Onay akışlarından raporlamaya kadar tüm süreçleri tek platformda yönetin.
                    </p>
                    <div class="mt-4 flex space-x-4">
                        <span class="text-sm text-gray-500">Sürüm: 2.0</span>
                        <span class="text-sm text-gray-300">|</span>
                        <span class="text-sm text-gray-500">Güvenli SSL</span>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 tracking-wider uppercase">Hızlı Erişim</h3>
                    <ul class="mt-4 space-y-3">
                        <li><a href="dashboard.php" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Ana Sayfa</a></li>
                        <li><a href="new_request.php" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Yeni Talep</a></li>
                        <li><a href="my_requests.php" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Taleplerim</a></li>
                        <li><a href="approval_queue.php" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Onaylar</a></li>
                    </ul>
                </div>
                
                <!-- Support -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 tracking-wider uppercase">Destek</h3>
                    <ul class="mt-4 space-y-3">
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Kullanım Kılavuzu</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">SSS</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">İletişim</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Sistem Durumu</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-8 border-t border-gray-200 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-sm text-gray-500">
                        &copy; <?= date('Y') ?> <?= EnvConfig::get('APP_NAME', 'Satın Alma Sistemi') ?>. Tüm hakları saklıdır.
                    </p>
                    <div class="mt-4 md:mt-0 flex items-center space-x-4">
                        <span class="text-xs text-gray-400">Powered by</span>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-500 font-medium">PHP</span>
                            <span class="text-xs text-gray-300">•</span>
                            <span class="text-xs text-gray-500 font-medium">MySQL</span>
                            <span class="text-xs text-gray-300">•</span>
                            <span class="text-xs text-gray-500 font-medium">N8N</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        // CSRF Token for AJAX requests
        window.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Configure axios defaults if using axios
        if (typeof axios !== 'undefined') {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = window.csrfToken;
        }
        
        // Notification System
        class NotificationManager {
            constructor() {
                this.container = document.getElementById('notification-container');
                this.notifications = [];
            }
            
            show(message, type = 'info', duration = 5000) {
                const id = Date.now().toString();
                const notification = this.createNotification(id, message, type);
                
                this.container.appendChild(notification);
                this.notifications.push({ id, element: notification });
                
                // Show animation
                setTimeout(() => {
                    notification.classList.add('notification-enter-active');
                    notification.classList.remove('notification-enter');
                }, 10);
                
                // Auto remove
                if (duration > 0) {
                    setTimeout(() => this.remove(id), duration);
                }
                
                return id;
            }
            
            createNotification(id, message, type) {
                const colors = {
                    success: 'bg-success-50 text-success-800 border-success-200',
                    error: 'bg-danger-50 text-danger-800 border-danger-200',
                    warning: 'bg-warning-50 text-warning-800 border-warning-200',
                    info: 'bg-primary-50 text-primary-800 border-primary-200'
                };
                
                const icons = {
                    success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                    error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                    warning: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"></path>',
                    info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                };
                
                const div = document.createElement('div');
                div.id = `notification-${id}`;
                div.className = `notification-enter pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg border shadow-lg ${colors[type] || colors.info}`;
                
                div.innerHTML = `
                    <div class="p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    ${icons[type] || icons.info}
                                </svg>
                            </div>
                            <div class="ml-3 w-0 flex-1 pt-0.5">
                                <p class="text-sm font-medium">${message}</p>
                            </div>
                            <div class="ml-4 flex flex-shrink-0">
                                <button onclick="notifications.remove('${id}')" class="inline-flex rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2">
                                    <span class="sr-only">Kapat</span>
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                return div;
            }
            
            remove(id) {
                const notification = this.notifications.find(n => n.id === id);
                if (notification) {
                    notification.element.classList.add('notification-leave-active');
                    setTimeout(() => {
                        if (notification.element.parentNode) {
                            notification.element.parentNode.removeChild(notification.element);
                        }
                        this.notifications = this.notifications.filter(n => n.id !== id);
                    }, 300);
                }
            }
            
            success(message) { return this.show(message, 'success'); }
            error(message) { return this.show(message, 'error'); }
            warning(message) { return this.show(message, 'warning'); }
            info(message) { return this.show(message, 'info'); }
        }
        
        // Initialize global notification manager
        window.notifications = new NotificationManager();
        
        // Auto-show PHP flash messages
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['flash_message'])): ?>
                notifications.<?= $_SESSION['flash_type'] ?? 'info' ?>('<?= addslashes($_SESSION['flash_message']) ?>');
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>
        });
        
        // Form helpers
        function submitFormWithCSRF(form) {
            const csrfInput = form.querySelector('input[name="csrf_token"]');
            if (!csrfInput) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'csrf_token';
                hiddenInput.value = window.csrfToken;
                form.appendChild(hiddenInput);
            }
            return true;
        }
        
        // Auto-submit CSRF for all forms
        document.addEventListener('submit', function(e) {
            if (e.target.tagName === 'FORM') {
                submitFormWithCSRF(e.target);
            }
        });
        
        // Loading states for buttons
        function setButtonLoading(button, loading = true) {
            if (loading) {
                button.disabled = true;
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    İşleniyor...
                `;
            } else {
                button.disabled = false;
                button.innerHTML = button.dataset.originalText || button.innerHTML;
            }
        }
    </script>
</body>
</html>