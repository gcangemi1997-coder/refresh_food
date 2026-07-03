# 🍏 ReFresh Food API

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP badge" />
  <img src="https://img.shields.io/badge/MySQL-8%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL badge" />
  <img src="https://img.shields.io/badge/PDO-Prepared%20Statements-4EA94B?style=for-the-badge" alt="PDO badge" />
  <img src="https://img.shields.io/badge/REST-JSON%20API-FF6F00?style=for-the-badge" alt="REST badge" />
  <img src="https://img.shields.io/badge/Apache-XAMPP%20Ready-D22128?style=for-the-badge&logo=apache&logoColor=white" alt="Apache badge" />
  <img src="https://img.shields.io/badge/Status-Working%20Project-2EA44F?style=for-the-badge" alt="Status badge" />
</p>

A compact and structured **RESTful API** built with **PHP**, **MySQL**, and **PDO** for managing recovered food products, orders, and environmental impact tracking. This project focuses on clean routing, relational data handling, and dynamic CO₂ analytics based on recovered food transactions. [1][2][3][4]

---

## ✨ Overview

ReFresh Food API allows you to manage a catalog of products, create sales orders containing multiple items, and calculate the total CO₂ saved through food recovery operations. The application uses a simple front controller in `public/index.php`, dedicated controllers for each domain, and a MySQL schema with relational integrity between products, orders, and order items. [5][1][2][4]

The project also includes a `stats_cache` table used to store and refresh the global CO₂ total when no filters are applied, while filtered analytics are calculated dynamically through SQL joins. [3][4]

---

## 🚀 Features

### 📦 Products

- Get all products. [1]
- Get a single product by id. [1]
- Create a new product with `name` and `co2_saved_per_unit`. [1]
- Update an existing product. [1]
- Delete a product. [1]

### 🛒 Orders

- Get all orders with their related items. [2]
- Get a single order with product details. [2]
- Create an order with `sold_at`, `destination_country`, and nested `items`. [2]
- Update order data and optionally replace its items. [2]
- Delete an order. [2]
- Automatic CO₂ cache refresh after order creation, update, and deletion. [2][5]

### 📊 CO₂ Analytics

- Get total CO₂ saved from all recorded order items. [3]
- Use global cached stats when no filters are provided. [3][4]
- Filter analytics by:
  - `from`
  - `to`
  - `country`
  - `product_id` [3]

### 🔒 Security and Data Access

- Uses PDO prepared statements with bound parameters across user-driven SQL queries. [1][2][3]
- PDO emulated prepares are disabled in the database connection. [6]
- Uses transactions for order creation and updates involving multiple related inserts. [2]
- Foreign keys enforce relational consistency between `orders`, `products`, and `order_items`. [4]

---

## 🧱 Tech Stack

| Technology      | Purpose                                |
| --------------- | -------------------------------------- |
| PHP 8.x         | Core backend language. [5]             |
| MySQL / MariaDB | Relational database. [4]               |
| PDO             | Secure database access layer. [6]      |
| Apache / XAMPP  | Local development environment. [7]     |
| JSON            | Request and response format. [2][1][3] |

---

## 📁 Project Structure

```text
refresh_food/
├── public/
│   └── index.php
├── src/
│   ├── controllers/
│   │   ├── OrderController.php
│   │   ├── ProductController.php
│   │   └── StatsController.php
│   ├── config.php
│   └── Database.php
└── migration.sql
```

- `public/index.php` is the front controller and router entry point. [5]
- `src/controllers/` contains the API domain logic for products, orders, and analytics. [1][2][3]
- `src/config.php` contains database configuration constants. [8]
- `src/Database.php` handles the PDO singleton connection. [6]
- `migration.sql` creates the schema and initializes the stats cache row. [4]

---

## 🗄️ Database Schema

The project is based on four main tables: [4]

- `products`: stores product name and CO₂ saved per unit. [4]
- `orders`: stores sale date and destination country. [4]
- `order_items`: links orders to products with quantities. [4]
- `stats_cache`: stores the global cached CO₂ total and update timestamp. [4]

### Relationship model

- One order can contain multiple items. [4]
- One product can appear in many order items. [4]
- `order_items.order_id` uses `ON DELETE CASCADE`. [4]
- `order_items.product_id` uses `ON DELETE RESTRICT`. [4]

---

## 🔌 API Endpoints

### Products

| Method      | Endpoint         | Description                     |
| ----------- | ---------------- | ------------------------------- |
| GET         | `/products`      | Retrieve all products. [1]      |
| GET         | `/products/{id}` | Retrieve one product by id. [1] |
| POST        | `/products`      | Create a new product. [1]       |
| PUT / PATCH | `/products/{id}` | Update a product. [1]           |
| DELETE      | `/products/{id}` | Delete a product. [1]           |

### Orders

| Method      | Endpoint       | Description                                       |
| ----------- | -------------- | ------------------------------------------------- |
| GET         | `/orders`      | Retrieve all orders with nested items. [2]        |
| GET         | `/orders/{id}` | Retrieve one order with nested items. [2]         |
| POST        | `/orders`      | Create a new order. [2]                           |
| PUT / PATCH | `/orders/{id}` | Update an order and optionally replace items. [2] |
| DELETE      | `/orders/{id}` | Delete an order. [2]                              |

### Stats

| Method | Endpoint                                                | Description                          |
| ------ | ------------------------------------------------------- | ------------------------------------ |
| GET    | `/stats/co2`                                            | Retrieve total CO₂ saved. [3]        |
| GET    | `/stats/co2?from=...&to=...&country=...&product_id=...` | Retrieve filtered CO₂ analytics. [3] |

---

## 🧮 CO₂ Calculation Logic

The total environmental impact is calculated by joining orders, order items, and products, then summing the product quantity multiplied by the saved CO₂ per unit. This logic is used both for global cache refresh and for filtered analytics queries. [5][3]

Formula:

$$Total\ CO_2 Saved = \sum_{i=1}^{n} (Quantity_i \times CO_{2\_saved\_per\_unit,i})$$

---

## 📥 Example Requests

### Create a product

```bash
curl -X POST http://localhost/refresh_food/public/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Recovered Apples",
    "co2_saved_per_unit": 0.75
  }'
```

Based on the product creation flow, the API expects `name` and `co2_saved_per_unit` in the JSON body. [1]

### Create an order

```bash
curl -X POST http://localhost/refresh_food/public/orders \
  -H "Content-Type: application/json" \
  -d '{
    "sold_at": "2026-07-03 10:00:00",
    "destination_country": "IT",
    "items": [
      { "product_id": 1, "quantity": 10 },
      { "product_id": 2, "quantity": 4 }
    ]
  }'
```

The order payload matches the controller logic that requires `sold_at`, `destination_country`, and a non-empty `items` array. [2]

### Get filtered CO₂ stats

```bash
curl "http://localhost/refresh_food/public/stats/co2?from=2026-07-01&to=2026-07-31&country=IT&product_id=1"
```

The stats endpoint supports `from`, `to`, `country`, and `product_id` as query parameters. [3]

---

## ▶️ How to Run Locally

### 1. Clone the repository

```bash
git clone <your-repository-url>
cd refresh_food
```

### 2. Move the project into your web server directory

For XAMPP on macOS, place it under:

```text
/Applications/XAMPP/xamppfiles/htdocs/
```

Your final path can look like this: [7]

```text
/Applications/XAMPP/xamppfiles/htdocs/refresh_food
```

### 3. Create the database

Open MySQL and run:

```sql
SOURCE migration.sql;
```

The SQL migration creates the `refresh_food` database, the required tables, indexes, foreign keys, and initializes the `stats_cache` table. [4]

### 4. Configure database credentials

Update `src/config.php` with your local database values: [8]

```php
<?php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'refresh_food';
const DB_USER = 'root';
const DB_PASS = 'your_password';
```

### 5. Start Apache and MySQL

Run Apache and MySQL from XAMPP, then open the project in your browser or test it with cURL/Postman. The application entry point is the public front controller. [7][5]

---

## 🧠 Routing

The application uses a single front controller located in `public/index.php`, which resolves the request path and dispatches it to `ProductController`, `OrderController`, or `StatsController`. The `/stats/co2` route is explicitly handled as a nested route. [5]

---

## ✅ Response Behavior

The controllers return JSON responses and use standard HTTP status codes such as `200`, `201`, `204`, `400`, `404`, `405`, and `500` depending on the request result. This behavior is visible across products, orders, and stats endpoints. [1][2][3]

---

## 👨‍💻 Personal Links

- GitHub Portfolio Repository: [GC-portfolio](https://github.com/gcangemi1997-coder/GC-portfolio)
- Portfolio Website: [gcangemi1997-coder.github.io](https://gcangemi1997-coder.github.io/)
- LinkedIn: [giorgio-cangemi-7b4b77172](https://www.linkedin.com/in/giorgio-cangemi-7b4b77172/)

---

## 📌 Notes

This README is aligned with the current project structure and implemented features visible in the provided codebase. In particular, the exposed routes are based on the current front controller, the analytics endpoint is `/stats/co2`, and the project uses `sold_at`, `destination_country`, and `co2_saved_per_unit` as the actual field names. [5][2][1][3][4]
