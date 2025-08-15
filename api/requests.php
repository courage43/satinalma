<?php
// N8N API: Purchase Requests CRUD Operations
require_once 'auth.php';

$auth = new APIAuth();
$api_data = $auth->authenticate();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            handleGetRequests($conn, $auth);
            break;
            
        case 'POST':
            handleCreateRequest($conn, $auth, $input);
            break;
            
        case 'PUT':
            handleUpdateRequest($conn, $auth, $input);
            break;
            
        case 'DELETE':
            handleDeleteRequest($conn, $auth, $input);
            break;
            
        default:
            $auth->sendError(405, 'Method not allowed');
    }
    
} catch (Exception $e) {
    $auth->sendError(500, 'Internal server error: ' . $e->getMessage());
}

function handleGetRequests($conn, $auth) {
    $request_id = $_GET['id'] ?? null;
    $status = $_GET['status'] ?? null;
    $requester_id = $_GET['requester_id'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    if ($request_id) {
        // Tek talep getir
        $query = "SELECT pr.*, u.first_name, u.last_name, u.email as requester_email, 
                         pc.name as category_name,
                         COUNT(ri.id) as item_count,
                         SUM(ri.estimated_total_price) as total_estimated_cost
                  FROM purchase_requests pr
                  LEFT JOIN users u ON pr.requester_id = u.id
                  LEFT JOIN purchase_categories pc ON pr.category_id = pc.id
                  LEFT JOIN request_items ri ON pr.id = ri.request_id
                  WHERE pr.id = ?
                  GROUP BY pr.id";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            $auth->sendError(404, 'Request not found');
        }
        
        // Talep kalemlerini getir
        $items_query = "SELECT * FROM request_items WHERE request_id = ? ORDER BY id";
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->execute([$request_id]);
        $request['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Onay geçmişini getir
        $approval_query = "SELECT aw.*, u.first_name, u.last_name FROM approval_workflow aw 
                          LEFT JOIN users u ON aw.approver_id = u.id 
                          WHERE aw.request_id = ? ORDER BY aw.approval_order";
        $approval_stmt = $conn->prepare($approval_query);
        $approval_stmt->execute([$request_id]);
        $request['approval_history'] = $approval_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $auth->sendSuccess($request, 'Request retrieved successfully');
        
    } else {
        // Talepler listesi
        $where_conditions = [];
        $params = [];
        
        if ($status) {
            $where_conditions[] = "pr.status = ?";
            $params[] = $status;
        }
        
        if ($requester_id) {
            $where_conditions[] = "pr.requester_id = ?";
            $params[] = $requester_id;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "SELECT pr.*, u.first_name, u.last_name, pc.name as category_name,
                         COUNT(ri.id) as item_count,
                         SUM(ri.estimated_total_price) as total_estimated_cost
                  FROM purchase_requests pr
                  LEFT JOIN users u ON pr.requester_id = u.id
                  LEFT JOIN purchase_categories pc ON pr.category_id = pc.id
                  LEFT JOIN request_items ri ON pr.id = ri.request_id
                  $where_clause
                  GROUP BY pr.id
                  ORDER BY pr.created_at DESC
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Toplam sayı
        $count_query = "SELECT COUNT(DISTINCT pr.id) as total FROM purchase_requests pr $where_clause";
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->execute(array_slice($params, 0, -2)); // limit ve offset'i çıkar
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $auth->sendSuccess([
            'requests' => $requests,
            'pagination' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ], 'Requests retrieved successfully');
    }
}

function handleCreateRequest($conn, $auth, $input) {
    $required_fields = ['title', 'description', 'requester_id', 'category_id'];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $auth->sendError(400, "Field '$field' is required");
        }
    }
    
    try {
        $conn->beginTransaction();
        
        // Talep numarası oluştur
        $request_number = 'ST-' . date('Y') . '-' . sprintf('%06d', mt_rand(1, 999999));
        
        // Ana talep kaydı
        $request_query = "INSERT INTO purchase_requests 
                         (request_number, requester_id, title, description, justification, 
                          category_id, urgency_level, budget_estimate, requested_delivery_date, status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'beklemede')";
        
        $stmt = $conn->prepare($request_query);
        $stmt->execute([
            $request_number,
            $input['requester_id'],
            $input['title'],
            $input['description'],
            $input['justification'] ?? '',
            $input['category_id'],
            $input['urgency_level'] ?? 'orta',
            $input['budget_estimate'] ?? null,
            $input['requested_delivery_date'] ?? null
        ]);
        
        $request_id = $conn->lastInsertId();
        
        // Talep kalemlerini kaydet
        if (!empty($input['items'])) {
            $items_query = "INSERT INTO request_items 
                           (request_id, item_name, description, quantity, unit, 
                            estimated_unit_price, estimated_total_price, specifications, brand_preference) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $items_stmt = $conn->prepare($items_query);
            
            foreach ($input['items'] as $item) {
                $total_price = ($item['quantity'] ?? 1) * ($item['estimated_unit_price'] ?? 0);
                $items_stmt->execute([
                    $request_id,
                    $item['item_name'],
                    $item['description'] ?? '',
                    $item['quantity'] ?? 1,
                    $item['unit'] ?? 'Adet',
                    $item['estimated_unit_price'] ?? 0,
                    $total_price,
                    $item['specifications'] ?? '',
                    $item['brand_preference'] ?? ''
                ]);
            }
        }
        
        // Onay workflow'unu başlat
        setupApprovalWorkflow($conn, $request_id);
        
        $conn->commit();
        
        // Webhook tetikle (eğer yapılandırılmışsa)
        triggerWebhook('new_request', ['request_id' => $request_id]);
        
        $auth->sendSuccess([
            'request_id' => $request_id,
            'request_number' => $request_number
        ], 'Request created successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function setupApprovalWorkflow($conn, $request_id) {
    // Birim sorumlusu onayı
    $workflow_query = "INSERT INTO approval_workflow (request_id, approver_id, approval_order, status) 
                      SELECT ?, u.id, 1, 'beklemede' FROM users u WHERE u.role_id = 
                      (SELECT id FROM roles WHERE name = 'birim_sorumlusu') LIMIT 1";
    $stmt = $conn->prepare($workflow_query);
    $stmt->execute([$request_id]);
    
    // Genel sekreter onayı
    $workflow_query2 = "INSERT INTO approval_workflow (request_id, approver_id, approval_order, status) 
                       SELECT ?, u.id, 2, 'beklemede' FROM users u WHERE u.role_id = 
                       (SELECT id FROM roles WHERE name = 'genel_sekreter') LIMIT 1";
    $stmt2 = $conn->prepare($workflow_query2);
    $stmt2->execute([$request_id]);
    
    // İlk onayciyi current_approver olarak ayarla
    $update_query = "UPDATE purchase_requests pr 
                    SET current_approver_id = (
                        SELECT aw.approver_id FROM approval_workflow aw 
                        WHERE aw.request_id = ? AND aw.approval_order = 1
                    ) WHERE pr.id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->execute([$request_id, $request_id]);
}

function triggerWebhook($event_type, $data) {
    // Basit webhook tetiklemesi - gerçek uygulamada queue sistemi kullanılabilir
    $webhook_url = "http://localhost/api/webhooks/{$event_type}.php";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

function handleUpdateRequest($conn, $auth, $input) {
    // Talep güncelleme implementasyonu
    $auth->sendError(501, 'Update not implemented yet');
}

function handleDeleteRequest($conn, $auth, $input) {
    // Talep silme implementasyonu
    $auth->sendError(501, 'Delete not implemented yet');
}
?>