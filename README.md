<h1 align="center"> 🍏 ReFresh Food API

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP badge" />
  <img src="https://img.shields.io/badge/MySQL-8%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL badge" />
  <img src="https://img.shields.io/badge/PDO-Prepared%20Statements-4EA94B?style=for-the-badge" alt="PDO badge" />
  <img src="https://img.shields.io/badge/REST-JSON%20API-FF6F00?style=for-the-badge" alt="REST badge" />
  <img src="https://img.shields.io/badge/Apache-XAMPP%20Ready-D22128?style=for-the-badge&logo=apache&logoColor=white" alt="Apache badge" />
  <img src="https://img.shields.io/badge/Status-Working%20Project-2EA44F?style=for-the-badge" alt="Status badge" />
</p>

A compact and structured **RESTful API** built with **PHP**, **MySQL**, and **PDO** for managing recovered food products, orders, and environmental impact tracking. This project focuses on clean routing, relational data handling, and dynamic CO₂ analytics based on recovered food transactions.

---

## ✨ Overview

ReFresh Food API allows you to manage a catalog of products, create sales orders containing multiple items, and calculate the total CO₂ saved through food recovery operations. The application uses a simple front controller in `public/index.php`, dedicated controllers for each domain, and a MySQL schema with relational integrity between products, orders, and order items.

The project also includes a `stats_cache` table used to store and refresh the global CO₂ total when no filters are applied, while filtered analytics are calculated dynamically through SQL joins.

---

## 🚀 Features

### 📦 Products

- Get all products.
- Get a single product by id.
- Create a new product with `name` and `co2_saved_per_unit`.
- Update an existing product.
- Delete a product.

### 🛒 Orders

- Get all orders with their related items.
- Get a single order with product details.
- Create an order with `sold_at`, `destination_country`, and nested `items`.
- Update order data and optionally replace its items.
- Delete an order.
- Automatic CO₂ cache refresh after order creation, update, and deletion.

### 📊 CO₂ Analytics

- Get total CO₂ saved from all recorded order items.
- Use global cached stats when no filters are provided.
- Filter analytics by:
  - `from`
  - `to`
  - `country`
  - `product_id`

### 🔒 Security and Data Access

- Uses PDO prepared statements with bound parameters across user-driven SQL queries.
- PDO emulated prepares are disabled in the database connection.
- Uses transactions for order creation and updates involving multiple related inserts.
- Foreign keys enforce relational consistency between `orders`, `products`, and `order_items`.

---

## 🧱 Tech Stack

| Technology      | Purpose                        |
| --------------- | ------------------------------ |
| PHP 8.x         | Core backend language.         |
| MySQL / MariaDB | Relational database.           |
| PDO             | Secure database access layer.  |
| Apache / XAMPP  | Local development environment. |
| JSON            | Request and response format.   |

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

- `public/index.php` is the front controller and router entry point.
- `src/controllers/` contains the API domain logic for products, orders, and analytics.
- `src/config.php` contains database configuration constants.
- `src/Database.php` handles the PDO singleton connection.
- `migration.sql` creates the schema and initializes the stats cache row.

---

## 🗄️ Database Schema

The project is based on four main tables:

- `products`: stores product name and CO₂ saved per unit.
- `orders`: stores sale date and destination country.
- `order_items`: links orders to products with quantities.
- `stats_cache`: stores the global cached CO₂ total and update timestamp.

### Relationship model

- One order can contain multiple items.
- One product can appear in many order items.
- `order_items.order_id` uses `ON DELETE CASCADE`.
- `order_items.product_id` uses `ON DELETE RESTRICT`.

---

## 🔌 API Endpoints

### Products

| Method      | Endpoint         | Description                 |
| ----------- | ---------------- | --------------------------- |
| GET         | `/products`      | Retrieve all products.      |
| GET         | `/products/{id}` | Retrieve one product by id. |
| POST        | `/products`      | Create a new product.       |
| PUT / PATCH | `/products/{id}` | Update a product.           |
| DELETE      | `/products/{id}` | Delete a product.           |

### Orders

| Method      | Endpoint       | Description                                   |
| ----------- | -------------- | --------------------------------------------- |
| GET         | `/orders`      | Retrieve all orders with nested items.        |
| GET         | `/orders/{id}` | Retrieve one order with nested items.         |
| POST        | `/orders`      | Create a new order.                           |
| PUT / PATCH | `/orders/{id}` | Update an order and optionally replace items. |
| DELETE      | `/orders/{id}` | Delete an order.                              |

### Stats

| Method | Endpoint                                                | Description                      |
| ------ | ------------------------------------------------------- | -------------------------------- |
| GET    | `/stats/co2`                                            | Retrieve total CO₂ saved.        |
| GET    | `/stats/co2?from=...&to=...&country=...&product_id=...` | Retrieve filtered CO₂ analytics. |

---

## 🧮 CO₂ Calculation Logic

The total environmental impact is calculated by joining orders, order items, and products, then summing the product quantity multiplied by the saved CO₂ per unit. This logic is used both for global cache refresh and for filtered analytics queries.

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

Based on the product creation flow, the API expects `name` and `co2_saved_per_unit` in the JSON body.

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

The order payload matches the controller logic that requires `sold_at`, `destination_country`, and a non-empty `items` array.

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

Your final path can look like this:

```text
/Applications/XAMPP/xamppfiles/htdocs/refresh_food
```

### 3. Create the database

Open MySQL and run:

```sql
SOURCE migration.sql;
```

The SQL migration creates the `refresh_food` database, the required tables, indexes, foreign keys, and initializes the `stats_cache` table.

### 4. Configure database credentials

Update `src/config.php` with your local database values:

```php
<?php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'refresh_food';
const DB_USER = 'root';
const DB_PASS = 'your_password';
```

### 5. Start Apache and MySQL

Run Apache and MySQL from XAMPP, then open the project in your browser or test it with cURL/Postman. The application entry point is the public front controller.

---

## 🧠 Routing

The application uses a single front controller located in `public/index.php`, which resolves the request path and dispatches it to `ProductController`, `OrderController`, or `StatsController`. The `/stats/co2` route is explicitly handled as a nested route.

---

## ✅ Response Behavior

The controllers return JSON responses and use standard HTTP status codes such as `200`, `201`, `204`, `400`, `404`, `405`, and `500` depending on the request result. This behavior is visible across products, orders, and stats endpoints.

---

## 👨‍💻 Personal Links

- Portfolio Website: [GC-portfolio](https://gc-portfolio-eta.vercel.app/)
- Github Repo: [gcangemi1997-coder.github.io](https://github.com/gcangemi1997-coder)
- LinkedIn: [giorgio-cangemi-7b4b77172](https://www.linkedin.com/in/giorgio-cangemi-7b4b77172/)

---

## 📌 Notes

This README is aligned with the current project structure and implemented features visible in the provided codebase. In particular, the exposed routes are based on the current front controller, the analytics endpoint is `/stats/co2`, and the project uses `sold_at`, `destination_country`, and `co2_saved_per_unit` as the actual field names.
