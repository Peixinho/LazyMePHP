---
id: joins-aggregates
title: Joins & Aggregates
sidebar_position: 3
---

# Joins & Aggregates

## Joins

```php
$rows = Model::query('orders')
    ->join('customers', 'orders.customer_id', 'customers.id')
    ->leftJoin('coupons', 'orders.coupon_id', 'coupons.id')
    ->select('orders.*', 'customers.name AS customer_name', 'coupons.code AS coupon')
    ->where('orders.status', 'open')
    ->orderBy('orders.created_at', 'DESC')
    ->get();

echo $rows[0]->customer_name;
echo $rows[0]->coupon;  // null when the left-join partner is missing
```

Available join methods:

| Method | SQL |
|---|---|
| `join($table, $local, $foreign)` | `INNER JOIN` |
| `leftJoin($table, $local, $foreign)` | `LEFT JOIN` |
| `rightJoin($table, $local, $foreign)` | `RIGHT JOIN` |

Keys like `'orders.customer_id'` are auto-quoted to `"orders"."customer_id"`.

## Selecting columns

By default `SELECT "table".*` is used. Override with `select()`:

```php
// Restrict columns
Model::query('users')->select('id', 'name', 'email')->get();

// Computed expressions — pass raw SQL
Model::query('users')->select('name', 'LENGTH(name) AS name_len')->get();
```

Columns from `select()` are passed as-is to SQL. Quote them yourself when needed (`"column"`).

## GROUP BY and HAVING

```php
$rows = Model::query('orders')
    ->select('customer_id', 'SUM(total) AS revenue', 'COUNT(*) AS cnt')
    ->groupBy('customer_id')
    ->having('revenue', 1000, '>=')   // HAVING "revenue" >= 1000
    ->orderBy('revenue', 'DESC')
    ->get();

echo $rows[0]->revenue;
echo $rows[0]->cnt;
```

`groupBy(column)` auto-quotes the column name. `having(column, value, operator)` also auto-quotes and defaults to `=`.

You can chain multiple `groupBy` calls to group by multiple columns:

```php
->groupBy('year')->groupBy('month')
// GROUP BY "year", "month"
```

## Aggregate-only queries

When you only want a count:

```php
$total = Model::query('orders')->where('status', 'open')->count();
```

For any other aggregate (`SUM`, `AVG`, `MAX`, etc.) use `select()` + `first()`:

```php
$row = Model::query('orders')
    ->select('SUM(total) AS total_revenue')
    ->first();

echo $row->total_revenue;
```
