<?php
// N8N Webhook: New Purchase Request Created
require_once '../auth.php';

$auth = new APIAuth();
$api_data = $auth->authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $auth->sendError(405, 'Only POST method allowed');
}

// Request ID'yi al
$input = json_decode(file_get_contents('php://input'), true);
$request_id = $input['request_id'] ?? $_POST['request_id'] ?? null;

if (!$request_id) {
    $auth->sendError(400, 'Request ID required');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Talep bilgilerini getir
    $query = "SELECT pr.*, u.first_name, u.last_name, u.email as requester_email, u.department,
                     pc.name as category_name, u2.first_name as approver_first_name, 
                     u2.last_name as approver_last_name, u2.email as approver_email
              FROM purchase_requests pr
              LEFT JOIN users u ON pr.requester_id = u.id
              LEFT JOIN purchase_categories pc ON pr.category_id = pc.id
              LEFT JOIN users u2 ON pr.current_approver_id = u2.id
              WHERE pr.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        $auth->sendError(404, 'Request not found');
    }
    
    // Talep kalemlerini getir
    $items_query = "SELECT * FROM request_items WHERE request_id = ?";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->execute([$request_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // N8N için webhook payload hazırla
    $webhook_data = [
        'event' => 'new_purchase_request',
        'request' => [
            'id' => $request['id'],
            'request_number' => $request['request_number'],
            'title' => $request['title'],
            'description' => $request['description'],
            'justification' => $request['justification'],
            'category' => $request['category_name'],
            'urgency_level' => $request['urgency_level'],
            'budget_estimate' => $request['budget_estimate'],
            'status' => $request['status'],
            'created_at' => $request['created_at']
        ],
        'requester' => [
            'name' => $request['first_name'] . ' ' . $request['last_name'],
            'email' => $request['requester_email'],
            'department' => $request['department']
        ],
        'current_approver' => [
            'name' => $request['approver_first_name'] . ' ' . $request['approver_last_name'],
            'email' => $request['approver_email']
        ],
        'items' => $items,
        'total_items' => count($items),
        'total_estimated_cost' => array_sum(array_column($items, 'estimated_total_price'))
    ];
    
    // Webhook URL'leri getir
    $webhook_query = "SELECT webhook_url FROM webhook_configs WHERE event_type = 'new_request' AND is_active = 1";
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
            'X-Webhook-Event: new_purchase_request'
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
        
        // Webhook log kaydet
        $log_query = "INSERT INTO webhook_logs (event_type, webhook_url, payload, response_code, response_body, created_at) 
                     VALUES ('new_request', ?, ?, ?, ?, NOW())";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->execute([$webhook_url, json_encode($webhook_data), $http_code, $response]);
    }
    
    $auth->sendSuccess([
        'request_id' => $request_id,
        'webhooks_sent' => count($sent_webhooks),
        'webhook_results' => $sent_webhooks,
        'payload' => $webhook_data
    ], 'Webhooks sent successfully');
    
} catch (Exception $e) {
    $auth->sendError(500, 'Internal server error: ' . $e->getMessage());
}
?>