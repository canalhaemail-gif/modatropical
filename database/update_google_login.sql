ALTER TABLE clientes
    MODIFY cep CHAR(8) NULL,
    MODIFY endereco VARCHAR(255) NULL,
    MODIFY cpf CHAR(11) NULL,
    MODIFY data_nascimento DATE NULL,
    MODIFY senha_hash VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS cliente_identities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    provider VARCHAR(20) NOT NULL,
    provider_user_id VARCHAR(191) NOT NULL,
    provider_email VARCHAR(160) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cliente_identities_provider_user (provider, provider_user_id),
    KEY idx_cliente_identities_cliente (cliente_id),
    CONSTRAINT fk_cliente_identities_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
