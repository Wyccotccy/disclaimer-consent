-- 免责声明同意系统 - 数据库建表语句
-- SQLite

CREATE TABLE IF NOT EXISTS disclaimer_consents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    qq TEXT NOT NULL,
    ip_address TEXT NOT NULL DEFAULT '',
    device_fp TEXT NOT NULL DEFAULT '',
    version TEXT NOT NULL DEFAULT 'v4.0',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 唯一约束：防止重复提交
CREATE UNIQUE INDEX IF NOT EXISTS idx_disclaimer_qq ON disclaimer_consents(qq);
CREATE INDEX IF NOT EXISTS idx_disclaimer_ip ON disclaimer_consents(ip_address);
CREATE INDEX IF NOT EXISTS idx_disclaimer_fp ON disclaimer_consents(device_fp);
