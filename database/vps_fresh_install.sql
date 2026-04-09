
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS produto_imagens;
DROP TABLE IF EXISTS produto_sabores;
DROP TABLE IF EXISTS cupom_marcas;
DROP TABLE IF EXISTS cupom_produtos;
DROP TABLE IF EXISTS cliente_cupons;
DROP TABLE IF EXISTS cupons;
DROP TABLE IF EXISTS cliente_notificacoes;
DROP TABLE IF EXISTS pedido_historico;
DROP TABLE IF EXISTS pedido_itens;
DROP TABLE IF EXISTS pedidos;
DROP TABLE IF EXISTS produtos;
DROP TABLE IF EXISTS sabores;
DROP TABLE IF EXISTS marcas;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS configuracoes;
DROP TABLE IF EXISTS cliente_enderecos;
DROP TABLE IF EXISTS cliente_email_alteracoes;
DROP TABLE IF EXISTS cliente_email_verificacoes;
DROP TABLE IF EXISTS cliente_password_resets;
DROP TABLE IF EXISTS cliente_identities;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS admins;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuracoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome_estabelecimento VARCHAR(160) NOT NULL,
    descricao_loja TEXT NULL,
    logo VARCHAR(255) NULL,
    telefone_whatsapp VARCHAR(30) NULL,
    endereco VARCHAR(255) NULL,
    horario_funcionamento VARCHAR(255) NULL,
    cor_primaria VARCHAR(20) NOT NULL DEFAULT '#D97A6C',
    cor_secundaria VARCHAR(20) NOT NULL DEFAULT '#97B39B',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(140) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    telefone VARCHAR(30) NULL,
    cep CHAR(8) NULL,
    endereco VARCHAR(255) NULL,
    cpf CHAR(11) NULL UNIQUE,
    data_nascimento DATE NULL,
    senha_hash VARCHAR(255) NULL,
    email_verificado_em DATETIME NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cliente_identities (
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

CREATE TABLE cliente_email_verificacoes (
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

CREATE TABLE cliente_email_alteracoes (
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

CREATE TABLE cliente_enderecos (
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

CREATE TABLE cliente_password_resets (
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

CREATE TABLE categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    ordem INT NOT NULL DEFAULT 0,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sabores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE marcas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    imagem VARCHAR(255) NULL,
    ordem INT NOT NULL DEFAULT 0,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE produtos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT UNSIGNED NOT NULL,
    marca_id INT UNSIGNED NULL,
    nome VARCHAR(160) NOT NULL,
    nome_curto VARCHAR(80) NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    descricao TEXT NULL,
    preco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    desconto_percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    estoque INT UNSIGNED NOT NULL DEFAULT 0,
    sabores TEXT NULL,
    imagem VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    destaque TINYINT(1) NOT NULL DEFAULT 0,
    promocao TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_produtos_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_produtos_marca
        FOREIGN KEY (marca_id) REFERENCES marcas(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cupons (
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

CREATE TABLE cupom_produtos (
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

CREATE TABLE cupom_marcas (
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

CREATE TABLE cliente_notificacoes (
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

CREATE TABLE cliente_cupons (
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

CREATE TABLE produto_sabores (
    produto_id INT UNSIGNED NOT NULL,
    sabor_id INT UNSIGNED NOT NULL,
    estoque INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (produto_id, sabor_id),
    CONSTRAINT fk_produto_sabores_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_produto_sabores_sabor
        FOREIGN KEY (sabor_id) REFERENCES sabores(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE produto_imagens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id INT UNSIGNED NOT NULL,
    imagem VARCHAR(255) NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_produto_imagens_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedidos (
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
    cupom_id INT UNSIGNED NULL,
    cupom_codigo VARCHAR(80) NULL,
    cupom_nome VARCHAR(140) NULL,
    cupom_tipo VARCHAR(32) NULL,
    cupom_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cupom_frete_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
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
    CONSTRAINT fk_pedidos_cupom
        FOREIGN KEY (cupom_id) REFERENCES cupons(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pedidos_admin
        FOREIGN KEY (ultimo_admin_id) REFERENCES admins(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedido_itens (
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

CREATE TABLE pedido_historico (
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

INSERT INTO admins (nome, email, senha_hash) VALUES
('Administrador', 'nogassis777@gmail.com', '$2y$10$70yl8jdNR5CX7R9baaZWhuC151rNnPWpDCqKRzRc05OKCKv8PO6VG');

LOCK TABLES `configuracoes` WRITE;
/*!40000 ALTER TABLE `configuracoes` DISABLE KEYS */;
INSERT INTO `configuracoes` VALUES (1,'MODA TROPICAL','Moda feminina com toque tropical, pecas leves e colecoes pensadas para destacar seu estilo.','logo.png','(24) 99859-2033','Rua das Flores, 123 - Centro','Seg a Sab, 10h as 22h','#D97A6C','#97B39B','2026-03-24 20:01:33');
/*!40000 ALTER TABLE `configuracoes` ENABLE KEYS */;
UNLOCK TABLES;

CREATE TABLE IF NOT EXISTS `cliente_remember_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cliente_id` int NOT NULL,
  `token_hash` char(64) NOT NULL,
  `criado_em` datetime NOT NULL,
  `expira_em` datetime NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_cliente_remember_tokens_hash` (`token_hash`),
  KEY `idx_cliente_remember_tokens_cliente` (`cliente_id`)
);

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'Vestidos','vestidos',2,1,'2026-03-24 20:01:33'),(7,'Blusinhas','blusinhas',1,1,'2026-03-28 19:05:16'),(8,'Macacões','macac-oes',3,1,'2026-04-02 18:12:08'),(9,'Inverno','inverno',4,1,'2026-04-02 18:12:29'),(10,'Saias','saias',5,1,'2026-04-02 18:12:40'),(11,'Shorts','shorts',6,1,'2026-04-02 18:12:48'),(12,'Tops','tops',8,1,'2026-04-02 18:12:59'),(13,'Calças','calcas',7,1,'2026-04-02 18:13:12'),(14,'Moda Praia','moda-praia',9,1,'2026-04-02 18:13:48');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `sabores` WRITE;
/*!40000 ALTER TABLE `sabores` DISABLE KEYS */;
INSERT INTO `sabores` VALUES (7,'38','38',5,1,'2026-04-02 18:34:11'),(8,'40','40',6,1,'2026-04-02 18:34:11'),(9,'42','42',7,1,'2026-04-02 18:34:11'),(10,'44','44',8,1,'2026-04-02 20:29:35'),(11,'46','46',9,1,'2026-04-02 21:08:09');
/*!40000 ALTER TABLE `sabores` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `marcas` WRITE;
/*!40000 ALTER TABLE `marcas` DISABLE KEYS */;
/*!40000 ALTER TABLE `marcas` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `produtos` WRITE;
/*!40000 ALTER TABLE `produtos` DISABLE KEYS */;
INSERT INTO `produtos` VALUES (17,7,NULL,'Bata de linho cor areia','bata-de-linho-cor-areia','Inspirada em nossa Bata de maior sucesso a Milena, a bata de linho Mila com decote assimétrico de um ombro só, com faixa desfiada à mão no próprio tecido. \r\n\r\nDica Dias: Combine com Short de linho Lari ou Calça de linho Gisela e use de forma mais casual com rasteiras ou em ocasiões mais especiais com sandálias altas!',120.00,0.00,14,'38\n40\n42','uploads/products/products-20260402151822-16dc307c.webp',1,1,0,1,'2026-04-02 18:18:22'),(18,7,NULL,'Bata de linho azul-netuno','bata-de-linho-azul-netuno','',150.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402172935-07d14b0a.webp',1,1,0,2,'2026-04-02 20:29:35'),(19,7,NULL,'Bata de Linho jabuticaba','bata-de-linho-jabuticaba','',150.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402173129-9fa231d1.webp',1,1,0,3,'2026-04-02 20:31:29'),(20,7,NULL,'Bata de linho off-white','bata-de-linho-off-white','',150.00,10.00,15,'38\n40\n44','uploads/products/products-20260402173253-512022c9.webp',1,0,1,4,'2026-04-02 20:32:53'),(21,7,NULL,'Blusa de linho off-white MilaBata de linho off-white','blusa-de-linho-off-white-milabata-de-linho-off-white','',150.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402175109-b70fc3fa.webp',1,0,0,5,'2026-04-02 20:51:09'),(22,7,NULL,'Blusa de linho verde-floresta','blusa-de-linho-verde-floresta','',150.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402175309-36c0f85c.webp',1,0,0,6,'2026-04-02 20:53:09'),(23,13,NULL,'Calça de linho areia','calca-de-linho-areia','',200.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402175455-730c66ce.webp',1,1,0,7,'2026-04-02 20:54:55'),(24,13,NULL,'Calça de Linho marrom-café','calca-de-linho-marrom-caf-e','',200.00,10.00,20,'38\n40\n42\n44','uploads/products/products-20260402175808-dd311e3a.webp',1,1,1,8,'2026-04-02 20:58:08'),(25,13,NULL,'Calça de linho off-white','calca-de-linho-off-white','',200.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402175953-1229024a.webp',1,1,0,9,'2026-04-02 20:59:53'),(26,13,NULL,'Calça de Linho pitanga','calca-de-linho-pitanga','',200.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402180135-80a59a73.webp',1,0,0,10,'2026-04-02 21:01:35'),(27,13,NULL,'Calça de Linho rosé','calca-de-linho-ros-e','',200.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402180249-eeffdba4.webp',1,0,0,11,'2026-04-02 21:02:49'),(28,13,NULL,'Calça de linho verde-floresta','calca-de-linho-verde-floresta','',200.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402180340-9c5bbf39.webp',1,0,0,12,'2026-04-02 21:03:40'),(29,13,NULL,'Calça de Linho verde-militar','calca-de-linho-verde-militar','',200.00,15.00,20,'38\n40\n42\n44','uploads/products/products-20260402180431-b509d54d.webp',1,0,1,13,'2026-04-02 21:04:31'),(30,9,NULL,'Cardigan em Viscose em Efeito Tricô com Botões Branco','cardigan-em-viscose-em-efeito-tric-o-com-bot-oes-branco','',350.00,18.00,20,'38\n40\n42\n44','uploads/products/products-20260402180639-5f07e3fe.webp',1,0,1,14,'2026-04-02 21:06:39'),(31,9,NULL,'Casaco Longo em Polivelour com Botões e Bolsos Frontais Cinza','casaco-longo-em-polivelour-com-bot-oes-e-bolsos-frontais-cinza','',350.00,22.00,25,'38\n40\n42\n44\n46','uploads/products/products-20260402180809-69ae49b0.webp',1,0,1,15,'2026-04-02 21:08:09'),(32,9,NULL,'Casaco Moletom com Bordado Lettering e Mangas Contrastantes BrancoMarrom','casaco-moletom-com-bordado-lettering-e-mangas-contrastantes-brancomarrom','',300.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402181128-9e75c9e2.webp',1,1,0,16,'2026-04-02 21:11:28'),(33,9,NULL,'Jaqueta Básica em Pu com Zíper Tratorado Marrom Médio','jaqueta-b-asica-em-pu-com-z-iper-tratorado-marrom-m-edio','',230.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402181251-9539750a.webp',1,0,0,17,'2026-04-02 21:12:51'),(34,8,NULL,'Macacão de linho marinho','macac-ao-de-linho-marinho','',170.00,0.00,20,'38\n40\n42\n44','uploads/products/products-20260402181620-e5f91e26.webp',1,0,0,18,'2026-04-02 21:16:20');
/*!40000 ALTER TABLE `produtos` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `produto_sabores` WRITE;
/*!40000 ALTER TABLE `produto_sabores` DISABLE KEYS */;
INSERT INTO `produto_sabores` VALUES (17,7,5),(17,8,5),(17,9,4),(18,7,5),(18,8,5),(18,9,5),(18,10,5),(19,7,5),(19,8,5),(19,9,5),(19,10,5),(20,7,5),(20,8,5),(20,9,0),(20,10,5),(21,7,5),(21,8,5),(21,9,5),(21,10,5),(22,7,5),(22,8,5),(22,9,5),(22,10,5),(23,7,5),(23,8,5),(23,9,5),(23,10,5),(24,7,5),(24,8,5),(24,9,5),(24,10,5),(25,7,5),(25,8,5),(25,9,5),(25,10,5),(26,7,5),(26,8,5),(26,9,5),(26,10,5),(27,7,5),(27,8,5),(27,9,5),(27,10,5),(28,7,5),(28,8,5),(28,9,5),(28,10,5),(29,7,5),(29,8,5),(29,9,5),(29,10,5),(30,7,5),(30,8,5),(30,9,5),(30,10,5),(31,7,5),(31,8,5),(31,9,5),(31,10,5),(31,11,5),(32,7,5),(32,8,5),(32,9,5),(32,10,5),(33,7,5),(33,8,5),(33,9,5),(33,10,5),(34,7,5),(34,8,5),(34,9,5),(34,10,5);
/*!40000 ALTER TABLE `produto_sabores` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `produto_imagens` WRITE;
/*!40000 ALTER TABLE `produto_imagens` DISABLE KEYS */;
INSERT INTO `produto_imagens` VALUES (2,17,'uploads/products/products-gallery-20260402151822-cc29720c.webp',1,'2026-04-02 18:18:22'),(3,17,'uploads/products/products-gallery-20260402151822-3f5b744c.webp',2,'2026-04-02 18:18:22'),(4,17,'uploads/products/products-gallery-20260402151822-b833a7d4.webp',3,'2026-04-02 18:18:22'),(5,17,'uploads/products/products-gallery-20260402151822-09527311.webp',4,'2026-04-02 18:18:22'),(6,17,'uploads/products/products-gallery-20260402151822-889458a0.webp',5,'2026-04-02 18:18:22'),(7,18,'uploads/products/products-gallery-20260402172935-5022ae2c.webp',1,'2026-04-02 20:29:35'),(8,18,'uploads/products/products-gallery-20260402172935-25c445b9.webp',2,'2026-04-02 20:29:35'),(9,18,'uploads/products/products-gallery-20260402172935-85b22130.webp',3,'2026-04-02 20:29:35'),(10,18,'uploads/products/products-gallery-20260402172935-07242992.webp',4,'2026-04-02 20:29:35'),(11,18,'uploads/products/products-gallery-20260402172935-93d09437.webp',5,'2026-04-02 20:29:35'),(12,19,'uploads/products/products-gallery-20260402173129-ba2d2a32.webp',1,'2026-04-02 20:31:29'),(13,19,'uploads/products/products-gallery-20260402173129-cfa0da88.webp',2,'2026-04-02 20:31:29'),(14,19,'uploads/products/products-gallery-20260402173129-658c90ab.webp',3,'2026-04-02 20:31:29'),(15,19,'uploads/products/products-gallery-20260402173129-1b787c02.webp',4,'2026-04-02 20:31:29'),(16,19,'uploads/products/products-gallery-20260402173129-596a47e7.webp',5,'2026-04-02 20:31:29'),(17,20,'uploads/products/products-gallery-20260402173253-5099825a.webp',1,'2026-04-02 20:32:53'),(18,20,'uploads/products/products-gallery-20260402173253-3d975c52.webp',2,'2026-04-02 20:32:53'),(19,20,'uploads/products/products-gallery-20260402173253-bf5538cf.webp',3,'2026-04-02 20:32:53'),(20,20,'uploads/products/products-gallery-20260402173253-ac1a2f48.webp',4,'2026-04-02 20:32:53'),(21,20,'uploads/products/products-gallery-20260402173253-223ddef9.webp',5,'2026-04-02 20:32:53'),(22,21,'uploads/products/products-gallery-20260402175109-c577d49c.webp',1,'2026-04-02 20:51:09'),(23,21,'uploads/products/products-gallery-20260402175109-e0e3bdff.webp',2,'2026-04-02 20:51:09'),(24,21,'uploads/products/products-gallery-20260402175109-f48b7a32.webp',3,'2026-04-02 20:51:09'),(25,21,'uploads/products/products-gallery-20260402175109-b6c2d0ac.webp',4,'2026-04-02 20:51:09'),(26,21,'uploads/products/products-gallery-20260402175109-ee5fa5c4.webp',5,'2026-04-02 20:51:09'),(27,21,'uploads/products/products-gallery-20260402175109-adcd4ea3.webp',6,'2026-04-02 20:51:09'),(28,22,'uploads/products/products-gallery-20260402175309-1dd1c321.webp',1,'2026-04-02 20:53:09'),(29,22,'uploads/products/products-gallery-20260402175309-76e71f71.webp',2,'2026-04-02 20:53:09'),(30,22,'uploads/products/products-gallery-20260402175309-21b505b3.webp',3,'2026-04-02 20:53:09'),(31,22,'uploads/products/products-gallery-20260402175309-2e82338b.webp',4,'2026-04-02 20:53:09'),(32,23,'uploads/products/products-gallery-20260402175455-2f54b488.webp',1,'2026-04-02 20:54:55'),(33,23,'uploads/products/products-gallery-20260402175455-8e8a06ac.webp',2,'2026-04-02 20:54:55'),(34,23,'uploads/products/products-gallery-20260402175455-db6cce9a.webp',3,'2026-04-02 20:54:55'),(35,24,'uploads/products/products-gallery-20260402175808-e09975ae.webp',1,'2026-04-02 20:58:08'),(36,24,'uploads/products/products-gallery-20260402175808-b0be53d0.webp',2,'2026-04-02 20:58:08'),(37,24,'uploads/products/products-gallery-20260402175808-b5154554.webp',3,'2026-04-02 20:58:08'),(38,25,'uploads/products/products-gallery-20260402175953-dff29e61.webp',1,'2026-04-02 20:59:53'),(39,25,'uploads/products/products-gallery-20260402175953-236d54e6.webp',2,'2026-04-02 20:59:53'),(40,25,'uploads/products/products-gallery-20260402175953-1df68873.webp',3,'2026-04-02 20:59:53'),(41,25,'uploads/products/products-gallery-20260402175953-da504429.webp',4,'2026-04-02 20:59:53'),(42,25,'uploads/products/products-gallery-20260402175953-6e1ca893.webp',5,'2026-04-02 20:59:53'),(43,26,'uploads/products/products-gallery-20260402180135-f3806341.webp',1,'2026-04-02 21:01:35'),(44,26,'uploads/products/products-gallery-20260402180135-8107bcfb.webp',2,'2026-04-02 21:01:35'),(45,26,'uploads/products/products-gallery-20260402180135-9610bfd7.webp',3,'2026-04-02 21:01:35'),(46,26,'uploads/products/products-gallery-20260402180135-526a1b19.webp',4,'2026-04-02 21:01:35'),(47,27,'uploads/products/products-gallery-20260402180249-105d93a3.webp',1,'2026-04-02 21:02:49'),(48,27,'uploads/products/products-gallery-20260402180249-4a2a89c4.webp',2,'2026-04-02 21:02:49'),(49,28,'uploads/products/products-gallery-20260402180340-871cdd4d.webp',1,'2026-04-02 21:03:40'),(50,28,'uploads/products/products-gallery-20260402180340-96a824a1.webp',2,'2026-04-02 21:03:40'),(51,28,'uploads/products/products-gallery-20260402180340-2f57f286.webp',3,'2026-04-02 21:03:40'),(52,28,'uploads/products/products-gallery-20260402180340-b85696d9.webp',4,'2026-04-02 21:03:40'),(53,29,'uploads/products/products-gallery-20260402180431-1b754e31.webp',1,'2026-04-02 21:04:31'),(54,29,'uploads/products/products-gallery-20260402180431-37b2652a.webp',2,'2026-04-02 21:04:31'),(55,29,'uploads/products/products-gallery-20260402180431-bdb3a993.webp',3,'2026-04-02 21:04:31'),(56,29,'uploads/products/products-gallery-20260402180431-b5f04bb6.webp',4,'2026-04-02 21:04:31'),(57,30,'uploads/products/products-gallery-20260402180639-31f6eb45.webp',1,'2026-04-02 21:06:39'),(58,30,'uploads/products/products-gallery-20260402180639-08c8a19c.webp',2,'2026-04-02 21:06:39'),(59,30,'uploads/products/products-gallery-20260402180639-564fa2c0.webp',3,'2026-04-02 21:06:39'),(60,31,'uploads/products/products-gallery-20260402180809-87507324.webp',1,'2026-04-02 21:08:09'),(61,31,'uploads/products/products-gallery-20260402180809-cd55c352.webp',2,'2026-04-02 21:08:09'),(62,31,'uploads/products/products-gallery-20260402180809-d3fa1cf3.webp',3,'2026-04-02 21:08:09'),(63,31,'uploads/products/products-gallery-20260402180809-1c919655.webp',4,'2026-04-02 21:08:09'),(64,31,'uploads/products/products-gallery-20260402180809-e83c338e.webp',5,'2026-04-02 21:08:09'),(65,31,'uploads/products/products-gallery-20260402180809-f40d97ca.webp',6,'2026-04-02 21:08:09'),(66,32,'uploads/products/products-gallery-20260402181128-7b16ca05.webp',1,'2026-04-02 21:11:28'),(67,32,'uploads/products/products-gallery-20260402181128-ce2fdd01.webp',2,'2026-04-02 21:11:28'),(68,32,'uploads/products/products-gallery-20260402181128-a8616f66.webp',3,'2026-04-02 21:11:28'),(69,32,'uploads/products/products-gallery-20260402181128-c7894301.webp',4,'2026-04-02 21:11:28'),(70,32,'uploads/products/products-gallery-20260402181128-46c9dc1e.webp',5,'2026-04-02 21:11:28'),(71,32,'uploads/products/products-gallery-20260402181128-8311479c.webp',6,'2026-04-02 21:11:28'),(72,33,'uploads/products/products-gallery-20260402181251-6e3a10b0.webp',1,'2026-04-02 21:12:51'),(73,33,'uploads/products/products-gallery-20260402181251-74d534fa.webp',2,'2026-04-02 21:12:51'),(74,33,'uploads/products/products-gallery-20260402181251-aeed46d4.webp',3,'2026-04-02 21:12:51'),(75,33,'uploads/products/products-gallery-20260402181251-c3bb4f56.webp',4,'2026-04-02 21:12:51'),(76,33,'uploads/products/products-gallery-20260402181251-ddb74fbd.webp',5,'2026-04-02 21:12:51'),(77,34,'uploads/products/products-gallery-20260402181620-9eeedf5f.webp',1,'2026-04-02 21:16:20'),(78,34,'uploads/products/products-gallery-20260402181620-eeb85c9f.webp',2,'2026-04-02 21:16:20'),(79,34,'uploads/products/products-gallery-20260402181620-2e4eba16.webp',3,'2026-04-02 21:16:20'),(80,34,'uploads/products/products-gallery-20260402181620-8be88b19.webp',4,'2026-04-02 21:16:20'),(81,34,'uploads/products/products-gallery-20260402181620-093e5a80.webp',5,'2026-04-02 21:16:20');
/*!40000 ALTER TABLE `produto_imagens` ENABLE KEYS */;
UNLOCK TABLES;
