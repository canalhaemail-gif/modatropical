CREATE TABLE IF NOT EXISTS admin_remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    criado_em DATETIME NOT NULL,
    expira_em DATETIME NOT NULL,
    user_agent VARCHAR(255) NULL,
    ip VARCHAR(64) NULL,
    UNIQUE KEY ux_admin_remember_tokens_hash (token_hash),
    KEY idx_admin_remember_tokens_admin (admin_id)
);
