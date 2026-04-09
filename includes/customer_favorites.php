<?php
declare(strict_types=1);

function ensure_customer_favorites_table(): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    db()->exec(
        'CREATE TABLE IF NOT EXISTS cliente_favoritos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cliente_id INT UNSIGNED NOT NULL,
            produto_id INT UNSIGNED NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_cliente_favoritos_cliente_produto (cliente_id, produto_id),
            CONSTRAINT fk_cliente_favoritos_cliente
                FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
            CONSTRAINT fk_cliente_favoritos_produto
                FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ensured = true;
}

function customer_favorite_product_ids(int $customerId): array
{
    ensure_customer_favorites_table();

    if ($customerId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT produto_id
         FROM cliente_favoritos
         WHERE cliente_id = :cliente_id
         ORDER BY criado_em DESC, id DESC'
    );
    $statement->execute(['cliente_id' => $customerId]);

    return array_map(
        static fn(mixed $value): int => (int) $value,
        $statement->fetchAll(PDO::FETCH_COLUMN)
    );
}

function customer_favorite_count(int $customerId): int
{
    ensure_customer_favorites_table();

    if ($customerId <= 0) {
        return 0;
    }

    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM cliente_favoritos
         WHERE cliente_id = :cliente_id'
    );
    $statement->execute(['cliente_id' => $customerId]);

    return (int) $statement->fetchColumn();
}

function customer_has_favorite_product(int $customerId, int $productId): bool
{
    ensure_customer_favorites_table();

    if ($customerId <= 0 || $productId <= 0) {
        return false;
    }

    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM cliente_favoritos
         WHERE cliente_id = :cliente_id
           AND produto_id = :produto_id'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'produto_id' => $productId,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function customer_save_product(int $customerId, int $productId): void
{
    ensure_customer_favorites_table();

    if ($customerId <= 0 || $productId <= 0) {
        return;
    }

    $statement = db()->prepare(
        'INSERT IGNORE INTO cliente_favoritos (cliente_id, produto_id)
         VALUES (:cliente_id, :produto_id)'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'produto_id' => $productId,
    ]);
}

function customer_remove_favorite_product(int $customerId, int $productId): void
{
    ensure_customer_favorites_table();

    if ($customerId <= 0 || $productId <= 0) {
        return;
    }

    $statement = db()->prepare(
        'DELETE FROM cliente_favoritos
         WHERE cliente_id = :cliente_id
           AND produto_id = :produto_id'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'produto_id' => $productId,
    ]);
}

function customer_toggle_favorite_product(int $customerId, int $productId): bool
{
    if (customer_has_favorite_product($customerId, $productId)) {
        customer_remove_favorite_product($customerId, $productId);
        return false;
    }

    customer_save_product($customerId, $productId);
    return true;
}

function customer_favorite_products(int $customerId, ?int $limit = null): array
{
    $productIds = customer_favorite_product_ids($customerId);

    if ($limit !== null) {
        $productIds = array_slice($productIds, 0, max(0, $limit));
    }

    if ($productIds === []) {
        return [];
    }

    $productsById = storefront_fetch_products_by_ids($productIds);
    $orderedProducts = [];

    foreach ($productIds as $productId) {
        if (isset($productsById[$productId])) {
            $orderedProducts[] = $productsById[$productId];
        }
    }

    return $orderedProducts;
}
