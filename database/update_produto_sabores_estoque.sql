USE cardapio_digital;

SET @has_estoque_col := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produto_sabores'
      AND COLUMN_NAME = 'estoque'
);

SET @sql := IF(
    @has_estoque_col = 0,
    'ALTER TABLE produto_sabores ADD COLUMN estoque INT UNSIGNED NOT NULL DEFAULT 0 AFTER sabor_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE produto_sabores ps
INNER JOIN (
    SELECT produto_id, COUNT(*) AS total_sabores
    FROM produto_sabores
    GROUP BY produto_id
) rel ON rel.produto_id = ps.produto_id
INNER JOIN produtos p ON p.id = ps.produto_id
SET ps.estoque = p.estoque
WHERE rel.total_sabores = 1
  AND ps.estoque = 0
  AND p.estoque > 0;
