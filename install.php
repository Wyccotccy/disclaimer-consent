<?php
/**
 * install.php - PHP Web 安装引导
 * 
 * 浏览器访问此文件即可完成安装。
 */

// 检测
$errors = [];
$php_ok = version_compare(PHP_VERSION, '8.0', '>=');
if (!$php_ok) $errors[] = "PHP 版本过低 ({PHP_VERSION})，需要 8.0+";

$pdo_ok = extension_loaded('pdo_sqlite');
if (!$pdo_ok) $errors[] = "缺少 PDO SQLite 扩展";

$data_dir = __DIR__ . '/data';
$data_writable = is_writable(dirname($data_dir)) || (is_dir($data_dir) && is_writable($data_dir));

// 执行安装
$installed = false;
$install_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    try {
        if (!is_dir($data_dir)) {
            mkdir($data_dir, 0755, true);
        }
        $db = new PDO("sqlite:$data_dir/disclaimer.db", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $db->exec("PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;");
        $db->exec("
            CREATE TABLE IF NOT EXISTS disclaimer_consents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                qq TEXT NOT NULL,
                ip_address TEXT NOT NULL DEFAULT '',
                device_fp TEXT NOT NULL DEFAULT '',
                version TEXT NOT NULL DEFAULT 'v4.0',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE UNIQUE INDEX IF NOT EXISTS idx_disclaimer_qq ON disclaimer_consents(qq);
            CREATE INDEX IF NOT EXISTS idx_disclaimer_ip ON disclaimer_consents(ip_address);
            CREATE INDEX IF NOT EXISTS idx_disclaimer_fp ON disclaimer_consents(device_fp);
        ");
        $installed = true;
        $install_msg = '安装成功！数据库已初始化。';
    } catch (Exception $e) {
        $errors[] = '安装失败: ' . $e->getMessage();
    }
}

// 生成 Nginx 配置
$current_dir = __DIR__;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>免责声明系统 - 安装向导</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans SC",sans-serif;background:#F5EFE6;color:#1A1A1A;line-height:1.7;padding:40px 20px}
.container{max-width:700px;margin:0 auto}
h1{font-size:1.5rem;margin-bottom:8px}
.desc{color:#6B6B6B;font-size:0.9rem;margin-bottom:24px}
.card{background:#fff;border:1px solid #E5DDD0;border-radius:12px;padding:24px;margin-bottom:16px}
.card h2{font-size:1.05rem;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #EBE3D4}
.err{background:#FDF2F2;border:1px solid #E8B4B4;color:#B91C1C;padding:10px 14px;border-radius:8px;font-size:0.85rem;margin-bottom:8px}
.ok{background:#F0F7EC;border:1px solid #B8D4A8;color:#2D6B1E;padding:10px 14px;border-radius:8px;font-size:0.85rem;margin-bottom:8px}
code{background:#F5EFE6;padding:2px 6px;border-radius:4px;font-size:0.85rem}
pre{background:#F5EFE6;padding:14px;border-radius:8px;font-size:0.82rem;overflow-x:auto;margin-top:8px}
.btn{display:inline-block;padding:10px 24px;background:#8B5E3C;color:#fff;border:none;border-radius:8px;font-size:0.95rem;cursor:pointer}
.btn:hover{background:#A0704C}
.btn:disabled{background:#D4C9B8;cursor:not-allowed}
</style>
</head>
<body>
<div class="container">
<h1>📋 免责声明同意系统</h1>
<p class="desc">一键安装向导</p>

<?php if ($installed): ?>
<div class="card">
<div class="ok">✅ <?=$install_msg?></div>
<h2>下一步</h2>
<p>1. 配置 Nginx 指向本目录：</p>
<pre>location /disclaimer/ {
    alias <?=$current_dir?>/disclaimer/;
    index index.php;
}

location /api/ {
    alias <?=$current_dir?>/api/;
}</pre>
<p style="margin-top:12px">2. 访问 <a href="disclaimer/">disclaimer/</a> 查看免责声明页面</p>
<p>3. 测试 API：<br><code>curl 'https://your-domain.com/api/disclaimer?qq=123456'</code></p>
<p style="margin-top:8px;color:#6B6B6B;font-size:0.85rem">⚠ 记得修改 disclaimer/index.php 中的 <code>\$admin_token</code> 密码！</p>
</div>
<?php else: ?>

<?php foreach ($errors as $e): ?>
<div class="err">✗ <?=$e?></div>
<?php endforeach; ?>

<div class="card">
<h2>环境检测</h2>
<p>✓ PHP <?=PHP_VERSION?> <?=$php_ok ? '✅' : '❌'?></p>
<p>✓ PDO SQLite <?=$pdo_ok ? '✅' : '❌'?></p>
</div>

<div class="card">
<h2>安装</h2>
<p style="font-size:0.85rem;color:#4B4B4B;margin-bottom:12px">点击安装将创建 SQLite 数据库并建立数据表。</p>
<form method="post">
<button type="submit" class="btn" <?=!empty($errors)?'disabled':''?>>开始安装</button>
</form>
</div>
<?php endif; ?>
</div>
</body>
</html>
