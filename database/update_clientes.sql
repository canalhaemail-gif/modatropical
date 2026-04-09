USE cardapio_digital;

CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(140) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    telefone VARCHAR(30) NULL,
    cep CHAR(8) NULL,
    endereco VARCHAR(255) NULL,
    cpf CHAR(11) NULL,
    data_nascimento DATE NULL,
    senha_hash VARCHAR(255) NOT NULL,
    email_verificado_em DATETIME NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliente_email_verificacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    codigo_hash CHAR(64) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cliente_email_verificacoes_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliente_email_alteracoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    novo_email VARCHAR(160) NOT NULL,
    codigo_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cliente_email_alteracoes_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliente_enderecos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    apelido VARCHAR(80) NOT NULL DEFAULT 'Endereco',
    cep CHAR(8) NOT NULL,
    rua VARCHAR(160) NOT NULL,
    bairro VARCHAR(120) NOT NULL,
    numero VARCHAR(20) NOT NULL,
    complemento VARCHAR(120) NULL,
    cidade VARCHAR(120) NOT NULL,
    uf CHAR(2) NOT NULL,
    principal TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cliente_enderecos_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliente_password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    codigo_hash CHAR(64) NULL,
    token_hash CHAR(64) NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cliente_password_resets_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @codigo_hash_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cliente_password_resets'
      AND COLUMN_NAME = 'codigo_hash'
);

SET @add_codigo_hash_sql := IF(
    @codigo_hash_exists = 0,
    'ALTER TABLE cliente_password_resets ADD COLUMN codigo_hash CHAR(64) NULL AFTER cliente_id',
    'SELECT 1'
);
PREPARE add_codigo_hash_stmt FROM @add_codigo_hash_sql;
EXECUTE add_codigo_hash_stmt;
DEALLOCATE PREPARE add_codigo_hash_stmt;

SET @token_hash_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cliente_password_resets'
      AND COLUMN_NAME = 'token_hash'
);

SET @add_token_hash_sql := IF(
    @token_hash_exists = 0,
    'ALTER TABLE cliente_password_resets ADD COLUMN token_hash CHAR(64) NULL AFTER codigo_hash',
    'SELECT 1'
);
PREPARE add_token_hash_stmt FROM @add_token_hash_sql;
EXECUTE add_token_hash_stmt;
DEALLOCATE PREPARE add_token_hash_stmt;

SET @copy_hash_sql := IF(
    @token_hash_exists > 0,
    'UPDATE cliente_password_resets SET codigo_hash = token_hash WHERE codigo_hash IS NULL AND token_hash IS NOT NULL',
    'SELECT 1'
);
PREPARE copy_hash_stmt FROM @copy_hash_sql;
EXECUTE copy_hash_stmt;
DEALLOCATE PREPARE copy_hash_stmt;

SET @cep_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND COLUMN_NAME = 'cep'
);

SET @add_cep_sql := IF(
    @cep_exists = 0,
    'ALTER TABLE clientes ADD COLUMN cep CHAR(8) NULL AFTER telefone',
    'SELECT 1'
);
PREPARE add_cep_stmt FROM @add_cep_sql;
EXECUTE add_cep_stmt;
DEALLOCATE PREPARE add_cep_stmt;

SET @endereco_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND COLUMN_NAME = 'endereco'
);

SET @add_endereco_sql := IF(
    @endereco_exists = 0,
    'ALTER TABLE clientes ADD COLUMN endereco VARCHAR(255) NULL AFTER cep',
    'SELECT 1'
);
PREPARE add_endereco_stmt FROM @add_endereco_sql;
EXECUTE add_endereco_stmt;
DEALLOCATE PREPARE add_endereco_stmt;

SET @cpf_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND COLUMN_NAME = 'cpf'
);

SET @add_cpf_sql := IF(
    @cpf_exists = 0,
    'ALTER TABLE clientes ADD COLUMN cpf CHAR(11) NULL AFTER endereco',
    'SELECT 1'
);
PREPARE add_cpf_stmt FROM @add_cpf_sql;
EXECUTE add_cpf_stmt;
DEALLOCATE PREPARE add_cpf_stmt;

SET @data_nascimento_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND COLUMN_NAME = 'data_nascimento'
);

SET @add_data_nascimento_sql := IF(
    @data_nascimento_exists = 0,
    'ALTER TABLE clientes ADD COLUMN data_nascimento DATE NULL AFTER cpf',
    'SELECT 1'
);
PREPARE add_data_nascimento_stmt FROM @add_data_nascimento_sql;
EXECUTE add_data_nascimento_stmt;
DEALLOCATE PREPARE add_data_nascimento_stmt;

SET @cpf_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND INDEX_NAME = 'uk_clientes_cpf'
);

SET @add_cpf_index_sql := IF(
    @cpf_index_exists = 0,
    'ALTER TABLE clientes ADD UNIQUE KEY uk_clientes_cpf (cpf)',
    'SELECT 1'
);
PREPARE add_cpf_index_stmt FROM @add_cpf_index_sql;
EXECUTE add_cpf_index_stmt;
DEALLOCATE PREPARE add_cpf_index_stmt;

SET @email_verificado_em_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND COLUMN_NAME = 'email_verificado_em'
);

SET @add_email_verificado_em_sql := IF(
    @email_verificado_em_exists = 0,
    'ALTER TABLE clientes ADD COLUMN email_verificado_em DATETIME NULL AFTER senha_hash',
    'SELECT 1'
);
PREPARE add_email_verificado_em_stmt FROM @add_email_verificado_em_sql;
EXECUTE add_email_verificado_em_stmt;
DEALLOCATE PREPARE add_email_verificado_em_stmt;

UPDATE clientes
SET email_verificado_em = COALESCE(email_verificado_em, criado_em, NOW())
WHERE email_verificado_em IS NULL;

INSERT INTO clientes (nome, email, telefone, cep, endereco, cpf, data_nascimento, senha_hash, email_verificado_em, ativo)
SELECT
    'Cliente Demo',
    'cliente@cardapio.local',
    '5511999997777',
    '20040002',
    'Rua do Cliente, 45 - Centro',
    '52998224725',
    '1994-08-19',
    '$2y$10$MPP5KLJ1OiSoXBiBEvgPiOHx0LZAUCtCw.h/MQOVK4FcClJSx4cpu',
    NOW(),
    1
WHERE NOT EXISTS (
    SELECT 1
    FROM clientes
    WHERE email = 'cliente@cardapio.local'
);

UPDATE clientes
SET
    cep = COALESCE(NULLIF(cep, ''), '20040002'),
    endereco = COALESCE(NULLIF(endereco, ''), 'Rua do Cliente, 45 - Centro'),
    cpf = COALESCE(NULLIF(cpf, ''), '52998224725'),
    data_nascimento = COALESCE(data_nascimento, '1994-08-19'),
    email_verificado_em = COALESCE(email_verificado_em, NOW())
WHERE email = 'cliente@cardapio.local';
