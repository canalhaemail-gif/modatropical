CREATE TABLE IF NOT EXISTS pedidos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NULL,
    codigo_rastreio VARCHAR(32) NOT NULL UNIQUE,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    fulfillment_method VARCHAR(20) NOT NULL DEFAULT 'delivery',
    payment_method VARCHAR(20) NULL,
    payment_provider VARCHAR(32) NULL,
    payment_status VARCHAR(32) NOT NULL DEFAULT 'none',
    payment_external_order_id VARCHAR(80) NULL,
    payment_external_charge_id VARCHAR(80) NULL,
    payment_external_qr_id VARCHAR(80) NULL,
    payment_pix_text TEXT NULL,
    payment_pix_image_base64 LONGTEXT NULL,
    payment_payload LONGTEXT NULL,
    payment_paid_at DATETIME NULL,
    payment_last_webhook_at DATETIME NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    taxa_entrega DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cash_change_for DECIMAL(10,2) NULL,
    cash_change_due DECIMAL(10,2) NULL,
    nome_cliente VARCHAR(140) NOT NULL,
    email_cliente VARCHAR(160) NULL,
    telefone_cliente VARCHAR(30) NULL,
    cpf_cliente CHAR(11) NULL,
    cep CHAR(8) NULL,
    rua VARCHAR(160) NULL,
    bairro VARCHAR(120) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(120) NULL,
    cidade VARCHAR(120) NULL,
    uf CHAR(2) NULL,
    endereco_snapshot VARCHAR(255) NOT NULL,
    estoque_devolvido_em DATETIME NULL,
    status_atualizado_em DATETIME NULL,
    ultimo_admin_id INT UNSIGNED NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedidos_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pedidos_admin
        FOREIGN KEY (ultimo_admin_id) REFERENCES admins(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_itens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NULL,
    produto_nome VARCHAR(180) NOT NULL,
    categoria_nome VARCHAR(140) NULL,
    marca_nome VARCHAR(140) NULL,
    sabor VARCHAR(120) NULL,
    quantidade INT UNSIGNED NOT NULL DEFAULT 1,
    preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal_item DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    imagem VARCHAR(255) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedido_itens_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pedido_itens_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_historico (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL,
    titulo VARCHAR(140) NOT NULL,
    descricao VARCHAR(255) NULL,
    admin_id INT UNSIGNED NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedido_historico_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pedido_historico_admin
        FOREIGN KEY (admin_id) REFERENCES admins(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
