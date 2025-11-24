<?php

// 测试清除所有日志的API调用

$url = 'http://localhost:8080/api/v2/admin/system/clearSystemLog';
$data = [
    'days' => 0,
    'limit' => 100000,
    'clear_all' => true,
    'level' => 'all'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Super-Admin: 1'  // 添加超级管理员头
]);

echo "发送请求到: $url\n";
echo "请求参数: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP状态码: $httpCode\n";
echo "响应内容: " . $response . "\n";

// 解析响应
$responseData = json_decode($response, true);
if ($responseData) {
    echo "\n解析后的响应:\n";
    echo "  - 成功: " . ($responseData['success'] ?? 'N/A') . "\n";
    if (isset($responseData['data'])) {
        echo "  - 删除数量: " . ($responseData['data']['deleted_count'] ?? 'N/A') . "\n";
        echo "  - 总数: " . ($responseData['data']['total_count'] ?? 'N/A') . "\n";
        echo "  - 剩余数量: " . ($responseData['data']['remaining_count'] ?? 'N/A') . "\n";
    }
}

