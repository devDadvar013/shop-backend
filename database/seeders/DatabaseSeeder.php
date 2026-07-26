<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Default admin user (used for login) ----
        User::updateOrCreate(
            ['email' => 'admin@shop.io'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        $products = collect([
            ['name' => 'Laptop Bag', 'sku' => 'BAG-001', 'price' => 49.99, 'stock' => 25],
            ['name' => 'Wireless Mouse', 'sku' => 'MOU-001', 'price' => 19.50, 'stock' => 100],
            ['name' => 'Mechanical Keyboard', 'sku' => 'KEY-001', 'price' => 89.00, 'stock' => 30],
            ['name' => 'USB-C Hub', 'sku' => 'HUB-001', 'price' => 35.00, 'stock' => 60],
            ['name' => 'Webcam HD', 'sku' => 'CAM-001', 'price' => 59.00, 'stock' => 18],
            ['name' => 'Desk Lamp', 'sku' => 'LMP-001', 'price' => 24.90, 'stock' => 45],
            ['name' => 'Notebook A5', 'sku' => 'NTB-001', 'price' => 4.50, 'stock' => 200],
            ['name' => 'Coffee Mug', 'sku' => 'MUG-001', 'price' => 9.00, 'stock' => 80],
        ])->map(fn ($p) => Product::create($p));

        $customers = collect([
            ['name' => 'Sara Mohammadi', 'email' => 'sara@example.com', 'phone' => '09120000001', 'address' => 'Tehran'],
            ['name' => 'Ali Rezaei',     'email' => 'ali@example.com',   'phone' => '09120000002', 'address' => 'Isfahan'],
            ['name' => 'Maryam Karimi',  'email' => 'maryam@example.com','phone' => '09120000003', 'address' => 'Shiraz'],
            ['name' => 'Reza Hosseini',  'email' => 'reza@example.com',  'phone' => '09120000004', 'address' => 'Tabriz'],
        ])->map(fn ($c) => Customer::create($c));

        $statuses = [Order::STATUS_PENDING, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_CANCELLED];

        foreach ($customers as $i => $customer) {
            for ($j = 0; $j < 3; $j++) {
                $items = $products->random(rand(1, 3));
                $total = 0;
                $order = Order::create([
                    'customer_id' => $customer->id,
                    'status' => $statuses[($i + $j) % count($statuses)],
                    'notes' => 'Seeded order',
                ]);
                foreach ($items as $product) {
                    $qty = rand(1, 3);
                    $line = $product->price * $qty;
                    $total += $line;
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $product->price,
                        'line_total' => $line,
                    ]);
                }
                $order->update(['total_amount' => $total]);
            }
        }
    }
}
