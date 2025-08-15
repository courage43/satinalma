<?php
// N8N Webhook: Approval Status Update
require_once '../auth.php';

$auth = new APIAuth();
$api_data = $auth->authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $auth->sendError(405, 'Only POST method allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
$request_id = $input['request_id'] ?? $_POST['request_id'] ?? null;
$old_status = $input['old_status'] ?? $_POST['old_status'] ?? null;
$new_status = $input['new_status'] ?? $_POST['new_status'] ?? null;
$approver_id = $input['approver_id'] ?? $_POST['approver_id'] ?? null;
$comments = $input['comments'] ?? $_POST['comments'] ?? '';

if (!$request_id || !$new_status) {
    $auth->sendError(400, 'Request ID and new status required');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Talep ve onayci bilgilerini getir
    $query = "SELECT pr.*, u.first_name, u.last_name, u.email as requester_email, u.department,
                     pc.name as category_name, u2.first_name as approver_first_name, 
                     u2.last_name as approver_last_name, u2.email as approver_email
              FROM purchase_requests pr
              LEFT JOIN users u ON pr.requester_id = u.id
              LEFT JOIN purchase_categories pc ON pr.category_id = pc.id
              LEFT JOIN users u2 ON u2.id = ?
              WHERE pr.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$approver_id, $request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        $auth->sendError(404, 'Request not found');
    }
    
    // Onay geçmişini getir
    $approval_history = "SELECT aw.*, u.first_name, u.last_name, u.email 
                        FROM approval_workflow aw 
                        LEFT JOIN users u ON aw.approver_id = u.id 
                        WHERE aw.request_id = ? 
                        ORDER BY aw.approval_order";
    $approval_stmt = $conn->prepare($approval_history);
    $approval_stmt->execute([$request_id]);
    $approvals = $approval_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Status türünü belirle
    $action = '';
    if ($new_status == 'onaylandı' && $old_status != 'onaylandı') {
        $action = 'approved';
    } elseif ($new_status == 'reddedildi') {
        $action = 'rejected';
    } elseif (strpos($new_status, 'onayı') !== false) {
        $action = 'forwarded_for_approval';
    }
    
    // Webhook payload hazırla
    $webhook_data = [
        'event' => 'approval_update',
        'action' => $action,
        'request' => [
            'id' => $request['id'],
            'request_number' => $request['request_number'],
            'title' => $request['title'],
            'old_status' => $old_status,
            'new_status' => $new_status,
            'urgency_level' => $request['urgency_level']
        ],
        'requester' => [
            'name' => $request['first_name'] . ' ' . $request['last_name'],
            'email' => $request['requester_email'],
            'department' => $request['department']
        ],
        'approver' => [
            'id' => $approver_id,
            'name' => $request['approver_first_name'] . ' ' . $request['approver_last_name'],
            'email' => $request['approver_email'],
            'comments' => $comments
        ],
        'approval_history' => array_map(function($approval) {
            return [
                'order' => $approval['approval_order'],
                'approver_name' => $approval['first_name'] . ' ' . $approval['last_name'],
                'status' => $approval['status'],
                'approved_at' => $approval['approved_at'],
                'comments' => $approval['comments']
            ];
        }, $approvals),
        'timestamp' => date('c')
    ];
    
    // Webhook URL'leri getir
    $webhook_query = "SELECT webhook_url FROM webhook_configs WHERE event_type = 'approval_update' AND is_active = 1";
    $webhook_stmt = $conn->prepare($webhook_query);
    $webhook_stmt->execute();
    $webhooks = $webhook_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $sent_webhooks = [];
    
    foreach ($webhooks as $webhook_url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Event: approval_update',
            'X-Webhook-Action: ' . $action
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $sent_webhooks[] = [
            'url' => $webhook_url,
            'status' => $http_code,
            'response' => $response
        ];
        
        // Log kaydet
        $log_query = "INSERT INTO webhook_logs (event_type, webhook_url, payload, response_code, response_body, created_at) 
                     VALUES ('approval_update', ?, ?, ?, ?, NOW())";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->execute([$webhook_url, json_encode($webhook_data), $http_code, $response]);
    }
    
    $auth->sendSuccess([
        'request_id' => $request_id,
        'action' => $action,
        'webhooks_sent' => count($sent_webhooks),
        'webhook_results' => $sent_webhooks
    ], 'Approval update webhooks sent successfully');
    
} catch (Exception $e) {
    $auth->sendError(500, 'Internal server error: ' . $e->getMessage());
}
?>