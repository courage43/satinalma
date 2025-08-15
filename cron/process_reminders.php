<?php
/**
 * Process Workflow Reminders Cron Job
 * This script should be run every 15 minutes via cron
 * Example crontab: */15 * * * * /usr/bin/php /path/to/process_reminders.php
 */

// Set script to run for maximum 5 minutes
set_time_limit(300);

// Include required files
require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/lib/workflow_engine.php';
require_once dirname(__DIR__) . '/lib/audit_log.php';

// Log script start
error_log('Starting reminder processing cron job at ' . date('Y-m-d H:i:s'));

try {
    // Get workflow engine instance
    $workflow = WorkflowEngine::getInstance();
    
    // Process reminders
    $processed = $workflow->processReminders();
    
    // Log success
    audit()->log(
        AuditLogger::EVENT_SYSTEM_CONFIG,
        "Reminder processing cron completed successfully",
        [
            'level' => AuditLogger::LEVEL_INFO,
            'additional_data' => [
                'processed_count' => $processed,
                'execution_time' => microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]
            ]
        ]
    );
    
    error_log("Reminder processing completed successfully. Processed: {$processed} reminders");
    
} catch (Exception $e) {
    // Log error
    audit()->log(
        AuditLogger::EVENT_SYSTEM_CONFIG,
        "Reminder processing cron failed: " . $e->getMessage(),
        [
            'level' => AuditLogger::LEVEL_ERROR,
            'additional_data' => [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]
        ]
    );
    
    error_log('Reminder processing failed: ' . $e->getMessage());
    exit(1);
}

exit(0);
?>