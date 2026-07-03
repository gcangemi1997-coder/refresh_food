<?php

class StatsController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Entry point per /stats/co2
     */
    public function handleCo2(string $method): void
    {
        if ($method !== 'GET') {
            jsonResponse(['message' => 'Metodo non consentito'], 405);
        }

        $this->getCo2Stats();
    }

    /**
     * Ricalcola e aggiorna la cache globale del totale CO2
     */
    private function refreshGlobalCo2Cache(): void
    {
        $sql = "
            SELECT
                COALESCE(SUM(oi.quantity * p.co2_saved_per_unit), 0) AS total_co2_saved
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            JOIN products p ON p.id = oi.product_id
        ";

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch();

        $total = $row ? round((float) $row['total_co2_saved'], 2) : 0.0;

        $stmtUpdate = $this->pdo->prepare(
            'UPDATE stats_cache SET total_co2_saved = :total WHERE id = 1'
        );
        $stmtUpdate->bindValue(':total', $total);
        $stmtUpdate->execute();
    }

    /**
     * GET /stats/co2?from=...&to=...&country=IT&product_id=1
     */
    private function getCo2Stats(): void
    {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $country = $_GET['country'] ?? null;
        $productId = $_GET['product_id'] ?? null;

        $noFilters =
            ($from === null || $from === '') &&
            ($to === null || $to === '') &&
            ($country === null || $country === '') &&
            ($productId === null || $productId === '');

        // Nessun filtro: uso cache aggiornata
        if ($noFilters) {
            $this->refreshGlobalCo2Cache();

            $stmt = $this->pdo->prepare(
                'SELECT total_co2_saved, updated_at FROM stats_cache WHERE id = 1'
            );
            $stmt->execute();
            $row = $stmt->fetch();

            $total = $row ? round((float) $row['total_co2_saved'], 2) : 0.0;

            $response = [
                'total_co2_saved' => $total,
                'cached' => true,
                'updated_at' => $row['updated_at'] ?? null,
                'filters' => [
                    'from' => null,
                    'to' => null,
                    'country' => null,
                    'product_id' => null,
                ],
            ];

            jsonResponse($response, 200);
        }

        $conditions = [];
        $params = [];

        if ($from !== null && $from !== '') {
            $conditions[] = 'o.sold_at >= :from';
            $params[':from'] = $from;
        }

        if ($to !== null && $to !== '') {
            $conditions[] = 'o.sold_at <= :to';
            $params[':to'] = $to;
        }

        if ($country !== null && $country !== '') {
            $conditions[] = 'o.destination_country = :country';
            $params[':country'] = $country;
        }

        if ($productId !== null && $productId !== '') {
            $conditions[] = 'p.id = :product_id';
            $params[':product_id'] = (int) $productId;
        }

        $whereSql = '';
        if (!empty($conditions)) {
            $whereSql = 'WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "
            SELECT
                COALESCE(SUM(oi.quantity * p.co2_saved_per_unit), 0) AS total_co2_saved
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            JOIN products p ON p.id = oi.product_id
            $whereSql
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === ':product_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        $row = $stmt->fetch();

        $total = $row ? round((float) $row['total_co2_saved'], 2) : 0.0;

        $response = [
            'total_co2_saved' => $total,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'country' => $country,
                'product_id' => $productId !== null && $productId !== '' ? (int) $productId : null,
            ],
        ];

        jsonResponse($response, 200);
    }
}