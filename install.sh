#!/bin/bash
# ================================================
# 免责声明同意系统 - 一键安装脚本
# ================================================
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  免责声明同意系统 - 一键安装脚本${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# 检测 PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}✗ 未检测到 PHP，请先安装 PHP 8.0+${NC}"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo -e "${GREEN}✓ 检测到 PHP ${PHP_VERSION}${NC}"

# 检测 PDO SQLite 支持
if ! php -m | grep -qi "pdo_sqlite"; then
    echo -e "${RED}✗ PHP 缺少 PDO SQLite 扩展${NC}"
    echo "  请安装: apt install php-sqlite (Debian/Ubuntu)"
    echo "  或: yum install php-pdo php-sqlite (CentOS/RHEL)"
    exit 1
fi
echo -e "${GREEN}✓ PDO SQLite 扩展已安装${NC}"

# 获取安装目录
DEFAULT_DIR="/www/wwwroot/disclaimer"
echo ""
echo -e "${YELLOW}安装目录 (默认: ${DEFAULT_DIR}):${NC}"
read -r INSTALL_DIR
if [ -z "$INSTALL_DIR" ]; then
    INSTALL_DIR="$DEFAULT_DIR"
fi

# 如果目录已存在
if [ -d "$INSTALL_DIR" ]; then
    echo -e "${YELLOW}⚠ 目录 $INSTALL_DIR 已存在${NC}"
    echo -n "是否覆盖？(y/N): "
    read -r OVERWRITE
    if [ "$OVERWRITE" != "y" ] && [ "$OVERWRITE" != "Y" ]; then
        echo -e "${RED}已取消安装${NC}"
        exit 1
    fi
fi

# 创建目录结构
echo ""
echo -e "${YELLOW}正在创建目录结构...${NC}"
mkdir -p "$INSTALL_DIR"/{disclaimer,api,data,admin}
echo -e "${GREEN}✓ 目录已创建${NC}"

# 获取源代码（自身目录或从 GitHub 下载）
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
if [ -f "$SCRIPT_DIR/disclaimer/index.php" ]; then
    echo -e "${YELLOW}正在从本地复制文件...${NC}"
    cp -r "$SCRIPT_DIR/disclaimer"/* "$INSTALL_DIR/disclaimer/"
    cp -r "$SCRIPT_DIR/api"/* "$INSTALL_DIR/api/"
    [ -f "$SCRIPT_DIR/admin/index.php" ] && cp -r "$SCRIPT_DIR/admin"/* "$INSTALL_DIR/admin/"
    chmod -R 755 "$INSTALL_DIR"
else
    echo -e "${YELLOW}正在从 GitHub 下载...${NC}"
    TMP_DIR=$(mktemp -d)
    cd "$TMP_DIR"
    if command -v git &> /dev/null; then
        git clone --depth 1 https://github.com/Wyccotccy/disclaimer-consent.git .
    elif command -v curl &> /dev/null; then
        curl -sL https://github.com/Wyccotccy/disclaimer-consent/archive/main.tar.gz | tar xz --strip=1
    elif command -v wget &> /dev/null; then
        wget -qO- https://github.com/Wyccotccy/disclaimer-consent/archive/main.tar.gz | tar xz --strip=1
    else
        echo -e "${RED}✗ 无法下载，请安装 git 或 curl${NC}"
        rm -rf "$TMP_DIR"
        exit 1
    fi
    cp -r disclaimer/* "$INSTALL_DIR/disclaimer/"
    cp -r api/* "$INSTALL_DIR/api/"
    [ -d admin ] && cp -r admin/* "$INSTALL_DIR/admin/" 2>/dev/null || true
    rm -rf "$TMP_DIR"
    chmod -R 755 "$INSTALL_DIR"
fi

# 设置 data 目录权限（PHP 需要写入 SQLite）
chmod 755 "$INSTALL_DIR/data"
echo -e "${GREEN}✓ 文件已部署${NC}"

# 初始化数据库
echo ""
echo -e "${YELLOW}正在初始化数据库...${NC}"
php -r "
\$db = new PDO('sqlite:$INSTALL_DIR/data/disclaimer.db');
\$db->exec('PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;');
\$db->exec('CREATE TABLE IF NOT EXISTS disclaimer_consents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    qq TEXT NOT NULL,
    ip_address TEXT NOT NULL DEFAULT \"\",
    device_fp TEXT NOT NULL DEFAULT \"\",
    version TEXT NOT NULL DEFAULT \"v4.0\",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');
\$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_disclaimer_qq ON disclaimer_consents(qq)');
\$db->exec('CREATE INDEX IF NOT EXISTS idx_disclaimer_ip ON disclaimer_consents(ip_address)');
\$db->exec('CREATE INDEX IF NOT EXISTS idx_disclaimer_fp ON disclaimer_consents(device_fp)');
echo 'OK';
"
echo -e "${GREEN}✓ 数据库已初始化${NC}"

# 设置安全权限
chmod 644 "$INSTALL_DIR/data/disclaimer.db"

# 输出 Nginx 配置示例
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  安装完成！${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "安装目录: ${YELLOW}$INSTALL_DIR${NC}"
echo ""
echo -e "${YELLOW}Nginx 配置参考 (添加到 server block 中):${NC}"
echo ""
echo "    # 免责声明页面"
echo "    location /disclaimer/ {"
echo "        alias ${INSTALL_DIR}/disclaimer/;"
echo "        index index.php;"
echo "    }"
echo ""
echo "    # API 接口"
echo "    location /api/ {"
echo "        alias ${INSTALL_DIR}/api/;"
echo "    }"
echo ""
echo -e "${YELLOW}使用说明:${NC}"
echo "  - 免责声明页面: https://your-domain.com/disclaimer/"
echo "  - API: https://your-domain.com/api/disclaimer?qq=你的QQ号"
echo "  - 管理面板: 在 disclaimer 页面后加上 ?admin=你的密码"
echo "    (默认密码: cinder2026，请务必修改!)"
echo ""

exit 0
