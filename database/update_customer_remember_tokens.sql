CREATE TABLE IF NOT EXISTS cliente_remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    criado_em DATETIME NOT NULL,
    expira_em DATETIME NOT NULL,
    user_agent VARCHAR(255) NULL,
    ip VARCHAR(64) NULL,
    UNIQUE KEY ux_cliente_remember_tokens_hash (token_hash),
    KEY idx_cliente_remember_tokens_cliente (cliente_id)
);
