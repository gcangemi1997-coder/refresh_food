<?php

class OrderController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function processRequest(string $method, ?string $id): void
    {
        switch ($method) {
            case 'GET':
                if ($id === null) {
                    $this->getAllOrders();
                } else {
                    $this->getOrder((int) $id);
                }
                break;

            case 'POST':
                $this->createOrder();
                break;

            case 'PUT':
            case 'PATCH':
                if ($id === null) {
                    jsonResponse(['message' => 'Order ID richiesto'], 400);
                }
                $this->updateOrder((int) $id);
                break;

            case 'DELETE':
                if ($id === null) {
                    jsonResponse(['message' => 'Order ID richiesto'], 400);
                }
                $this->deleteOrder((int) $id);
                break;

            default:
                jsonResponse(['message' => 'Metodo non consentito'], 405);
        }
    }

    /**
     * GET /orders
     * Restituisce tutti gli ordini con i rispettivi items
     */
    private function getAllOrders(): void
    {
        $stmt = $this->pdo->query(
            'SELECT id, sold_at, destination_country
             FROM orders
             ORDER BY sold_at DESC, id DESC'
        );
        $orders = $stmt->fetchAll();

        if (empty($orders)) {
            jsonResponse([], 200);
        }

        $orderIds = array_column($orders, 'id');
        $inPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));

        $stmtItems = $this->pdo->prepare(
            'SELECT oi.order_id, oi.product_id, oi.quantity,
                    p.name AS product_name, p.co2_saved_per_unit
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id IN (' . $inPlaceholders . ')
             ORDER BY oi.order_id ASC, oi.id ASC'
        );

        foreach ($orderIds as $index => $orderId) {
            $stmtItems->bindValue($index + 1, $orderId, PDO::PARAM_INT);
        }

        $stmtItems->execute();
        $items = $stmtItems->fetchAll();

        $itemsByOrder = [];
        foreach ($items as $item) {
            $orderId = (int) $item['order_id'];

            if (!isset($itemsByOrder[$orderId])) {
                $itemsByOrder[$orderId] = [];
            }

            $itemsByOrder[$orderId][] = [
                'product_id' => (int) $item['product_id'],
                'product_name' => $item['product_name'],
                'quantity' => (int) $item['quantity'],
                'co2_saved_per_unit' => (float) $item['co2_saved_per_unit'],
            ];
        }

        $result = [];
        foreach ($orders as $order) {
            $orderId = (int) $order['id'];

            $result[] = [
                'id' => $orderId,
                'sold_at' => $order['sold_at'],
                'destination_country' => $order['destination_country'],
                'items' => $itemsByOrder[$orderId] ?? [],
            ];
        }

        jsonResponse($result, 200);
    }

    /**
     * GET /orders/{id}
     * Restituisce un singolo ordine con i rispettivi items
     */
    private function getOrder(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sold_at, destination_country
             FROM orders
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $order = $stmt->fetch();

        if (!$order) {
            jsonResponse(['message' => 'Order non trovato'], 404);
        }

        $stmtItems = $this->pdo->prepare(
            'SELECT oi.product_id, oi.quantity,
                    p.name AS product_name, p.co2_saved_per_unit
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = :order_id
             ORDER BY oi.id ASC'
        );
        $stmtItems->bindValue(':order_id', $id, PDO::PARAM_INT);
        $stmtItems->execute();

        $items = [];
        foreach ($stmtItems->fetchAll() as $item) {
            $items[] = [
                'product_id' => (int) $item['product_id'],
                'product_name' => $item['product_name'],
                'quantity' => (int) $item['quantity'],
                'co2_saved_per_unit' => (float) $item['co2_saved_per_unit'],
            ];
        }

        $response = [
            'id' => (int) $order['id'],
            'sold_at' => $order['sold_at'],
            'destination_country' => $order['destination_country'],
            'items' => $items,
        ];

        jsonResponse($response, 200);
    }

    /**
     * POST /orders
     */
    private function createOrder(): void
    {
        $input = getJsonInput();

        if (empty($input['sold_at']) || empty($input['destination_country']) || empty($input['items'])) {
            jsonResponse(['message' => 'Campi richiesti: sold_at, destination_country, items'], 400);
        }

        if (!is_array($input['items']) || count($input['items']) === 0) {
            jsonResponse(['message' => 'items deve essere un array non vuoto'], 400);
        }

        $this->pdo->beginTransaction();

        try {
            $stmtOrder = $this->pdo->prepare(
                'INSERT INTO orders (sold_at, destination_country)
                 VALUES (:sold_at, :country)'
            );
            $stmtOrder->bindValue(':sold_at', $input['sold_at'], PDO::PARAM_STR);
            $stmtOrder->bindValue(':country', $input['destination_country'], PDO::PARAM_STR);
            $stmtOrder->execute();

            $orderId = (int) $this->pdo->lastInsertId();

            $stmtItem = $this->pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, quantity)
                 VALUES (:order_id, :product_id, :quantity)'
            );

            foreach ($input['items'] as $item) {
                if (empty($item['product_id']) || empty($item['quantity'])) {
                    throw new RuntimeException('Ogni item deve avere product_id e quantity');
                }

                $stmtItem->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $stmtItem->bindValue(':product_id', (int) $item['product_id'], PDO::PARAM_INT);
                $stmtItem->bindValue(':quantity', (int) $item['quantity'], PDO::PARAM_INT);
                $stmtItem->execute();
            }

            $this->pdo->commit();
            refreshGlobalCo2Cache($this->pdo);

            $this->getOrder($orderId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            jsonResponse(['message' => 'Errore durante la creazione ordine'], 500);
        }
    }

    /**
     * PUT/PATCH /orders/{id}
     * Aggiorna dati ordine e, se presenti, sostituisce gli items
     */
    private function updateOrder(int $id): void
    {
        $stmtCheck = $this->pdo->prepare('SELECT id FROM orders WHERE id = :id');
        $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();

        if (!$stmtCheck->fetch()) {
            jsonResponse(['message' => 'Order non trovato'], 404);
        }

        $input = getJsonInput();
        $this->pdo->beginTransaction();

        try {
            $fields = [];
            $params = [':id' => $id];

            if (isset($input['sold_at'])) {
                $fields[] = 'sold_at = :sold_at';
                $params[':sold_at'] = $input['sold_at'];
            }

            if (isset($input['destination_country'])) {
                $fields[] = 'destination_country = :country';
                $params[':country'] = $input['destination_country'];
            }

            if (!empty($fields)) {
                $sql = 'UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id = :id';
                $stmtOrder = $this->pdo->prepare($sql);

                foreach ($params as $key => $value) {
                    if ($key === ':id') {
                        $stmtOrder->bindValue($key, $value, PDO::PARAM_INT);
                    } else {
                        $stmtOrder->bindValue($key, $value);
                    }
                }

                $stmtOrder->execute();
            }

            if (isset($input['items'])) {
                if (!is_array($input['items']) || count($input['items']) === 0) {
                    throw new RuntimeException('items deve essere un array non vuoto');
                }

                $stmtDeleteItems = $this->pdo->prepare(
                    'DELETE FROM order_items WHERE order_id = :order_id'
                );
                $stmtDeleteItems->bindValue(':order_id', $id, PDO::PARAM_INT);
                $stmtDeleteItems->execute();

                $stmtItem = $this->pdo->prepare(
                    'INSERT INTO order_items (order_id, product_id, quantity)
                     VALUES (:order_id, :product_id, :quantity)'
                );

                foreach ($input['items'] as $item) {
                    if (empty($item['product_id']) || empty($item['quantity'])) {
                        throw new RuntimeException('Ogni item deve avere product_id e quantity');
                    }

                    $stmtItem->bindValue(':order_id', $id, PDO::PARAM_INT);
                    $stmtItem->bindValue(':product_id', (int) $item['product_id'], PDO::PARAM_INT);
                    $stmtItem->bindValue(':quantity', (int) $item['quantity'], PDO::PARAM_INT);
                    $stmtItem->execute();
                }
            }

            $this->pdo->commit();
            refreshGlobalCo2Cache($this->pdo);

            $this->getOrder($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            jsonResponse(['message' => 'Errore durante l’aggiornamento ordine'], 500);
        }
    }

    /**
     * DELETE /orders/{id}
     */
    private function deleteOrder(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM orders WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            jsonResponse(['message' => 'Order non trovato'], 404);
        }

        refreshGlobalCo2Cache($this->pdo);

        jsonResponse(null, 204);
    }
}