<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin user ────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@mattelecom.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('Admin@1234'),
            ]
        );

        // ── Warehouses ────────────────────────────────────────────────
        $mainWarehouse = Warehouse::firstOrCreate(
            ['name' => 'Main Store — Head Office'],
            ['location' => 'Phnom Penh HQ — Ground Floor Storage', 'is_default' => true]
        );

        $techWarehouse = Warehouse::firstOrCreate(
            ['name' => 'Field Tech Store'],
            ['location' => 'Technical Department — Equipment Room', 'is_default' => false]
        );

        $branchWarehouse = Warehouse::firstOrCreate(
            ['name' => 'Branch Store'],
            ['location' => 'Siem Reap Branch Office', 'is_default' => false]
        );

        // ── Categories ────────────────────────────────────────────────
        $cats = [
            ['name' => 'Network Equipment',   'slug' => 'network-equipment'],
            ['name' => 'Cables & Connectors', 'slug' => 'cables-connectors'],
            ['name' => 'SIM & Accessories',   'slug' => 'sim-accessories'],
            ['name' => 'Power Equipment',     'slug' => 'power-equipment'],
            ['name' => 'Tools & Testing',     'slug' => 'tools-testing'],
            ['name' => 'Office Supplies',     'slug' => 'office-supplies'],
            ['name' => 'CCTV & Security',     'slug' => 'cctv-security'],
        ];

        foreach ($cats as $cat) {
            Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        $network  = Category::where('slug', 'network-equipment')->first();
        $cables   = Category::where('slug', 'cables-connectors')->first();
        $sim      = Category::where('slug', 'sim-accessories')->first();
        $power    = Category::where('slug', 'power-equipment')->first();
        $tools    = Category::where('slug', 'tools-testing')->first();
        $office   = Category::where('slug', 'office-supplies')->first();
        $cctv     = Category::where('slug', 'cctv-security')->first();

        // ── Suppliers ─────────────────────────────────────────────────
        $supplierCN = Supplier::firstOrCreate(
            ['name' => 'Huawei Supply Chain — China'],
            [
                'country'        => 'China',
                'currency'       => 'CNY',
                'contact_name'   => 'Zhang Wei',
                'contact_email'  => 'supply@huawei-partner.com',
                'contact_phone'  => '+86 755 8888 0001',
                'website'        => 'https://supplier.huawei.com',
                'lead_time_days' => 25,
                'notes'          => 'Primary overseas supplier for routers, ONU/OLT equipment and network hardware',
                'is_active'      => true,
            ]
        );

        $supplierSG = Supplier::firstOrCreate(
            ['name' => 'NetStar Singapore Pte Ltd'],
            [
                'country'        => 'Singapore',
                'currency'       => 'SGD',
                'contact_name'   => 'Kevin Tan',
                'contact_email'  => 'kevin@netstar.sg',
                'contact_phone'  => '+65 6123 4567',
                'website'        => 'https://netstar.sg',
                'lead_time_days' => 10,
                'notes'          => 'Regional supplier for Cisco, Mikrotik and power equipment',
                'is_active'      => true,
            ]
        );

        $supplierKH = Supplier::firstOrCreate(
            ['name' => 'Phnom Penh Tech Wholesale'],
            [
                'country'        => 'Cambodia',
                'currency'       => 'USD',
                'contact_name'   => 'Vantha Keo',
                'contact_email'  => 'vantha@pptechwholesale.com.kh',
                'contact_phone'  => '+855 23 456 789',
                'lead_time_days' => 1,
                'notes'          => 'Local supplier for cables, tools and urgent stock',
                'is_active'      => true,
            ]
        );

        $supplierTH = Supplier::firstOrCreate(
            ['name' => 'Bangkok Telecom Parts Co.'],
            [
                'country'        => 'Thailand',
                'currency'       => 'THB',
                'contact_name'   => 'Preecha S.',
                'contact_email'  => 'sales@bkktelecomparts.co.th',
                'contact_phone'  => '+66 2 123 4567',
                'lead_time_days' => 7,
                'notes'          => 'Supplier for SIM card accessories, power banks and tools',
                'is_active'      => true,
            ]
        );

        // ── Products ──────────────────────────────────────────────────
        $products = [
            // Network Equipment
            [
                'sku'           => 'NET-001',
                'name'          => 'Huawei EchoLife HG8145V5 ONU',
                'description'   => 'GPON ONU with 4 LAN ports + 2 POTS + WiFi 2.4/5GHz. Used for FTTH installations.',
                'category_id'   => $network->id,
                'supplier_id'   => $supplierCN->id,
                'unit'          => 'pcs',
                'unit_cost'     => 28.00,
                'cost_currency' => 'USD',
                'selling_price' => 45.00,
                'reorder_point' => 50,
                'reorder_qty'   => 200,
                'is_active'     => true,
            ],
            [
                'sku'           => 'NET-002',
                'name'          => 'Huawei MA5608T OLT Chassis',
                'description'   => '2U OLT chassis supporting up to 8 GPON/XG-PON line cards',
                'category_id'   => $network->id,
                'supplier_id'   => $supplierCN->id,
                'unit'          => 'pcs',
                'unit_cost'     => 1800.00,
                'cost_currency' => 'USD',
                'selling_price' => 2400.00,
                'reorder_point' => 2,
                'reorder_qty'   => 5,
                'is_active'     => true,
            ],
            [
                'sku'           => 'NET-003',
                'name'          => 'MikroTik RB960PGS hEX PoE',
                'description'   => '5-port Gigabit router with PoE output, RouterOS L4',
                'category_id'   => $network->id,
                'supplier_id'   => $supplierSG->id,
                'unit'          => 'pcs',
                'unit_cost'     => 65.00,
                'cost_currency' => 'USD',
                'selling_price' => 90.00,
                'reorder_point' => 10,
                'reorder_qty'   => 30,
                'is_active'     => true,
            ],
            [
                'sku'           => 'NET-004',
                'name'          => 'Cisco SG350-28 Managed Switch',
                'description'   => '28-port Gigabit managed switch with 2 combo SFP ports',
                'category_id'   => $network->id,
                'supplier_id'   => $supplierSG->id,
                'unit'          => 'pcs',
                'unit_cost'     => 320.00,
                'cost_currency' => 'USD',
                'selling_price' => 420.00,
                'reorder_point' => 3,
                'reorder_qty'   => 10,
                'is_active'     => true,
            ],
            [
                'sku'           => 'NET-005',
                'name'          => 'Ubiquiti UniFi AP AC LR',
                'description'   => 'Long-range indoor WiFi access point, 802.11ac dual band',
                'category_id'   => $network->id,
                'supplier_id'   => $supplierSG->id,
                'unit'          => 'pcs',
                'unit_cost'     => 85.00,
                'cost_currency' => 'USD',
                'selling_price' => 115.00,
                'reorder_point' => 8,
                'reorder_qty'   => 25,
                'is_active'     => true,
            ],
            // Cables & Connectors
            [
                'sku'           => 'CAB-001',
                'name'          => 'Single Mode Fiber Optic Cable (per meter)',
                'description'   => 'G.652D single mode fiber optic cable, outdoor armored',
                'category_id'   => $cables->id,
                'supplier_id'   => $supplierCN->id,
                'unit'          => 'meter',
                'unit_cost'     => 0.18,
                'cost_currency' => 'USD',
                'selling_price' => 0.35,
                'reorder_point' => 1000,
                'reorder_qty'   => 5000,
                'is_active'     => true,
            ],
            [
                'sku'           => 'CAB-002',
                'name'          => 'CAT6 UTP Cable (per meter)',
                'description'   => 'CAT6 unshielded twisted pair, 23AWG, indoor',
                'category_id'   => $cables->id,
                'supplier_id'   => $supplierKH->id,
                'unit'          => 'meter',
                'unit_cost'     => 0.12,
                'cost_currency' => 'USD',
                'selling_price' => 0.25,
                'reorder_point' => 500,
                'reorder_qty'   => 2000,
                'is_active'     => true,
            ],
            [
                'sku'           => 'CAB-003',
                'name'          => 'SC/APC Fiber Connector (box of 100)',
                'description'   => 'SC/APC single mode fiber optic connector, ceramic ferrule',
                'category_id'   => $cables->id,
                'supplier_id'   => $supplierCN->id,
                'unit'          => 'box',
                'unit_cost'     => 18.00,
                'cost_currency' => 'USD',
                'selling_price' => 30.00,
                'reorder_point' => 5,
                'reorder_qty'   => 20,
                'is_active'     => true,
            ],
            [
                'sku'           => 'CAB-004',
                'name'          => 'RJ45 Connector (bag of 100)',
                'description'   => 'CAT6 RJ45 pass-through connectors, gold plated',
                'category_id'   => $cables->id,
                'supplier_id'   => $supplierKH->id,
                'unit'          => 'bag',
                'unit_cost'     => 3.50,
                'cost_currency' => 'USD',
                'selling_price' => 6.00,
                'reorder_point' => 10,
                'reorder_qty'   => 30,
                'is_active'     => true,
            ],
            // SIM & Accessories
            [
                'sku'           => 'SIM-001',
                'name'          => 'SIM Card — Blank Programmable',
                'description'   => 'Blank SIM cards for ISP provisioning, mini/micro/nano triple cut',
                'category_id'   => $sim->id,
                'supplier_id'   => $supplierTH->id,
                'unit'          => 'pcs',
                'unit_cost'     => 0.80,
                'cost_currency' => 'USD',
                'selling_price' => 2.00,
                'reorder_point' => 200,
                'reorder_qty'   => 1000,
                'is_active'     => true,
            ],
            [
                'sku'           => 'SIM-002',
                'name'          => 'SIM Card Tray Adapter Set',
                'description'   => 'Universal SIM adapter set nano to micro to standard',
                'category_id'   => $sim->id,
                'supplier_id'   => $supplierTH->id,
                'unit'          => 'set',
                'unit_cost'     => 0.50,
                'cost_currency' => 'USD',
                'selling_price' => 1.50,
                'reorder_point' => 50,
                'reorder_qty'   => 200,
                'is_active'     => true,
            ],
            // Power Equipment
            [
                'sku'           => 'PWR-001',
                'name'          => 'APC UPS 1500VA',
                'description'   => 'APC Back-UPS 1500VA/900W, 6 outlets, USB connectivity',
                'category_id'   => $power->id,
                'supplier_id'   => $supplierSG->id,
                'unit'          => 'pcs',
                'unit_cost'     => 180.00,
                'cost_currency' => 'USD',
                'selling_price' => 240.00,
                'reorder_point' => 3,
                'reorder_qty'   => 10,
                'is_active'     => true,
            ],
            [
                'sku'           => 'PWR-002',
                'name'          => '48V PoE Injector',
                'description'   => '802.3af/at PoE injector, 48V 0.5A, for access points',
                'category_id'   => $power->id,
                'supplier_id'   => $supplierKH->id,
                'unit'          => 'pcs',
                'unit_cost'     => 8.00,
                'cost_currency' => 'USD',
                'selling_price' => 14.00,
                'reorder_point' => 15,
                'reorder_qty'   => 50,
                'is_active'     => true,
            ],
            [
                'sku'           => 'PWR-003',
                'name'          => 'Solar Panel 100W Monocrystalline',
                'description'   => '100W mono solar panel for remote tower/BTS sites',
                'category_id'   => $power->id,
                'supplier_id'   => $supplierCN->id,
                'unit'          => 'pcs',
                'unit_cost'     => 55.00,
                'cost_currency' => 'USD',
                'selling_price' => 85.00,
                'reorder_point' => 5,
                'reorder_qty'   => 20,
                'is_active'     => true,
            ],
            // Tools & Testing
            [
                'sku'           => 'TOOL-001',
                'name'          => 'Fiber Optic Cleaver',
                'description'   => 'High precision fiber cleaver for FTTH splicing work',
                'category_id'   => $tools->id,
                'supplier_id'   => $supplierCN->id,
                'unit'          => 'pcs',
                'unit_cost'     => 45.00,
                'cost_currency' => 'USD',
                'selling_price' => 70.00,
                'reorder_point' => 2,
                'reorder_qty'   => 5,
                'is_active'     => true,
            ],
            [
                'sku'           => 'TOOL-002',
                'name'          => 'Optical Power Meter',
                'description'   => 'Handheld optical power meter -70 to +10 dBm, 850/1300/1310/1490/1550nm',
                'category_id'   => $tools->id,
                'supplier_id'   => $supplierCN->id,
                'unit'          => 'pcs',
                'unit_cost'     => 35.00,
                'cost_currency' => 'USD',
                'selling_price' => 55.00,
                'reorder_point' => 3,
                'reorder_qty'   => 8,
                'is_active'     => true,
            ],
            [
                'sku'           => 'TOOL-003',
                'name'          => 'Cable Crimping Tool Set',
                'description'   => 'RJ45/RJ11 crimping tool with wire stripper and cutter',
                'category_id'   => $tools->id,
                'supplier_id'   => $supplierKH->id,
                'unit'          => 'set',
                'unit_cost'     => 6.00,
                'cost_currency' => 'USD',
                'selling_price' => 10.00,
                'reorder_point' => 5,
                'reorder_qty'   => 15,
                'is_active'     => true,
            ],
            // CCTV & Security
            [
                'sku'           => 'CCTV-001',
                'name'          => 'Hikvision IP Camera 4MP',
                'description'   => 'DS-2CD2143G2-I 4MP AcuSense fixed dome network camera',
                'category_id'   => $cctv->id,
                'supplier_id'   => $supplierCN->id,
                'unit'          => 'pcs',
                'unit_cost'     => 48.00,
                'cost_currency' => 'USD',
                'selling_price' => 75.00,
                'reorder_point' => 5,
                'reorder_qty'   => 20,
                'is_active'     => true,
            ],
            [
                'sku'           => 'CCTV-002',
                'name'          => 'NVR 16 Channel',
                'description'   => 'Hikvision DS-7616NI-K2 16ch NVR, supports up to 8MP',
                'category_id'   => $cctv->id,
                'supplier_id'   => $supplierCN->id,
                'unit'          => 'pcs',
                'unit_cost'     => 120.00,
                'cost_currency' => 'USD',
                'selling_price' => 175.00,
                'reorder_point' => 2,
                'reorder_qty'   => 5,
                'is_active'     => true,
            ],
            // Office Supplies
            [
                'sku'           => 'OFF-001',
                'name'          => 'A4 Paper (ream 500 sheets)',
                'description'   => 'Double A 80gsm copy paper',
                'category_id'   => $office->id,
                'supplier_id'   => $supplierKH->id,
                'unit'          => 'ream',
                'unit_cost'     => 4.50,
                'cost_currency' => 'USD',
                'selling_price' => 6.00,
                'reorder_point' => 20,
                'reorder_qty'   => 100,
                'is_active'     => true,
            ],
        ];

        foreach ($products as $p) {
            Product::firstOrCreate(['sku' => $p['sku']], $p);
        }

        // ── Stock Levels ──────────────────────────────────────────────
        $stockData = [
            // SKU          main   tech   branch
            'NET-001'  => [120,   45,    30],
            'NET-002'  => [4,     2,     0],
            'NET-003'  => [18,    12,    5],
            'NET-004'  => [6,     3,     0],
            'NET-005'  => [14,    8,     3],
            'CAB-001'  => [3500,  1200,  800],  // meters
            'CAB-002'  => [2200,  800,   400],  // meters
            'CAB-003'  => [12,    6,     2],
            'CAB-004'  => [18,    10,    4],
            'SIM-001'  => [150,   0,     80],   // low stock — below 200
            'SIM-002'  => [35,    0,     20],   // low stock — below 50
            'PWR-001'  => [5,     3,     1],
            'PWR-002'  => [22,    14,    6],
            'PWR-003'  => [3,     2,     0],    // low stock — below 5
            'TOOL-001' => [4,     6,     1],
            'TOOL-002' => [2,     5,     1],    // low stock — below 3
            'TOOL-003' => [8,     12,    3],
            'CCTV-001' => [18,    4,     5],
            'CCTV-002' => [4,     1,     0],
            'OFF-001'  => [45,    0,     10],
        ];

        $warehouses = [
            0 => $mainWarehouse,
            1 => $techWarehouse,
            2 => $branchWarehouse,
        ];

        foreach ($stockData as $sku => $qtys) {
            $product = Product::where('sku', $sku)->first();
            if (!$product) continue;

            foreach ($qtys as $idx => $qty) {
                if ($qty <= 0) continue;

                $warehouse = $warehouses[$idx];

                StockLevel::firstOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['quantity' => $qty, 'reserved' => 0]
                );

                StockMovement::create([
                    'product_id'      => $product->id,
                    'warehouse_id'    => $warehouse->id,
                    'user_id'         => $admin->id,
                    'type'            => 'receipt',
                    'quantity'        => $qty,
                    'quantity_before' => 0,
                    'quantity_after'  => $qty,
                    'reference'       => 'INITIAL-STOCK',
                    'notes'           => 'Opening stock entry',
                    'moved_at'        => now()->subDays(60),
                ]);
            }
        }

        // ── Purchase Orders ───────────────────────────────────────────
        $po1 = PurchaseOrder::firstOrCreate(
            ['po_number' => 'PO-2026-001'],
            [
                'supplier_id'    => $supplierCN->id,
                'warehouse_id'   => $mainWarehouse->id,
                'user_id'        => $admin->id,
                'status'         => 'received',
                'order_date'     => now()->subDays(50),
                'expected_date'  => now()->subDays(25),
                'received_date'  => now()->subDays(23),
                'currency'       => 'CNY',
                'exchange_rate'  => 0.138,
                'freight_cost'   => 320.00,
                'customs_duty'   => 180.00,
                'total'          => 28400.00,
                'tracking_number'=> 'DHL-CN-7712984',
                'notes'          => 'Q1 2026 FTTH equipment bulk order — ONU and fiber connectors',
            ]
        );

        PurchaseOrderItem::firstOrCreate(
            ['purchase_order_id' => $po1->id, 'product_id' => Product::where('sku', 'NET-001')->first()->id],
            ['qty_ordered' => 200, 'qty_received' => 200, 'unit_price' => 28.00, 'customisation' => 'Pre-configured with MAT Telecom SSID defaults']
        );
        PurchaseOrderItem::firstOrCreate(
            ['purchase_order_id' => $po1->id, 'product_id' => Product::where('sku', 'CAB-003')->first()->id],
            ['qty_ordered' => 20, 'qty_received' => 20, 'unit_price' => 18.00]
        );

        $po2 = PurchaseOrder::firstOrCreate(
            ['po_number' => 'PO-2026-002'],
            [
                'supplier_id'    => $supplierSG->id,
                'warehouse_id'   => $mainWarehouse->id,
                'user_id'        => $admin->id,
                'status'         => 'shipped',
                'order_date'     => now()->subDays(12),
                'expected_date'  => now()->addDays(3),
                'currency'       => 'SGD',
                'exchange_rate'  => 0.74,
                'freight_cost'   => 85.00,
                'customs_duty'   => 0,
                'total'          => 4250.00,
                'tracking_number'=> 'FEDEX-SG-44129871',
                'notes'          => 'MikroTik routers and Cisco switches restock',
            ]
        );

        PurchaseOrderItem::firstOrCreate(
            ['purchase_order_id' => $po2->id, 'product_id' => Product::where('sku', 'NET-003')->first()->id],
            ['qty_ordered' => 30, 'qty_received' => 0, 'unit_price' => 65.00]
        );
        PurchaseOrderItem::firstOrCreate(
            ['purchase_order_id' => $po2->id, 'product_id' => Product::where('sku', 'NET-004')->first()->id],
            ['qty_ordered' => 5, 'qty_received' => 0, 'unit_price' => 320.00]
        );

        $po3 = PurchaseOrder::firstOrCreate(
            ['po_number' => 'PO-2026-003'],
            [
                'supplier_id'    => $supplierTH->id,
                'warehouse_id'   => $mainWarehouse->id,
                'user_id'        => $admin->id,
                'status'         => 'confirmed',
                'order_date'     => now()->subDays(5),
                'expected_date'  => now()->addDays(5),
                'currency'       => 'THB',
                'exchange_rate'  => 0.028,
                'freight_cost'   => 25.00,
                'customs_duty'   => 0,
                'total'          => 1200.00,
                'notes'          => 'Urgent SIM card restock — running low',
            ]
        );

        PurchaseOrderItem::firstOrCreate(
            ['purchase_order_id' => $po3->id, 'product_id' => Product::where('sku', 'SIM-001')->first()->id],
            ['qty_ordered' => 1000, 'qty_received' => 0, 'unit_price' => 0.80]
        );
        PurchaseOrderItem::firstOrCreate(
            ['purchase_order_id' => $po3->id, 'product_id' => Product::where('sku', 'SIM-002')->first()->id],
            ['qty_ordered' => 200, 'qty_received' => 0, 'unit_price' => 0.50]
        );

        $po4 = PurchaseOrder::firstOrCreate(
            ['po_number' => 'PO-2026-004'],
            [
                'supplier_id'    => $supplierCN->id,
                'warehouse_id'   => $techWarehouse->id,
                'user_id'        => $admin->id,
                'status'         => 'draft',
                'order_date'     => now(),
                'expected_date'  => now()->addDays(30),
                'currency'       => 'USD',
                'exchange_rate'  => 1,
                'freight_cost'   => 0,
                'customs_duty'   => 0,
                'total'          => 0,
                'notes'          => 'Planned Q2 CCTV equipment order — pending management approval',
            ]
        );

        PurchaseOrderItem::firstOrCreate(
            ['purchase_order_id' => $po4->id, 'product_id' => Product::where('sku', 'CCTV-001')->first()->id],
            ['qty_ordered' => 20, 'qty_received' => 0, 'unit_price' => 48.00]
        );
        PurchaseOrderItem::firstOrCreate(
            ['purchase_order_id' => $po4->id, 'product_id' => Product::where('sku', 'CCTV-002')->first()->id],
            ['qty_ordered' => 5, 'qty_received' => 0, 'unit_price' => 120.00]
        );

        // ── Chart movement history ─────────────────────────────────────
        $onu     = Product::where('sku', 'NET-001')->first();
        $fiber   = Product::where('sku', 'CAB-001')->first();
        $simCard = Product::where('sku', 'SIM-001')->first();

        for ($i = 30; $i >= 1; $i--) {
            // Daily fiber usage by field techs
            StockMovement::create([
                'product_id'      => $fiber->id,
                'warehouse_id'    => $techWarehouse->id,
                'user_id'         => $admin->id,
                'type'            => 'issue',
                'quantity'        => -rand(50, 200),
                'quantity_before' => rand(1000, 1500),
                'quantity_after'  => rand(800, 1000),
                'reference'       => 'FIELD-WORK',
                'notes'           => 'Daily fiber deployment by field technicians',
                'moved_at'        => now()->subDays($i),
            ]);

            // ONU installations every day
            StockMovement::create([
                'product_id'      => $onu->id,
                'warehouse_id'    => $mainWarehouse->id,
                'user_id'         => $admin->id,
                'type'            => 'issue',
                'quantity'        => -rand(2, 8),
                'quantity_before' => rand(100, 150),
                'quantity_after'  => rand(90, 130),
                'reference'       => 'INSTALL',
                'notes'           => 'Customer FTTH installation',
                'moved_at'        => now()->subDays($i),
            ]);

            // SIM card activations
            StockMovement::create([
                'product_id'      => $simCard->id,
                'warehouse_id'    => $mainWarehouse->id,
                'user_id'         => $admin->id,
                'type'            => 'issue',
                'quantity'        => -rand(5, 20),
                'quantity_before' => rand(100, 200),
                'quantity_after'  => rand(80, 180),
                'reference'       => 'ACTIVATION',
                'notes'           => 'New customer SIM activations',
                'moved_at'        => now()->subDays($i),
            ]);
        }

        $this->command->info('');
        $this->command->info('✅ MAT Telecom demo data seeded successfully!');
        $this->command->info('');
        $this->command->info('   Warehouses : 3 (HQ, Field Tech, Branch)');
        $this->command->info('   Categories : 7 (Network, Cables, SIM, Power, Tools, CCTV, Office)');
        $this->command->info('   Suppliers  : 4 (China, Singapore, Cambodia, Thailand)');
        $this->command->info('   Products   : 20 (ISP/telecom equipment)');
        $this->command->info('   POs        : 4 (received, shipped, confirmed, draft)');
        $this->command->info('   Low stock  : 4 items flagged for reorder');
        $this->command->info('   Movements  : 30 days of field deployment history');
        $this->command->info('');
        $this->command->info('   Admin login: admin@mat.com / admin123');
    }
}