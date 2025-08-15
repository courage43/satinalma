<?php
/**
 * Automated Approval Workflow Engine
 * SAS & GS onayları için otomatik bildirim ve takip sistemi
 */

require_once 'helpers.php';
require_once 'audit_log.php';
require_once dirname(__DIR__) . '/config/security.php';

class WorkflowEngine {
    private $db;
    private $security;
    private static $instance = null;
    
    private function __construct() {
        $this->db = DatabaseHelper::getInstance();
        $this->security = SecurityManager::getInstance();
        $this->ensureWorkflowTables();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create workflow tables if not exist
     */
    private function ensureWorkflowTables() {
        $tables = [
            "CREATE TABLE IF NOT EXISTS workflow_tasks (
                id INT PRIMARY KEY AUTO_INCREMENT,
                request_id INT NOT NULL,
                task_type ENUM('approval', 'notification', 'reminder') NOT NULL,
                assigned_to INT NOT NULL,
                assigned_role VARCHAR(50) NOT NULL,
                priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                status ENUM('pending', 'in_progress', 'completed', 'failed', 'expired') DEFAULT 'pending',
                due_date DATETIME NULL,
                completion_date DATETIME NULL,
                token VARCHAR(500) NULL,
                metadata JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                FOREIGN KEY (request_id) REFERENCES purchase_requests(id),
                FOREIGN KEY (assigned_to) REFERENCES users(id),
                INDEX idx_status (status),
                INDEX idx_due_date (due_date),
                INDEX idx_assigned_to (assigned_to)
            )",
            
            "CREATE TABLE IF NOT EXISTS workflow_notifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                task_id INT NOT NULL,
                notification_type ENUM('email', 'telegram', 'webhook') NOT NULL,
                recipient VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NULL,
                message TEXT NOT NULL,
                status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
                sent_at DATETIME NULL,
                error_message TEXT NULL,
                retry_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (task_id) REFERENCES workflow_tasks(id),
                INDEX idx_status (status),
                INDEX idx_notification_type (notification_type)
            )",
            
            "CREATE TABLE IF NOT EXISTS workflow_reminders (
                id INT PRIMARY KEY AUTO_INCREMENT,
                task_id INT NOT NULL,
                reminder_type ENUM('first', 'second', 'final', 'escalation') NOT NULL,
                scheduled_at DATETIME NOT NULL,
                sent_at DATETIME NULL,
                status ENUM('scheduled', 'sent', 'cancelled') DEFAULT 'scheduled',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (task_id) REFERENCES workflow_tasks(id),
                INDEX idx_scheduled_at (scheduled_at),
                INDEX idx_status (status)
            )"
        ];
        
        foreach ($tables as $sql) {
            try {
                $this->db->execute($sql);
            } catch (Exception $e) {
                error_log('Workflow table creation failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Start workflow for a new request
     */
    public function startWorkflow($requestId, $requestData) {
        try {
            $this->db->beginTransaction();
            
            // Create SAS approval task
            $sasTaskId = $this->createApprovalTask($requestId, 'satin_alma_sorumlusu', [
                'step' => 'sas_approval',
                'title' => $requestData['title'] ?? 'Talep',
                'urgency' => $requestData['urgency_level'] ?? 'orta'
            ]);
            
            // Schedule notifications for SAS
            $this->scheduleNotifications($sasTaskId, 'sas_approval', $requestData);
            
            // Schedule reminders
            $this->scheduleReminders($sasTaskId);
            
            $this->db->commit();
            
            audit()->log(
                AuditLogger::EVENT_REQUEST_CREATE,
                "Workflow started for request {$requestId}",
                [
                    'table' => 'workflow_tasks',
                    'id' => $sasTaskId,
                    'additional_data' => ['step' => 'sas_approval']
                ]
            );
            
            // Trigger immediate notification
            $this->processImmediateNotifications($sasTaskId);
            
            return $sasTaskId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Workflow start failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Process approval action
     */
    public function processApproval($token, $action, $comments = null) {
        // Validate token
        $tokenData = $this->security->validateOneTimeToken($token);
        if (!$tokenData) {
            auditSecurity('invalid_approval_token', ['token' => FormatHelper::mask($token)]);
            throw new Exception('Geçersiz veya süresi dolmuş onay linki');
        }
        
        $taskId = $tokenData['task_id'];
        $userId = $tokenData['user_id'];
        $allowedAction = $tokenData['action'];
        
        if ($action !== $allowedAction) {
            auditSecurity('unauthorized_approval_action', [
                'expected' => $allowedAction,
                'attempted' => $action,
                'user_id' => $userId
            ]);
            throw new Exception('Yetkisiz işlem');
        }
        
        try {
            $this->db->beginTransaction();
            
            // Get task details
            $task = $this->db->fetchRow(
                "SELECT wt.*, pr.request_number, pr.title FROM workflow_tasks wt 
                 LEFT JOIN purchase_requests pr ON wt.request_id = pr.id 
                 WHERE wt.id = ?",
                [$taskId]
            );
            
            if (!$task || $task['status'] !== 'pending') {
                throw new Exception('Görev bulunamadı veya zaten işlenmiş');
            }
            
            // Update task status
            $this->db->update(
                'workflow_tasks',
                [
                    'status' => 'completed',
                    'completion_date' => DateHelper::now()
                ],
                'id = ?',
                [$taskId]
            );
            
            // Cancel pending reminders
            $this->db->update(
                'workflow_reminders',
                ['status' => 'cancelled'],
                'task_id = ? AND status = ?',
                [$taskId, 'scheduled']
            );
            
            // Process the approval/rejection
            if ($action === 'approve') {
                $this->handleApproval($task, $comments, $userId);
            } else {
                $this->handleRejection($task, $comments, $userId);
            }
            
            $this->db->commit();
            
            auditApproval(
                $action,
                $task['request_id'],
                $task['request_number'],
                $comments,
                $task['assigned_role']
            );
            
            return [
                'success' => true,
                'message' => $action === 'approve' ? 'Talep onaylandı' : 'Talep reddedildi',
                'request_number' => $task['request_number']
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Handle approval and continue workflow
     */
    private function handleApproval($task, $comments, $userId) {
        $requestId = $task['request_id'];
        $currentRole = $task['assigned_role'];
        
        // Update request status based on current step
        $newStatus = match($currentRole) {
            'satin_alma_sorumlusu' => 'gs_incelemede',
            'genel_sekreter' => 'teklif_toplama',
            default => 'onaylandı'
        };
        
        $this->db->update(
            'purchase_requests',
            ['status' => $newStatus],
            'id = ?',
            [$requestId]
        );
        
        // Continue workflow if needed
        if ($newStatus === 'gs_incelemede') {
            // Create GS approval task
            $request = $this->db->fetchRow(
                "SELECT * FROM purchase_requests WHERE id = ?",
                [$requestId]
            );
            
            $gsTaskId = $this->createApprovalTask($requestId, 'genel_sekreter', [
                'step' => 'gs_approval',
                'title' => $request['title'],
                'urgency' => $request['urgency_level'],
                'sas_comments' => $comments
            ]);
            
            $this->scheduleNotifications($gsTaskId, 'gs_approval', $request);
            $this->scheduleReminders($gsTaskId);
            $this->processImmediateNotifications($gsTaskId);
        }
        
        // Send notification to requester
        $this->notifyRequester($requestId, 'approved', $comments);
        
        // Trigger webhook
        $this->triggerWebhook('approval_update', [
            'request_id' => $requestId,
            'action' => 'approved',
            'step' => $currentRole,
            'comments' => $comments
        ]);
    }
    
    /**
     * Handle rejection
     */
    private function handleRejection($task, $comments, $userId) {
        $requestId = $task['request_id'];
        
        // Update request status
        $this->db->update(
            'purchase_requests',
            ['status' => 'red_edildi'],
            'id = ?',
            [$requestId]
        );
        
        // Cancel all pending tasks for this request
        $this->db->update(
            'workflow_tasks',
            ['status' => 'cancelled'],
            'request_id = ? AND status = ?',
            [$requestId, 'pending']
        );
        
        // Send notification to requester
        $this->notifyRequester($requestId, 'rejected', $comments);
        
        // Trigger webhook
        $this->triggerWebhook('approval_update', [
            'request_id' => $requestId,
            'action' => 'rejected',
            'step' => $task['assigned_role'],
            'comments' => $comments
        ]);
    }
    
    /**
     * Create approval task
     */
    private function createApprovalTask($requestId, $role, $metadata = []) {
        // Get user with the specified role
        $user = $this->db->fetchRow(
            "SELECT u.id, u.email, u.first_name, u.last_name FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE r.name = ? AND u.is_active = 1 LIMIT 1",
            [$role]
        );
        
        if (!$user) {
            throw new Exception("No active user found with role: {$role}");
        }
        
        // Calculate due date (48 hours for approvals)
        $dueDate = DateHelper::addBusinessDays(date('Y-m-d'), 2) . ' 18:00:00';
        
        // Create task first without token
        $taskData = [
            'request_id' => $requestId,
            'task_type' => 'approval',
            'assigned_to' => $user['id'],
            'assigned_role' => $role,
            'priority' => $metadata['urgency'] === 'acil' ? 'urgent' : 'medium',
            'due_date' => $dueDate,
            'metadata' => json_encode(array_merge($metadata, [
                'user_name' => $user['first_name'] . ' ' . $user['last_name'],
                'user_email' => $user['email']
            ]))
        ];
        
        $taskId = $this->db->insert('workflow_tasks', $taskData);
        
        // Generate approval token with task_id
        $tokenPayload = [
            'user_id' => $user['id'],
            'task_id' => $taskId,
            'action' => 'approve',
            'expires' => time() + (48 * 3600),
            'nonce' => bin2hex(random_bytes(16))
        ];
        
        $tokenData = base64_encode(json_encode($tokenPayload));
        $signature = hash_hmac('sha256', $tokenData, EnvConfig::required('CSRF_SECRET'));
        $token = $tokenData . '.' . $signature;
        
        // Update task with token
        $this->db->update(
            'workflow_tasks',
            ['token' => $token],
            'id = ?',
            [$taskId]
        );
        
        return $taskId;
    }
    
    /**
     * Schedule notifications for a task
     */
    private function scheduleNotifications($taskId, $step, $requestData) {
        $task = $this->db->fetchRow(
            "SELECT wt.*, u.email, u.first_name, u.last_name FROM workflow_tasks wt 
             LEFT JOIN users u ON wt.assigned_to = u.id 
             WHERE wt.id = ?",
            [$taskId]
        );
        
        if (!$task) return;
        
        $metadata = json_decode($task['metadata'], true) ?: [];
        
        // Email notification
        $this->createNotification($taskId, 'email', $task['email'], [
            'subject' => "Onay Bekleyen Talep: {$requestData['title']}",
            'template' => 'approval_request',
            'data' => [
                'user_name' => $task['first_name'] . ' ' . $task['last_name'],
                'request_title' => $requestData['title'],
                'request_number' => $requestData['request_number'] ?? 'N/A',
                'urgency' => $requestData['urgency_level'] ?? 'orta',
                'approve_url' => $this->generateApprovalUrl($task['token'], 'approve'),
                'reject_url' => $this->generateApprovalUrl($task['token'], 'reject'),
                'step' => $step
            ]
        ]);
        
        // Telegram notification (if configured)
        if (EnvConfig::get('TELEGRAM_BOT_TOKEN')) {
            $this->createNotification($taskId, 'telegram', $task['assigned_to'], [
                'message' => $this->generateTelegramMessage($task, $requestData, $step)
            ]);
        }
    }
    
    /**
     * Schedule automatic reminders
     */
    private function scheduleReminders($taskId) {
        $task = $this->db->fetchRow("SELECT * FROM workflow_tasks WHERE id = ?", [$taskId]);
        if (!$task) return;
        
        $dueDate = new DateTime($task['due_date']);
        $now = new DateTime();
        
        // 24 hours before due date
        $firstReminder = clone $dueDate;
        $firstReminder->sub(new DateInterval('P1D'));
        
        if ($firstReminder > $now) {
            $this->db->insert('workflow_reminders', [
                'task_id' => $taskId,
                'reminder_type' => 'first',
                'scheduled_at' => $firstReminder->format('Y-m-d H:i:s')
            ]);
        }
        
        // 4 hours before due date
        $secondReminder = clone $dueDate;
        $secondReminder->sub(new DateInterval('PT4H'));
        
        if ($secondReminder > $now) {
            $this->db->insert('workflow_reminders', [
                'task_id' => $taskId,
                'reminder_type' => 'second',
                'scheduled_at' => $secondReminder->format('Y-m-d H:i:s')
            ]);
        }
        
        // At due date
        $this->db->insert('workflow_reminders', [
            'task_id' => $taskId,
            'reminder_type' => 'final',
            'scheduled_at' => $dueDate->format('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Create notification record
     */
    private function createNotification($taskId, $type, $recipient, $data) {
        $notificationData = [
            'task_id' => $taskId,
            'notification_type' => $type,
            'recipient' => $recipient,
            'subject' => $data['subject'] ?? null,
            'message' => $type === 'email' ? json_encode($data) : $data['message']
        ];
        
        return $this->db->insert('workflow_notifications', $notificationData);
    }
    
    /**
     * Process immediate notifications
     */
    private function processImmediateNotifications($taskId) {
        $notifications = $this->db->fetchAll(
            "SELECT * FROM workflow_notifications WHERE task_id = ? AND status = 'pending'",
            [$taskId]
        );
        
        foreach ($notifications as $notification) {
            $this->sendNotification($notification);
        }
    }
    
    /**
     * Send notification
     */
    private function sendNotification($notification) {
        try {
            $success = false;
            
            switch ($notification['notification_type']) {
                case 'email':
                    $success = $this->sendEmail($notification);
                    break;
                case 'telegram':
                    $success = $this->sendTelegram($notification);
                    break;
                case 'webhook':
                    $success = $this->sendWebhook($notification);
                    break;
            }
            
            // Update notification status
            $this->db->update(
                'workflow_notifications',
                [
                    'status' => $success ? 'sent' : 'failed',
                    'sent_at' => $success ? DateHelper::now() : null,
                    'retry_count' => $notification['retry_count'] + 1
                ],
                'id = ?',
                [$notification['id']]
            );
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Notification send failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification
     */
    private function sendEmail($notification) {
        // Implementation depends on your email service
        // This is a placeholder for email sending logic
        
        $data = json_decode($notification['message'], true);
        
        // Use PHP's mail() function or a service like PHPMailer, SendGrid, etc.
        $to = $notification['recipient'];
        $subject = $notification['subject'];
        $message = $this->renderEmailTemplate($data['template'], $data['data']);
        
        $headers = [
            'From: ' . EnvConfig::get('SMTP_FROM_EMAIL', 'noreply@kutahyam.tr'),
            'Reply-To: ' . EnvConfig::get('SMTP_FROM_EMAIL', 'noreply@kutahyam.tr'),
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        $success = mail($to, $subject, $message, implode("\r\n", $headers));
        
        auditEmail($to, $subject, $success, $success ? null : 'Mail function failed');
        
        return $success;
    }
    
    /**
     * Send Telegram notification
     */
    private function sendTelegram($notification) {
        $botToken = EnvConfig::get('TELEGRAM_BOT_TOKEN');
        if (!$botToken) return false;
        
        $chatId = $notification['recipient']; // User ID or chat ID
        $message = $notification['message'];
        
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $success = $httpCode === 200;
        
        auditTelegram($chatId, 'Workflow notification', $success, $success ? null : $response);
        
        return $success;
    }
    
    /**
     * Process pending reminders
     */
    public function processReminders() {
        $reminders = $this->db->fetchAll(
            "SELECT wr.*, wt.*, u.email, u.first_name, u.last_name 
             FROM workflow_reminders wr
             LEFT JOIN workflow_tasks wt ON wr.task_id = wt.id
             LEFT JOIN users u ON wt.assigned_to = u.id
             WHERE wr.status = 'scheduled' AND wr.scheduled_at <= NOW()
             AND wt.status = 'pending'"
        );
        
        $processedCount = 0;
        
        foreach ($reminders as $reminder) {
            try {
                $this->sendReminder($reminder);
                
                // Mark reminder as sent
                $this->db->update(
                    'workflow_reminders',
                    [
                        'status' => 'sent',
                        'sent_at' => DateHelper::now()
                    ],
                    'id = ?',
                    [$reminder['id']]
                );
                
                $processedCount++;
                
            } catch (Exception $e) {
                error_log("Failed to send reminder {$reminder['id']}: " . $e->getMessage());
                
                // Mark as failed if retry count is too high
                if ($reminder['retry_count'] >= 3) {
                    $this->db->update(
                        'workflow_reminders',
                        ['status' => 'failed'],
                        'id = ?',
                        [$reminder['id']]
                    );
                }
            }
        }
        
        return $processedCount;
    }
    
    /**
     * Send reminder notification
     */
    private function sendReminder($reminder) {
        // Get request details
        $request = $this->db->fetchRow(
            "SELECT * FROM purchase_requests WHERE id = ?",
            [$reminder['request_id']]
        );
        
        if (!$request) return;
        
        $metadata = json_decode($reminder['metadata'], true) ?: [];
        
        // Create reminder notification based on type
        $subject = $this->getReminderSubject($reminder['reminder_type'], $request);
        $message = $this->getReminderMessage($reminder, $request);
        
        // Send email reminder
        $this->sendEmail([
            'notification_type' => 'email',
            'recipient' => $reminder['email'],
            'subject' => $subject,
            'message' => json_encode([
                'template' => 'reminder',
                'data' => [
                    'user_name' => $reminder['first_name'] . ' ' . $reminder['last_name'],
                    'request_title' => $request['title'],
                    'request_number' => $request['request_number'],
                    'reminder_type' => $reminder['reminder_type'],
                    'due_date' => $reminder['due_date'],
                    'approve_url' => $this->generateApprovalUrl($reminder['token'], 'approve'),
                    'reject_url' => $this->generateApprovalUrl($reminder['token'], 'reject')
                ]
            ])
        ]);
        
        // Send Telegram reminder if configured
        if (EnvConfig::get('TELEGRAM_BOT_TOKEN')) {
            $this->sendTelegram([
                'notification_type' => 'telegram',
                'recipient' => $reminder['assigned_to'],
                'message' => $this->generateTelegramReminderMessage($reminder, $request)
            ]);
        }
    }
    
    /**
     * Get reminder subject
     */
    private function getReminderSubject($type, $request) {
        $subjects = [
            'first' => 'Hatırlatma: Onay Bekleyen Talep - ' . $request['title'],
            'second' => 'Acil: Son 4 Saat - Onay Bekleyen Talep - ' . $request['title'],
            'final' => 'SON UYARI: Onay Süresi Doldu - ' . $request['title'],
            'escalation' => 'Eskalasyon: Onaylanmamış Talep - ' . $request['title']
        ];
        
        return $subjects[$type] ?? 'Onay Hatırlatması - ' . $request['title'];
    }
    
    /**
     * Get reminder message
     */
    private function getReminderMessage($reminder, $request) {
        $messages = [
            'first' => 'Onayınızı bekleyen talep için 24 saat kaldı.',
            'second' => 'Onayınızı bekleyen talep için sadece 4 saat kaldı. Lütfen acilen işlem yapın.',
            'final' => 'Onay süreniz dolmuştur. Lütfen derhal işlem yapın.',
            'escalation' => 'Bu talep onaylanmadığı için eskalasyona alınmıştır.'
        ];
        
        return $messages[$reminder['reminder_type']] ?? 'Onayınızı bekleyen bir talep bulunmaktadır.';
    }
    
    /**
     * Generate Telegram reminder message
     */
    private function generateTelegramReminderMessage($reminder, $request) {
        $urgencyEmojis = [
            'acil' => '🚨',
            'yüksek' => '⚠️',
            'orta' => '📋',
            'düşük' => '📝'
        ];
        
        $reminderEmojis = [
            'first' => '⏰',
            'second' => '🔔',
            'final' => '🚨',
            'escalation' => '📢'
        ];
        
        $urgency = $request['urgency_level'] ?? 'orta';
        $emoji = $urgencyEmojis[$urgency] ?? '📋';
        $reminderEmoji = $reminderEmojis[$reminder['reminder_type']] ?? '⏰';
        
        return sprintf(
            "%s <b>HATIRLATMA</b> %s\n\n" .
            "📋 <b>Talep:</b> %s\n" .
            "🔢 <b>No:</b> %s\n" .
            "%s <b>Aciliyet:</b> %s\n" .
            "⏰ <b>Son Tarih:</b> %s\n\n" .
            "👤 <b>Onayci:</b> %s\n\n" .
            "<a href='%s'>✅ Onayla</a> | <a href='%s'>❌ Reddet</a>",
            $reminderEmoji,
            $this->getReminderSubject($reminder['reminder_type'], $request),
            FormatHelper::text($request['title']),
            $request['request_number'],
            $emoji,
            ucfirst($urgency),
            DateHelper::formatTurkish($reminder['due_date'], true),
            $reminder['first_name'] . ' ' . $reminder['last_name'],
            $this->generateApprovalUrl($reminder['token'], 'approve'),
            $this->generateApprovalUrl($reminder['token'], 'reject')
        );
    }
    
    /**
     * Generate approval URL
     */
    private function generateApprovalUrl($token, $action) {
        $baseUrl = EnvConfig::get('APP_URL', 'https://satinalma.kutahyam.tr');
        return "{$baseUrl}/approve.php?token={$token}&action={$action}";
    }
    
    /**
     * Generate Telegram message
     */
    private function generateTelegramMessage($task, $requestData, $step) {
        $metadata = json_decode($task['metadata'], true) ?: [];
        
        $stepTexts = [
            'sas_approval' => 'SAS Onayı',
            'gs_approval' => 'Genel Sekreter Onayı'
        ];
        
        $urgencyEmojis = [
            'acil' => '🚨',
            'yüksek' => '⚠️',
            'orta' => '📋',
            'düşük' => '📝'
        ];
        
        $urgency = $requestData['urgency_level'] ?? 'orta';
        $emoji = $urgencyEmojis[$urgency] ?? '📋';
        
        return sprintf(
            "%s <b>%s</b>\n\n" .
            "📋 <b>Talep:</b> %s\n" .
            "🔢 <b>No:</b> %s\n" .
            "%s <b>Aciliyet:</b> %s\n" .
            "⏰ <b>Son Tarih:</b> %s\n\n" .
            "👤 <b>Onayci:</b> %s\n\n" .
            "<a href='%s'>✅ Onayla</a> | <a href='%s'>❌ Reddet</a>",
            $emoji,
            $stepTexts[$step] ?? 'Onay Bekliyor',
            FormatHelper::text($requestData['title']),
            $requestData['request_number'] ?? 'N/A',
            $emoji,
            ucfirst($urgency),
            DateHelper::formatTurkish($task['due_date'], true),
            $metadata['user_name'] ?? 'Kullanıcı',
            $this->generateApprovalUrl($task['token'], 'approve'),
            $this->generateApprovalUrl($task['token'], 'reject')
        );
    }
    
    /**
     * Render email template
     */
    private function renderEmailTemplate($template, $data) {
        // This is a simple template system
        // In production, you might want to use Twig or another templating engine
        
        $templatePath = dirname(__DIR__) . "/templates/email/{$template}.html";
        
        if (!file_exists($templatePath)) {
            // Fallback to simple template
            return $this->getSimpleEmailTemplate($data);
        }
        
        $content = file_get_contents($templatePath);
        
        foreach ($data as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Simple email template fallback
     */
    private function getSimpleEmailTemplate($data) {
        return sprintf(
            "<html><body style='font-family: Arial, sans-serif;'>" .
            "<h2>%s</h2>" .
            "<p>Merhaba %s,</p>" .
            "<p>Onayınızı bekleyen bir satın alma talebi bulunmaktadır:</p>" .
            "<ul>" .
            "<li><strong>Talep:</strong> %s</li>" .
            "<li><strong>Talep No:</strong> %s</li>" .
            "<li><strong>Aciliyet:</strong> %s</li>" .
            "</ul>" .
            "<p><a href='%s' style='background: #22c55e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Onayla</a> " .
            "<a href='%s' style='background: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Reddet</a></p>" .
            "<p>Bu link 48 saat geçerlidir.</p>" .
            "</body></html>",
            $data['subject'] ?? 'Onay Talebi',
            $data['user_name'] ?? 'Kullanıcı',
            $data['request_title'] ?? 'Talep',
            $data['request_number'] ?? 'N/A',
            ucfirst($data['urgency'] ?? 'orta'),
            $data['approve_url'] ?? '#',
            $data['reject_url'] ?? '#'
        );
    }
    
    /**
     * Trigger N8N webhook
     */
    private function triggerWebhook($event, $data) {
        $webhookUrl = EnvConfig::get('N8N_WEBHOOK_URL');
        if (!$webhookUrl) return false;
        
        $payload = array_merge(['event' => $event], $data);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        auditWebhook($webhookUrl, $payload, $response, $httpCode);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
    
    /**
     * Notify requester about approval/rejection
     */
    private function notifyRequester($requestId, $action, $comments) {
        $request = $this->db->fetchRow(
            "SELECT pr.*, u.email, u.first_name, u.last_name 
             FROM purchase_requests pr
             LEFT JOIN users u ON pr.requester_id = u.id
             WHERE pr.id = ?",
            [$requestId]
        );
        
        if (!$request) return;
        
        $subject = $action === 'approved' ? 
            "Talep Onaylandı: {$request['title']}" : 
            "Talep Reddedildi: {$request['title']}";
        
        $this->sendEmail([
            'notification_type' => 'email',
            'recipient' => $request['email'],
            'subject' => $subject,
            'message' => json_encode([
                'template' => 'approval_result',
                'data' => [
                    'user_name' => $request['first_name'] . ' ' . $request['last_name'],
                    'request_title' => $request['title'],
                    'request_number' => $request['request_number'],
                    'action' => $action,
                    'comments' => $comments
                ]
            ])
        ]);
    }
}

// Global workflow instance
function workflow() {
    return WorkflowEngine::getInstance();
}
?>