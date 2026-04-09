ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS desconto_percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER preco;

CREATE TABLE IF NOT EXISTS cupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(80) NOT NULL UNIQUE,
    nome VARCHAR(140) NOT NULL,
    descricao VARCHAR(255) NULL,
    tipo VARCHAR(32) NOT NULL DEFAULT 'percent',
    escopo VARCHAR(32) NOT NULL DEFAULT 'order',
    valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal_minimo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    limite_resgates INT UNSIGNED NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    notificado_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cupons
    ADD COLUMN IF NOT EXISTS limite_resgates INT UNSIGNED NULL AFTER subtotal_minimo;

CREATE TABLE IF NOT EXISTS cupom_produtos (
    cupom_id INT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (cupom_id, produto_id),
    CONSTRAINT fk_cupom_produtos_cupom
        FOREIGN KEY (cupom_id) REFERENCES cupons(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cupom_produtos_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupom_marcas (
    cupom_id INT UNSIGNED NOT NULL,
    marca_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (cupom_id, marca_id),
    CONSTRAINT fk_cupom_marcas_cupom
        FOREIGN KEY (cupom_id) REFERENCES cupons(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cupom_marcas_marca
        FOREIGN KEY (marca_id) REFERENCES marcas(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliente_notificacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(32) NOT NULL DEFAULT 'geral',
    titulo VARCHAR(140) NOT NULL,
    mensagem VARCHAR(255) NOT NULL,
    link_url VARCHAR(255) NULL,
    payload_json LONGTEXT NULL,
    lida_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cliente_notificacoes_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliente_cupons (
    cliente_id INT UNSIGNED NOT NULL,
    cupom_id INT UNSIGNED NOT NULL,
    resgatado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cliente_id, cupom_id),
    CONSTRAINT fk_cliente_cupons_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cliente_cupons_cupom
        FOREIGN KEY (cupom_id) REFERENCES cupons(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS cupom_id INT UNSIGNED NULL AFTER payment_last_webhook_at,
    ADD COLUMN IF NOT EXISTS cupom_codigo VARCHAR(80) NULL AFTER cupom_id,
    ADD COLUMN IF NOT EXISTS cupom_nome VARCHAR(140) NULL AFTER cupom_codigo,
    ADD COLUMN IF NOT EXISTS cupom_tipo VARCHAR(32) NULL AFTER cupom_nome,
    ADD COLUMN IF NOT EXISTS cupom_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER cupom_tipo,
    ADD COLUMN IF NOT EXISTS cupom_frete_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER cupom_desconto;

SET @has_fk_pedidos_cupom := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedidos'
      AND CONSTRAINT_NAME = 'fk_pedidos_cupom'
);
SET @sql_fk_pedidos_cupom := IF(
    @has_fk_pedidos_cupom = 0,
    'ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_cupom FOREIGN KEY (cupom_id) REFERENCES cupons(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_fk_pedidos_cupom FROM @sql_fk_pedidos_cupom;
EXECUTE stmt_fk_pedidos_cupom;
DEALLOCATE PREPARE stmt_fk_pedidos_cupom;
