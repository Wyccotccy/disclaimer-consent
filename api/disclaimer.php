<?php
/**
 * api/disclaimer.php - 免责声明同意状态检查 API
 * 
 * 供 AstrBot 或其他 QQ 机器人调用，查询用户的同意状态。
 * 
 * 使用: GET /api/disclaimer?qq=123456
 * 
 * 返回:
 *   已同意: { ok: true, data: { consented: true, version: "v4.0", consented_at: "2026-05-20 12:34:56" } }
 *   未同意: { ok: true, data: { consented: false, version: null, consented_at: null } }
 *   参数错误: { ok: false, msg: "无效的QQ号" }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 验证参数
$qq = trim($_GET['qq'] ?? '');
if (!preg_match('/^[1-9]\d{4,10}$/', $qq)) {
    echo json_encode(['ok' => false, 'msg' => '无效的QQ号'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 数据库路径（向上两级到项目根目录的 data/ 下）
$dbPath = dirname(__DIR__) . '/data/disclaimer.db';

try {
    if (!file_exists($dbPath)) {
        // 数据库不存在时返回未同意
        echo json_encode([
            'ok' => true,
            'data' => [
                'consented' => false,
                'version' => null,
                'consented_at' => null
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $db->prepare('SELECT version, created_at FROM disclaimer_consents WHERE qq = ?');
    $stmt->execute([$qq]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'ok' => true,
            'data' => [
                'consented' => true,
                'version' => $row['version'],
                'consented_at' => $row['created_at']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'ok' => true,
            'data' => [
                'consented' => false,
                'version' => null,
                'consented_at' => null
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    error_log('[disclaimer API] DB Error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
}
