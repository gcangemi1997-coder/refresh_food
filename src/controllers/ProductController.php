<?php

class ProductController
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
                    $this->getAllProducts();
                } else {
                    $this->getProduct((int) $id);
                }
                break;

            case 'POST':
                $this->createProduct();
                break;

            case 'PUT':
            case 'PATCH':
                if ($id === null) {
                    jsonResponse(['message' => 'Product ID richiesto'], 400);
                }
                $this->updateProduct((int) $id);
                break;

            case 'DELETE':
                if ($id === null) {
                    jsonResponse(['message' => 'Product ID richiesto'], 400);
                }
                $this->deleteProduct((int) $id);
                break;

            default:
                jsonResponse(['message' => 'Metodo non consentito'], 405);
        }
    }

    private function getAllProducts(): void
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, co2_saved_per_unit FROM products ORDER BY id ASC'
        );
        $products = $stmt->fetchAll();

        jsonResponse($products, 200);
    }

    private function getProduct(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, co2_saved_per_unit FROM products WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $product = $stmt->fetch();

        if (!$product) {
            jsonResponse(['message' => 'Product non trovato'], 404);
        }

        jsonResponse($product, 200);
    }

    private function createProduct(): void
    {
        $input = getJsonInput();

        if (empty($input['name']) || !isset($input['co2_saved_per_unit'])) {
            jsonResponse(['message' => 'Campi richiesti: name, co2_saved_per_unit'], 400);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO products (name, co2_saved_per_unit) VALUES (:name, :co2)'
        );
        $stmt->bindValue(':name', $input['name'], PDO::PARAM_STR);
        $stmt->bindValue(':co2', $input['co2_saved_per_unit']);
        $stmt->execute();

        $id = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            'SELECT id, name, co2_saved_per_unit FROM products WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $product = $stmt->fetch();

        jsonResponse($product, 201);
    }

    private function updateProduct(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM products WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if (!$stmt->fetch()) {
            jsonResponse(['message' => 'Product non trovato'], 404);
        }

        $input  = getJsonInput();
        $fields = [];
        $params = [':id' => $id];

        if (isset($input['name'])) {
            $fields[]        = 'name = :name';
            $params[':name'] = $input['name'];
        }
        if (isset($input['co2_saved_per_unit'])) {
            $fields[]        = 'co2_saved_per_unit = :co2';
            $params[':co2']  = $input['co2_saved_per_unit'];
        }

        if (empty($fields)) {
            jsonResponse(['message' => 'Nessun campo da aggiornare'], 400);
        }

        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $stmt = $this->pdo->prepare(
            'SELECT id, name, co2_saved_per_unit FROM products WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $product = $stmt->fetch();

        jsonResponse($product, 200);
    }

    private function deleteProduct(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            jsonResponse(['message' => 'Product non trovato'], 404);
        }

        jsonResponse(null, 204);
    }
}