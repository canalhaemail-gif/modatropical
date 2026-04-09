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
