<?php
declare(strict_types=1);

function build_customer_address_string(
    string $street,
    string $district,
    string $number,
    string $complement,
    string $city,
    string $state
): string {
    $parts = [
        trim($street),
        trim($district) !== '' ? 'Bairro ' . trim($district) : '',
        trim($number) !== '' ? 'Numero ' . trim($number) : '',
        trim($complement) !== '' ? 'Complemento ' . trim($complement) : '',
        trim($city) !== '' && trim($state) !== '' ? trim($city) . ' - ' . strtoupper(trim($state)) : (trim($city) ?: strtoupper(trim($state))),
    ];

    return trim(implode(', ', array_values(array_filter($parts, static fn(string $part): bool => $part !== ''))));
}

function fetch_customer_addresses(int $customerId): array
{
    $statement = db()->prepare(
        'SELECT *
         FROM cliente_enderecos
         WHERE cliente_id = :cliente_id
         ORDER BY principal DESC, id ASC'
    );
    $statement->execute(['cliente_id' => $customerId]);

    return $statement->fetchAll();
}

function find_customer_address(int $customerId, int $addressId): ?array
{
    $statement = db()->prepare(
        'SELECT *
         FROM cliente_enderecos
         WHERE id = :id
           AND cliente_id = :cliente_id
         LIMIT 1'
    );
    $statement->execute([
        'id' => $addressId,
        'cliente_id' => $customerId,
    ]);
    $address = $statement->fetch();

    return $address ?: null;
}

function fetch_customer_primary_address(int $customerId): ?array
{
    $statement = db()->prepare(
        'SELECT *
         FROM cliente_enderecos
         WHERE cliente_id = :cliente_id
         ORDER BY principal DESC, id ASC
         LIMIT 1'
    );
    $statement->execute(['cliente_id' => $customerId]);
    $address = $statement->fetch();

    return $address ?: null;
}

function customer_has_addresses(int $customerId): bool
{
    $statement = db()->prepare('SELECT COUNT(*) FROM cliente_enderecos WHERE cliente_id = :cliente_id');
    $statement->execute(['cliente_id' => $customerId]);

    return (int) $statement->fetchColumn() > 0;
}

function sync_customer_primary_address(int $customerId): void
{
    $statement = db()->prepare(
        'SELECT cep, rua, bairro, numero, complemento, cidade, uf
         FROM cliente_enderecos
         WHERE cliente_id = :cliente_id
           AND principal = 1
         ORDER BY id ASC
         LIMIT 1'
    );
    $statement->execute(['cliente_id' => $customerId]);
    $address = $statement->fetch();

    if (!$address) {
        db()->prepare(
            'UPDATE clientes
             SET cep = \'\',
                 endereco = \'\'
             WHERE id = :id'
        )->execute(['id' => $customerId]);

        return;
    }

    db()->prepare(
        'UPDATE clientes
         SET cep = :cep,
             endereco = :endereco
         WHERE id = :id'
    )->execute([
        'cep' => normalize_cep((string) $address['cep']),
        'endereco' => build_customer_address_string(
            (string) $address['rua'],
            (string) $address['bairro'],
            (string) $address['numero'],
            (string) $address['complemento'],
            (string) $address['cidade'],
            (string) $address['uf']
        ),
        'id' => $customerId,
    ]);
}

function save_customer_address(int $customerId, array $data, ?int $addressId = null): int
{
    $hasExisting = customer_has_addresses($customerId);
    $isPrimary = !empty($data['principal']) || !$hasExisting;

    if ($addressId !== null && !$isPrimary) {
        $existingPrimary = db()->prepare(
            'SELECT COUNT(*)
             FROM cliente_enderecos
             WHERE cliente_id = :cliente_id
               AND principal = 1
               AND id != :id'
        );
        $existingPrimary->execute([
            'cliente_id' => $customerId,
            'id' => $addressId,
        ]);

        if ((int) $existingPrimary->fetchColumn() === 0) {
            $isPrimary = true;
        }
    }

    if ($isPrimary) {
        db()->prepare(
            'UPDATE cliente_enderecos
             SET principal = 0
             WHERE cliente_id = :cliente_id'
        )->execute(['cliente_id' => $customerId]);
    }

    $payload = [
        'cliente_id' => $customerId,
        'apelido' => trim((string) ($data['apelido'] ?? '')),
        'cep' => normalize_cep((string) ($data['cep'] ?? '')),
        'rua' => trim((string) ($data['rua'] ?? '')),
        'bairro' => trim((string) ($data['bairro'] ?? '')),
        'numero' => trim((string) ($data['numero'] ?? '')),
        'complemento' => trim((string) ($data['complemento'] ?? '')),
        'cidade' => trim((string) ($data['cidade'] ?? '')),
        'uf' => strtoupper(trim((string) ($data['uf'] ?? ''))),
        'principal' => $isPrimary ? 1 : 0,
    ];

    if ($addressId !== null) {
        db()->prepare(
            'UPDATE cliente_enderecos
             SET apelido = :apelido,
                 cep = :cep,
                 rua = :rua,
                 bairro = :bairro,
                 numero = :numero,
                 complemento = :complemento,
                 cidade = :cidade,
                 uf = :uf,
                 principal = :principal
             WHERE id = :id
               AND cliente_id = :cliente_id'
        )->execute($payload + ['id' => $addressId]);

        sync_customer_primary_address($customerId);

        return $addressId;
    }

    db()->prepare(
        'INSERT INTO cliente_enderecos (
            cliente_id, apelido, cep, rua, bairro, numero, complemento, cidade, uf, principal
         ) VALUES (
            :cliente_id, :apelido, :cep, :rua, :bairro, :numero, :complemento, :cidade, :uf, :principal
         )'
    )->execute($payload);

    $newId = (int) db()->lastInsertId();
    sync_customer_primary_address($customerId);

    return $newId;
}

function set_customer_address_as_primary(int $customerId, int $addressId): bool
{
    if (!find_customer_address($customerId, $addressId)) {
        return false;
    }

    db()->prepare(
        'UPDATE cliente_enderecos
         SET principal = 0
         WHERE cliente_id = :cliente_id'
    )->execute(['cliente_id' => $customerId]);

    db()->prepare(
        'UPDATE cliente_enderecos
         SET principal = 1
         WHERE id = :id
           AND cliente_id = :cliente_id'
    )->execute([
        'id' => $addressId,
        'cliente_id' => $customerId,
    ]);

    sync_customer_primary_address($customerId);

    return true;
}

function delete_customer_address(int $customerId, int $addressId): bool
{
    $address = find_customer_address($customerId, $addressId);

    if (!$address) {
        return false;
    }

    db()->prepare(
        'DELETE FROM cliente_enderecos
         WHERE id = :id
           AND cliente_id = :cliente_id'
    )->execute([
        'id' => $addressId,
        'cliente_id' => $customerId,
    ]);

    $nextPrimary = db()->prepare(
        'SELECT id
         FROM cliente_enderecos
         WHERE cliente_id = :cliente_id
         ORDER BY id ASC
         LIMIT 1'
    );
    $nextPrimary->execute(['cliente_id' => $customerId]);
    $nextId = $nextPrimary->fetchColumn();

    if ($nextId !== false) {
        $primaryCount = db()->prepare(
            'SELECT COUNT(*)
             FROM cliente_enderecos
             WHERE cliente_id = :cliente_id
               AND principal = 1'
        );
        $primaryCount->execute(['cliente_id' => $customerId]);

        if ((int) $primaryCount->fetchColumn() === 0) {
            db()->prepare(
                'UPDATE cliente_enderecos
                 SET principal = 1
                 WHERE id = :id
                   AND cliente_id = :cliente_id'
            )->execute([
                'id' => (int) $nextId,
                'cliente_id' => $customerId,
            ]);
        }
    }

    sync_customer_primary_address($customerId);

    return true;
}
