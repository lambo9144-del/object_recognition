<?php

declare(strict_types=1);

/**
 * Reads and writes the scan_history table. The camera image itself is never stored.
 */
final class ScanHistory
{
    public const PER_PAGE = 10;

    /** @param array<string, mixed> $result normalized ProductResult */
    public static function add(array $result): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO scan_history (object_label, product_name, manufacturer, specification, description, confidence, provider)
             VALUES (:label, :product, :maker, :spec, :descr, :conf, :provider)'
        );
        $stmt->execute([
            ':label' => $result['objectLabel'],
            ':product' => $result['productName'],
            ':maker' => $result['manufacturer'],
            ':spec' => $result['specification'],
            ':descr' => $result['description'],
            ':conf' => $result['confidence'],
            ':provider' => $result['provider'],
        ]);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function search(string $query, int $page): array
    {
        $pdo = Database::connection();
        $where = '';
        $params = [];
        if ($query !== '') {
            $where = 'WHERE product_name LIKE :q OR object_label LIKE :q OR manufacturer LIKE :q';
            $params[':q'] = '%' . addcslashes($query, '%_\\') . '%';
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM scan_history $where");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset = max(0, ($page - 1) * self::PER_PAGE);
        $stmt = $pdo->prepare("SELECT * FROM scan_history $where ORDER BY id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM scan_history WHERE id = :id')->execute([':id' => $id]);
    }

    public static function clear(): void
    {
        Database::connection()->exec('TRUNCATE TABLE scan_history');
    }
}
