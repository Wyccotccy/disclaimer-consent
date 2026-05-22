# 免责声明同意系统 — 烬熵 Cinder

一个轻量级的免责声明同意管理系统，专为 QQ 机器人（或其他需要用户确认免责声明的服务）设计。

## 功能

- 📄 完整的免责声明展示页面（v4.0）
- ✅ 用户点击同意后记录到 SQLite 数据库
- 🔍 API 接口供机器人查询用户的同意状态
- 🛡️ 防重复提交（同 QQ/IP/设备指纹）
- 🔧 内置简易管理面板（可查看/删除记录）

## 技术栈

- PHP 8.0+
- SQLite
- 无外部依赖

## 快速安装

```bash
curl -sSL https://raw.githubusercontent.com/Wyccotccy/disclaimer-consent/main/install.sh | bash
```

或手动下载后运行：

```bash
cd /www/wwwroot/your-site
git clone https://github.com/Wyccotccy/disclaimer-consent.git disclaimer
php disclaimer/install.php
```

## 文件结构

```
disclaimer/
├── disclaimer/
│   ├── index.php        # 免责声明同意页面（主页面）
│   └── style.css        # 样式表
├── api/
│   └── disclaimer.php   # 同意状态查询 API
├── data/
│   └── schema.sql       # 数据库建表语句
├── admin/
│   └── index.php        # 管理面板
├── install.sh           # 一键安装脚本
├── install.php          # PHP 安装引导
└── README.md
```

## API 使用

```http
GET /api/disclaimer?qq=123456
```

响应示例：

```json
{
  "ok": true,
  "data": {
    "consented": true,
    "version": "v4.0",
    "consented_at": "2026-05-20 12:34:56"
  }
}
```

## 管理面板

访问 `?admin=cinder2026` 参数进入管理面板（可在 `disclaimer/index.php` 中修改 `$admin_token` 值）。

## License

MIT
