<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XBoard 多租户演示</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 32px;
        }
        .subtitle {
            color: #666;
            font-size: 16px;
        }
        .tenant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .tenant-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        .tenant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        .tenant-card h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 24px;
        }
        .tenant-card p {
            color: #666;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .tenant-card .domain {
            color: #999;
            font-size: 12px;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .info-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .info-box h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .info-box ul {
            list-style: none;
            padding-left: 0;
        }
        .info-box li {
            padding: 10px;
            margin-bottom: 8px;
            background: #f5f5f5;
            border-radius: 5px;
            font-family: monospace;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status.active {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 XBoard 多租户系统演示</h1>
            <p class="subtitle">一套后台，多个租户，共享节点和配置</p>
        </div>

        <div class="tenant-grid">
            <?php
            $tenants = [
                [
                    'name' => '租户 A',
                    'domain' => 'tenant1.xboard.test',
                    'uuid' => 'e53af425-3a05-4370-9fd1-ddbd57cffc6f',
                    'admin' => 'admin@tenant1.com',
                    'password' => 'admin123'
                ],
                [
                    'name' => '租户 B',
                    'domain' => 'tenant2.xboard.test',
                    'uuid' => '6cf5cbe9-c97d-49f6-9727-6d3d59c737d8',
                    'admin' => 'admin@tenant2.com',
                    'password' => 'admin123'
                ],
                [
                    'name' => '租户 C',
                    'domain' => 'tenant3.xboard.test',
                    'uuid' => '0d14493f-2037-4420-be7c-5e1b5103e0ab',
                    'admin' => 'admin@tenant3.com',
                    'password' => 'admin123'
                ]
            ];

            foreach ($tenants as $tenant) {
                echo '<div class="tenant-card">';
                echo '<h2>' . $tenant['name'] . '</h2>';
                echo '<span class="status active">运行中</span>';
                echo '<p><strong>域名:</strong> ' . $tenant['domain'] . '</p>';
                echo '<p><strong>管理员:</strong> ' . $tenant['admin'] . '</p>';
                echo '<p><strong>密码:</strong> ' . $tenant['password'] . '</p>';
                echo '<p class="domain"><strong>UUID:</strong> ' . $tenant['uuid'] . '</p>';
                echo '<a href="http://' . $tenant['domain'] . ':8080/" class="btn" target="_blank">打开 ' . $tenant['name'] . '</a>';
                echo '</div>';
            }
            ?>
        </div>

        <div class="info-box">
            <h3>📋 配置说明</h3>
            <p style="margin-bottom: 15px; color: #666;">要访问各租户页面，请先在hosts文件中添加以下记录：</p>
            <ul>
                <li>127.0.0.1 tenant1.xboard.test</li>
                <li>127.0.0.1 tenant2.xboard.test</li>
                <li>127.0.0.1 tenant3.xboard.test</li>
            </ul>
            <p style="margin-top: 15px; color: #999; font-size: 14px;">
                <strong>Windows:</strong> C:\Windows\System32\drivers\etc\hosts<br>
                <strong>Linux/Mac:</strong> /etc/hosts
            </p>
        </div>
    </div>
</body>
</html>

