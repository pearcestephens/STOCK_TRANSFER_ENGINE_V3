<?php

/**
 * generate_transfers_enterprise_v2.php
 *
 * Enterprise Stock Transfer Engine — Pass 2 (CLEANED)
 * ---------------------------------------------------
 * - Single, coherent file (no duplicates / stray fragments)
 * - Robust schema resolver (synonyms via INFORMATION_SCHEMA)
 * - Tunable via GET/CLI (see params near bottom)
 * - Simulation vs Apply (transactional on Apply)
 * - Decision Ledger + structured logs
 * - Fair-share allocation w/ hub buffer, safety caps
 * - New-store seeding (demand-aware)
 * - Freight container pick + profitability guard
 * - JSON replay dumps + visible EMERGENCY files
 * - Neural Brain hooks (optional)
 * - GPT categorization-only mode (optional)
 */

declare(strict_types=1);

// ----------------------
// Environment Hardening
// ----------------------
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
date_default_timezone_set('Pacific/Auckland');
set_time_limit(5400);
ini_set('memory_limit', '3072M');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
        echo "FATAL: " . $e['message'] . " in " . $e['file'] . ":" . $e['line'] . PHP_EOL;
    }
});

// ----------------------
// API Action Detection (before heavy includes)
// ----------------------
function get_request_param($key, $default = '') {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

$action = (string)get_request_param('action', '');

if ($action) {
    // Handle API actions with minimal includes
    require_once __DIR__ . "/../../functions/config.php";  // Main config with DB connection
    
    if (!isset($db) || !($db instanceof mysqli)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Database connection not available']);
        exit;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    switch ($action) {
        case 'smart_seed':
            require_once __DIR__ . '/NewStoreSeeder.php';
            $seeder = new NewStoreSeeder($db);
            
            $target_outlet = (int)get_request_param('target_outlet_id', 0);
            if (!$target_outlet) {
                echo json_encode(['success' => false, 'error' => 'target_outlet_id required']);
                exit;
            }
            
            function as_int_simple($val, $def, $min = 1, $max = 100) {
                $val = (int)$val;
                return ($val >= $min && $val <= $max) ? $val : $def;
            }
            
            function boolish_simple($val, $def) {
                return filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $def;
            }
            
            $options = [
                'respect_pack_outers' => boolish_simple(get_request_param('respect_pack_outers', '1'), true),
                'balance_categories' => boolish_simple(get_request_param('balance_categories', '1'), true),
                'max_contribution_per_store' => as_int_simple(get_request_param('max_contribution_per_store', 2), 2, 1, 5),
                'min_source_stock' => as_int_simple(get_request_param('min_source_stock', 5), 5, 1, 20),
                'simulate' => boolish_simple(get_request_param('simulate', '0'), false)
            ];
            
            $result = $seeder->createSmartSeed($target_outlet, [], $options);
            echo json_encode($result);
            exit;
            
        case 'get_outlets':
            $stmt = $db->prepare("
                SELECT outlet_id, outlet_name, outlet_prefix
                FROM vend_outlets 
                WHERE deleted_at IS NULL 
                ORDER BY outlet_name
            ");
            $stmt->execute();
            $outlets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'outlets' => $outlets]);
            exit;
            
        case 'recent_transfers':
            $limit = as_int_simple(get_request_param('limit', 5), 5, 1, 20);
            $stmt = $db->prepare("
                SELECT transfer_id, outlet_from, outlet_to, status, date_created, notes
                FROM stock_transfers 
                ORDER BY date_created DESC 
                LIMIT ?
            ");
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $transfers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'transfers' => $transfers]);
            exit;
            
        case 'stats':
            $stats = [];
            
            // Total transfers
            $stmt = $db->query("SELECT COUNT(*) as count FROM stock_transfers");
            $stats['total_transfers'] = $stmt->fetch_assoc()['count'];
            
            // Pack rules count
            $stmt = $db->query("SELECT COUNT(*) as count FROM pack_rules WHERE enabled = 1");
            $stats['pack_rules'] = $stmt->fetch_assoc()['count'];
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
}

// ----------------------
// Dependencies & DB (for main engine)
// ----------------------
require_once __DIR__ . "/../../functions/config.php";          // expects mysqli $con
require_once __DIR__ . "/../../functions/OpenAIHelper.php";    // GPT integration
require_once __DIR__ . "/neural_brain_integration.php";        // Neural Brain

if (!isset($con) || !($con instanceof mysqli)) {
    http_response_code(500);
    die("Database connection not available.");
}
if (!$con->ping()) {
    error_log("TransferEngine: DB ping failed. Reconnecting...");
    $con->close();
    require __DIR__ . "/../../functions/config.php";
    if (!isset($con) || !($con instanceof mysqli) || !$con->ping()) {
        http_response_code(500);
        die("Failed to establish database connection.");
    }
}
$con->set_charset('utf8mb4');
$con->options(MYSQLI_OPT_CONNECT_TIMEOUT, 30);
$con->options(MYSQLI_OPT_READ_TIMEOUT, 60);

// ----------------------
// Utilities (globals)
// ----------------------
$format = (string)($_POST['format'] ?? $_GET['format'] ?? 'html');  // needed early for echo_html

function echo_html(string $content): void
{
    global $format;
    if ($format === 'html') echo $content;
}
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function boolish($v, bool $default = false): bool
{
    if ($v === null) return $default;
    $v = strtolower((string)$v);
    return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
}
function get_cli_or_get(string $key, $default = null)
{
    if (PHP_SAPI === 'cli') {
        foreach (array_slice($_SERVER['argv'] ?? [], 1) as $arg) {
            if (strpos($arg, '=') !== false) {
                [$k, $v] = explode('=', $arg, 2);
                if ($k === $key) return $v;
            }
        }
    }
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}
function as_int($v, int $def, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
{
    $x = is_numeric($v) ? (int)$v : $def;
    return max($min, min($max, $x));
}
function as_float($v, float $def, float $min = -INF, float $max = INF): float
{
    $x = is_numeric($v) ? (float)$v : $def;
    return max($min, min($max, $x));
}
function ensure_dir(string $path): void
{
    if (!is_dir($path)) @mkdir($path, 0775, true);
}
function jenc($data): string
{
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT);
}

// ----------------------
// Neural Brain (optional)
// ----------------------
$neural_brain = init_neural_brain($con);
if ($neural_brain && $neural_brain->isEnabled()) {
    echo_html("🧠 Neural Brain Enterprise activated - Session: " . $neural_brain->getSessionId() . "<br>\n");
    try {
        $neural_stats = $neural_brain->getStats();
        echo_html("🧠 Neural memory: " . (int)$neural_stats['total_memories'] . " memories, " . (int)$neural_stats['active_agents'] . " agents active<br>\n");
    } catch (Throwable $e) {
        error_log("NeuralBrain stats error: " . $e->getMessage());
    }
} else {
    echo_html("⚠️ Neural Brain integration disabled or unavailable<br>\n");
}

// ----------------------
// Logging & Ledger
// ----------------------
final class Logger
{
    private array $lines = [];
    private int $trace;
    public function __construct(int $trace)
    {
        $this->trace = $trace;
    }
    public function log(int $lvl, string $msg, array $ctx = []): void
    {
        if ($lvl <= $this->trace) $this->lines[] = ['t' => date('c'), 'lvl' => $lvl, 'msg' => $msg, 'ctx' => $ctx];
    }
    public function export(): array
    {
        return $this->lines;
    }
}
final class DecisionLedger
{
    private array $rows = [];
    public function push(string|int $product_id, string|int $store_id, string $step, string $reason, array $context = []): void
    {
        $this->rows[] = ['time' => date('c'), 'product_id' => $product_id, 'store_id' => $store_id, 'step' => $step, 'reason' => $reason, 'context' => $context];
    }
    public function export(): array
    {
        return $this->rows;
    }
}

// ----------------------
// Schema Resolver
// ----------------------
final class SchemaResolver
{
    private mysqli $con;
    private array $cache = [];
    private array $tables;
    private array $aliases;

    public function __construct(mysqli $con)
    {
        $this->con = $con;
        $this->aliases = [
            'inventory' => 'vend_inventory',
            // add more short aliases if you use them elsewhere
        ];
        $this->tables = [
            'vend_products' => [
                'product_id'         => ['id', 'product_id', 'productID'],
                'sku'                => ['handle', 'sku', 'product_handle'],
                'name'               => ['name', 'title', 'product_name'],
                'supplier_id'        => ['supplier_id', 'vendor_id', 'supplier'],
                'brand_id'           => ['brand_id', 'brand', 'brandID'],
                'price'              => ['price_including_tax', 'retail_price', 'price', 'sell_price', 'rrp'],
                'cost'               => ['supply_price', 'cost_price', 'cost', 'buy_price', 'cogs', 'last_cost'],
                'avg_weight_grams'   => ['avg_weight_grams', 'weight_grams', 'avg_weight', 'weight_g'],
                'product_type_code'  => ['type', 'product_type_code', 'type_code'],
                'category_id'        => ['category_id', 'cat_id'], // optional
            ],
            'vend_inventory' => [
                'outlet_id'  => ['outlet_id', 'store_id', 'location_id'],
                'product_id' => ['product_id', 'id_product', 'productID'],
                'on_hand'    => ['inventory_level', 'current_amount', 'on_hand', 'stock_on_hand', 'quantity', 'qty', 'qty_on_hand'],
                'updated_at' => ['updated_at', 'modified_at', 'last_updated'],
            ],
            'vend_sales_line_items' => [
                'sale_id'    => ['sale_id', 'id_sale', 'id'],
                'product_id' => ['product_id'],
                'outlet_id'  => ['outlet_id'],
                'quantity'   => ['quantity', 'qty', 'units'],
                'unit_price' => ['unit_price', 'price', 'line_price'],
                'sold_at'    => ['sold_at', 'sale_date', 'created_at'],
            ],
            'sales_summary_90d' => [
                'product_id'     => ['product_id'],
                'outlet_id'      => ['outlet_id'],
                'units_sold_90d' => ['qty_sold', 'units_sold_90d', 'qty_90d', 'units_sold'],
                'last_sold_at'   => ['last_updated', 'last_sold_at', 'last_sale_at', 'last_sale_date'],
                'trend_score'    => ['trend_score', 'trend', 'velocity_score'],
            ],
            'vend_outlets' => [
                'outlet_id'           => ['id', 'outlet_id', 'store_id'],
                'name'                => ['name', 'outlet_name'],
                'is_warehouse'        => ['is_warehouse', 'is_hub', 'is_distribution_centre'],
                'turnover_multiplier' => ['turn_over_rate', 'turnover_multiplier', 'turnover_mult', 'turnover_pct'],
                'status'              => ['website_active', 'status', 'active', 'enabled'],
            ],
            'product_types' => [
                'product_type_code'        => ['code', 'product_type_code', 'type_code'],
                'default_seed_qty'         => ['default_seed_qty', 'seed_qty_default'],
                'avg_weight_grams_default' => ['avg_weight_grams', 'avg_weight_grams_default', 'default_weight_grams'],
            ],
            'product_classification_unified' => [
                'product_id'    => ['product_id'],
                'type_code'     => ['type_code', 'product_type_code'],
                'category_code' => ['category_code', 'cat_code', 'category'],
                'confidence'    => ['confidence', 'clf_confidence'],
                'reasoning'     => ['reasoning', 'notes', 'rationale'],
                'updated_at'    => ['updated_at'],
            ],
            'category_weights' => [
                'category_code'    => ['category_code'],
                'avg_weight_grams' => ['avg_weight_grams'],
            ],
            'freight_rules' => [
                'container'        => ['container'],
                'max_weight_grams' => ['max_weight_grams'],
                'cost'             => ['cost'],
                'is_active'        => ['active', 'is_active', 'enabled'],
                'sort_order'       => ['sort_order', 'order'],
            ],
            'vend_suppliers' => [
                'supplier_id'           => ['id'],
                'automatic_transferring' => ['automatic_transferring', 'auto_transfer'],
                'name'                  => ['name'],
            ],
            'vend_brands' => [
                'brand_id'              => ['id'],
                'automatic_transferring' => ['enable_store_transfers', 'automatic_transferring'],
                'name'                  => ['name'],
            ],
            'stock_transfers' => [
                'transfer_id'      => ['transfer_id'],
                'outlet_from'      => ['outlet_from', 'source_outlet_id'],
                'outlet_to'        => ['outlet_to', 'dest_outlet_id'],
                'status'           => ['status'],
                'micro_status'     => ['micro_status'],
                'transfer_created_by_user' => ['transfer_created_by_user'],
                'source_module'    => ['source_module'],
                'delivery_mode'    => ['delivery_mode', 'freight_container'],
                'automation_triggered' => ['automation_triggered'],
                'run_id'           => ['run_id'],
                'created_by_system' => ['created_by_system'],
                'product_count'    => ['product_count'],
                'total_quantity'   => ['total_quantity'],
                'date_created'     => ['date_created', 'created_at'],
            ],
            'stock_products_to_transfer' => [
                'primary_key'       => ['primary_key', 'line_id', 'id'],
                'transfer_id'       => ['transfer_id'],
                'product_id'        => ['product_id'],
                'qty_to_transfer'   => ['qty_to_transfer', 'qty'],
                'optimal_qty'       => ['optimal_qty'],
                'demand_forecast'   => ['demand_forecast'],
                'sales_velocity'    => ['sales_velocity'],
                'min_qty_to_remain' => ['min_qty_to_remain'],
            ],
        ];
    }

    public function table(string $alias): string
    {
        return $this->aliases[$alias] ?? $alias;
    }

    public function col(string $table, string $role, bool $strict = true): ?string
    {
        $key = "$table::$role";
        if (isset($this->cache[$key])) return $this->cache[$key];

        $syns = $this->tables[$table][$role] ?? null;
        if (!$syns) {
            $msg = "SCHEMA: No synonyms configured for {$table}.{$role}";
            if ($strict) throw new RuntimeException($msg);
            error_log($msg);
            return null;
        }
        $cols = $this->listColumns($table);
        foreach ($syns as $name) if (in_array($name, $cols, true)) return $this->cache[$key] = $name;
        $lower = array_map('strtolower', $cols);
        foreach ($syns as $name) {
            $i = array_search(strtolower($name), $lower, true);
            if ($i !== false) return $this->cache[$key] = $cols[$i];
        }
        $msg = "SCHEMA: Missing column for {$table}.{$role} (syns=" . implode(',', $syns) . "); actual=" . implode(',', $cols);
        if ($strict) throw new RuntimeException($msg);
        error_log($msg);
        return null;
    }

    public function listColumns(string $table): array
    {
        $k = "cols::$table";
        if (isset($this->cache[$k])) return $this->cache[$k];

        $dbRow = $this->con->query("SELECT DATABASE() AS db");
        if (!$dbRow) throw new RuntimeException("SCHEMA: Could not determine current database: " . $this->con->error);
        $db = $dbRow->fetch_assoc()['db'] ?? '';
        if ($db === '') throw new RuntimeException("SCHEMA: Current database is empty");

        $stmt = $this->con->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        if (!$stmt) throw new RuntimeException("SCHEMA: listColumns prepare failed: " . $this->con->error);
        $stmt->bind_param('ss', $db, $table);
        if (!$stmt->execute()) throw new RuntimeException("SCHEMA: listColumns exec failed: " . $stmt->error);
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) $out[] = $row['COLUMN_NAME'];
        $stmt->close();
        return $this->cache[$k] = $out;
    }
}

// ----------------------
// Data Access Layer
// ----------------------
final class DAL
{
    private mysqli $con;
    private SchemaResolver $S;
    private Logger $LOG;

    public function __construct(mysqli $con, SchemaResolver $S, Logger $LOG)
    {
        $this->con = $con;
        $this->S = $S;
        $this->LOG = $LOG;
    }

    public function q(string $sql, array $params = [], string $types = ''): mysqli_result
    {
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new RuntimeException("Prepare failed: " . $this->con->error);
        if ($params) {
            if (!$types) $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) throw new RuntimeException("Exec failed: " . $stmt->error);
        if ($stmt->field_count === 0) throw new RuntimeException("No result set for query expecting rows.");
        $res = $stmt->get_result();
        if (!$res) throw new RuntimeException("get_result failed: " . $stmt->error);
        return $res;
    }
    public function exec(string $sql, array $params = [], string $types = ''): int
    {
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new RuntimeException("Prepare failed: " . $this->con->error);
        if ($params) {
            if (!$types) $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) throw new RuntimeException("Exec failed: " . $stmt->error);
        return $stmt->affected_rows;
    }
    public function insertId(): int
    {
        return (int)$this->con->insert_id;
    }

    /** Inventory rows count for store (new-store detection) */
    public function getStoreInventoryCount(string $outlet_id): int
    {
        $t = $this->S->table('inventory');
        $oid = $this->S->col($t, 'outlet_id');
        $sql = "SELECT COUNT(*) as cnt FROM $t WHERE $oid = ?";
        $res = $this->q($sql, [$outlet_id], 's');
        $row = $res->fetch_assoc();
        return (int)($row['cnt'] ?? 0);
    }

    public function outlets(): array
    {
        $t = 'vend_outlets';
        $id  = $this->S->col($t, 'outlet_id');
        $nm  = $this->S->col($t, 'name');
        $hub = $this->S->col($t, 'is_warehouse');
        $tm  = $this->S->col($t, 'turnover_multiplier');
        $st  = $this->S->col($t, 'status');

        $sql = "SELECT $id AS outlet_id, $nm AS name, $hub AS is_warehouse, $tm AS turnover_multiplier, $st AS status
                FROM $t
                WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' OR deleted_at = 0)
                  AND $st = 1
                ORDER BY $nm";

        $out = [];
        $res = $this->q($sql);
        while ($r = $res->fetch_assoc()) {
            $r['is_warehouse']        = (int)$r['is_warehouse'];
            $r['turnover_multiplier'] = (float)($r['turnover_multiplier'] ?: 1.0);
            if ($r['turnover_multiplier'] < 0.1) $r['turnover_multiplier'] = 1.0;
            $out[(string)$r['outlet_id']] = $r;
        }
        if (!$out) error_log("OUTLETS: 0 active outlets returned. Check vend_outlets.website_active and deleted_at flags.");
        return $out;
    }

    public function freightRules(): array
    {
        $t  = 'freight_rules';
        $c  = $this->S->col($t, 'container');
        $mw = $this->S->col($t, 'max_weight_grams');
        $co = $this->S->col($t, 'cost');
        $ia = $this->S->col($t, 'is_active', false);
        $where = $ia ? "WHERE $ia = 1" : "";
        $sql   = "SELECT $c AS container, $mw AS max_weight_grams, $co AS cost FROM $t $where ORDER BY $mw ASC";
        $out = [];
        $res = $this->q($sql);
        while ($r = $res->fetch_assoc()) {
            $r['max_weight_grams'] = (int)$r['max_weight_grams'];
            $r['cost'] = (float)$r['cost'];
            $out[] = $r;
        }
        if (!$out) error_log("FREIGHT: 0 active rules. Populate freight_rules (container,max_weight_grams,cost[,is_active])");
        return $out;
    }

    public function categoryWeights(): array
    {
        $t = 'category_weights';
        $cc = $this->S->col($t, 'category_code');
        $aw = $this->S->col($t, 'avg_weight_grams');
        $res = $this->q("SELECT $cc AS category_code,$aw AS avg_weight_grams FROM $t");
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(string)$r['category_code']] = (float)$r['avg_weight_grams'];
        return $out;
    }

    public function getSupplierInfo(array $supplier_ids): array
    {
        if (!$supplier_ids) return [];
        $t = 'vend_suppliers';
        $sid = $this->S->col($t, 'supplier_id');
        $auto = $this->S->col($t, 'automatic_transferring', false);
        $in = implode(',', array_fill(0, count($supplier_ids), '?'));
        $sql = "SELECT $sid AS supplier_id" . ($auto ? ", $auto AS automatic_transferring" : "") . " FROM $t WHERE $sid IN ($in)";
        $res = $this->q($sql, array_values($supplier_ids), str_repeat('s', count($supplier_ids)));
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(string)$r['supplier_id']] = ['supplier_id' => $r['supplier_id'], 'automatic_transferring' => isset($r['automatic_transferring']) ? (int)$r['automatic_transferring'] : 1];
        return $out;
    }

    public function getBrandInfo(array $brand_ids): array
    {
        if (!$brand_ids) return [];
        $t = 'vend_brands';
        $bid = $this->S->col($t, 'brand_id');
        $auto = $this->S->col($t, 'automatic_transferring', false);
        $in = implode(',', array_fill(0, count($brand_ids), '?'));
        $sql = "SELECT $bid AS brand_id" . ($auto ? ", $auto AS automatic_transferring" : "") . " FROM $t WHERE $bid IN ($in)";
        $res = $this->q($sql, array_values($brand_ids), str_repeat('s', count($brand_ids)));
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(string)$r['brand_id']] = ['brand_id' => $r['brand_id'], 'automatic_transferring' => isset($r['automatic_transferring']) ? (int)$r['automatic_transferring'] : 1];
        return $out;
    }

    /** Hub candidates with dynamic safe filtering (drinks etc), robust to missing columns */
    public function candidatesFromHub(string $hub_outlet_id, int $limit = 0): array
    {
        $t = 'vend_inventory';
        $tp = 'vend_products';
        $ts = 'vend_suppliers';
        $tb = 'vend_brands';
        $pid = $this->S->col($t, 'product_id');
        $oid = $this->S->col($t, 'outlet_id');
        $oh = $this->S->col($t, 'on_hand');
        $p_pid = $this->S->col($tp, 'product_id');
        $p_sid = $this->S->col($tp, 'supplier_id');
        $p_bid = $this->S->col($tp, 'brand_id');
        $p_name = $this->S->col($tp, 'name');

        // Optional product flags (add only if exist)
        $tp_cols = $this->S->listColumns($tp);
        $conds = ["i.$oid = ?", "i.$oh > 0"];
        if (in_array('has_inventory', $tp_cols, true)) $conds[] = "p.has_inventory = 1";
        if (in_array('active',        $tp_cols, true)) $conds[] = "p.active = 1";
        if (in_array('is_active',     $tp_cols, true)) $conds[] = "p.is_active = 1";
        if (in_array('is_deleted',    $tp_cols, true)) $conds[] = "p.is_deleted = 0";
        if (in_array('deleted_at',    $tp_cols, true)) $conds[] = "(p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')";

        $s_id = $this->S->col($ts, 'supplier_id', false);
        $s_auto = $this->S->col($ts, 'automatic_transferring', false);
        $s_name = 'name';
        $b_id = $this->S->col($tb, 'brand_id', false);
        $b_auto = $this->S->col($tb, 'automatic_transferring', false);
        $b_name = 'name';

        $sql = "SELECT i.$pid AS product_id, i.$oh AS on_hand
                FROM $t i
                INNER JOIN $tp p ON i.$pid = p.$p_pid
                LEFT JOIN $ts s ON " . ($s_id ? "p.$p_sid = s.$s_id" : "1=1") . "
                LEFT JOIN $tb b ON " . ($b_id ? "p.$p_bid = b.$b_id" : "1=1") . "
                WHERE " . implode(' AND ', $conds);

        // Supplier/brand switches (allow NULL or enabled)
        if ($s_auto) $sql .= " AND (p.$p_sid IS NULL OR s.$s_auto IS NULL OR s.$s_auto != '0')";
        if ($b_auto) $sql .= " AND (p.$p_bid IS NULL OR b.$b_auto IS NULL OR b.$b_auto != '0')";

        // Drink-word filters (safe even if names don't exist: our joins tolerate)
        $sql .= " AND (s.$s_name IS NULL OR (s.$s_name NOT LIKE '%coca%' AND s.$s_name NOT LIKE '%pepsi%' AND s.$s_name NOT LIKE '%monster%' AND s.$s_name NOT LIKE '%red bull%' AND s.$s_name NOT LIKE '%drink%' AND s.$s_name NOT LIKE '%beverage%' AND s.$s_name NOT LIKE '%water%' AND s.$s_name NOT LIKE '%juice%' AND s.$s_name NOT LIKE '%soda%'))";
        $sql .= " AND (b.$b_name IS NULL OR (b.$b_name NOT LIKE '%coca%' AND b.$b_name NOT LIKE '%pepsi%' AND b.$b_name NOT LIKE '%monster%' AND b.$b_name NOT LIKE '%red bull%' AND b.$b_name NOT LIKE '%drink%' AND b.$b_name NOT LIKE '%beverage%' AND b.$b_name NOT LIKE '%water%' AND b.$b_name NOT LIKE '%juice%' AND b.$b_name NOT LIKE '%soda%'))";
        $sql .= " AND (p.$p_name NOT LIKE '%coca%' AND p.$p_name NOT LIKE '%pepsi%' AND p.$p_name NOT LIKE '%monster%' AND p.$p_name NOT LIKE '%red bull%' AND p.$p_name NOT LIKE '%drink%' AND p.$p_name NOT LIKE '%beverage%' AND p.$p_name NOT LIKE '%water%' AND p.$p_name NOT LIKE '%juice%' AND p.$p_name NOT LIKE '%soda%' AND p.$p_name NOT LIKE '%energy drink%')";

        if ($limit > 0) $sql .= " LIMIT {$limit}";
        error_log("candidatesFromHub SQL: $sql");
        $res = $this->q($sql, [$hub_outlet_id], 's');

        $out = [];
        while ($r = $res->fetch_assoc()) $out[(string)$r['product_id']] = (int)$r['on_hand'];
        error_log("candidatesFromHub: returned " . count($out) . " products");
        return $out;
    }

    public function productInfoBulk(array $product_ids): array
    {
        if (!$product_ids) return [];
        $t = 'vend_products';
        $pid = $this->S->col($t, 'product_id');
        $nm = $this->S->col($t, 'name');
        $sku = $this->S->col($t, 'sku');
        $pr = $this->S->col($t, 'price');
        $co = $this->S->col($t, 'cost');
        $wt = $this->S->col($t, 'avg_weight_grams');
        $pt = $this->S->col($t, 'product_type_code');
        $sid = $this->S->col($t, 'supplier_id');
        $bid = $this->S->col($t, 'brand_id');
        $in = implode(',', array_fill(0, count($product_ids), '?'));
        $sql = "SELECT $pid AS product_id,$nm AS name,$sku AS sku,$pr AS price,$co AS cost,$wt AS avg_weight_grams,$pt AS product_type_code,$sid AS supplier_id,$bid AS brand_id FROM $t WHERE $pid IN ($in)";
        $res = $this->q($sql, array_values($product_ids), str_repeat('s', count($product_ids)));
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $r['price'] = (float)$r['price'];
            $r['cost'] = (float)($r['cost'] ?? 0);
            $r['avg_weight_grams'] = (float)($r['avg_weight_grams'] ?? 0);
            $out[(string)$r['product_id']] = $r;
        }
        return $out;
    }

    public function demandBulk(array $product_ids, array $outlet_ids): array
    {
        if (!$product_ids || !$outlet_ids) return [];
        $since = (new DateTime('-90 days'))->format('Y-m-d 00:00:00');

        try {
            $check = $this->q("SHOW TABLES LIKE 'sales_summary_90d'");
            if ($check->num_rows > 0) {
                $t = 'sales_summary_90d';
                $pid = $this->S->col($t, 'product_id');
                $oid = $this->S->col($t, 'outlet_id');
                $u90 = $this->S->col($t, 'units_sold_90d');
                $ls = $this->S->col($t, 'last_sold_at');
                $inP = implode(',', array_fill(0, count($product_ids), '?'));
                $inO = implode(',', array_fill(0, count($outlet_ids), '?'));
                $sql = "SELECT $pid AS product_id,$oid AS outlet_id,$u90 AS units_sold_90d,$ls AS last_sold_at FROM $t WHERE $pid IN ($inP) AND $oid IN ($inO)";
                $res = $this->q($sql, array_merge($product_ids, $outlet_ids), str_repeat('s', count($product_ids) + count($outlet_ids)));
                $out = [];
                while ($r = $res->fetch_assoc()) {
                    $p = (string)$r['product_id'];
                    $o = (string)$r['outlet_id'];
                    $out[$p][$o] = ['units_sold_90d' => (float)$r['units_sold_90d'], 'last_sold_at' => $r['last_sold_at']];
                }
                return $out;
            }
        } catch (Throwable $e) {
        }

        // Fallback: vend_sales_line_items (+ optional join vend_sales)
        $li = 'vend_sales_line_items';
        $li_cols = $this->S->listColumns($li);
        $li_pid = $this->S->col($li, 'product_id');
        $li_oid = in_array('outlet_id', $li_cols, true) ? $this->S->col($li, 'outlet_id') : null;
        $li_qty = $this->S->col($li, 'quantity');
        $li_sold = in_array('sold_at', $li_cols, true) ? $this->S->col($li, 'sold_at') : (in_array('created_at', $li_cols, true) ? $this->S->col($li, 'created_at') : null);
        $li_saleid = in_array('sale_id', $li_cols, true) ? $this->S->col($li, 'sale_id') : null;

        $have_sales = false;
        $s = 'vend_sales';
        try {
            $chkS = $this->q("SHOW TABLES LIKE '$s'");
            $have_sales = $chkS->num_rows > 0;
        } catch (Throwable $e) {
        }
        $s_cols = $have_sales ? $this->S->listColumns($s) : [];
        $s_oid = $have_sales ? (in_array('outlet_id', $s_cols, true) ? $this->S->col($s, 'outlet_id') : null) : null;
        $s_sold = $have_sales ? (in_array('sold_at', $s_cols, true) ? $this->S->col($s, 'sold_at') : (in_array('created_at', $s_cols, true) ? $this->S->col($s, 'created_at') : null)) : null;
        $s_id  = $have_sales ? (in_array('sale_id', $s_cols, true) ? $this->S->col($s, 'sale_id') : (in_array('id', $s_cols, true) ? $this->S->col($s, 'id') : null)) : null;

        $use_join = $have_sales && ($li_oid === null || $li_sold === null);
        $dateExpr  = $use_join ? "COALESCE(li.$li_sold, s.$s_sold)" : "li.$li_sold";
        $outletExpr = $use_join ? "COALESCE(li.$li_oid, s.$s_oid)"   : "li.$li_oid";

        $inP = implode(',', array_fill(0, count($product_ids), '?'));
        $inO = implode(',', array_fill(0, count($outlet_ids), '?'));
        $params = array_merge([$since], $product_ids, $outlet_ids);
        $types  = 's' . str_repeat('s', count($product_ids) + count($outlet_ids));
        $where  = "$dateExpr >= ? AND li.$li_pid IN ($inP)";
        if ($outletExpr !== null) $where .= " AND $outletExpr IN ($inO)";

        if ($use_join && $s_id) {
            $joinOn = $li_saleid ? "li.$li_saleid = s.$s_id" : "1=1";
            $sql = "SELECT li.$li_pid AS product_id, $outletExpr AS outlet_id,
                           SUM(CASE WHEN li.$li_qty < 0 THEN -li.$li_qty ELSE li.$li_qty END) AS units_sold_90d,
                           MAX($dateExpr) AS last_sold_at
                    FROM $li li LEFT JOIN $s s ON $joinOn
                    WHERE $where
                    GROUP BY li.$li_pid, $outletExpr";
        } else {
            if ($li_oid === null || $li_sold === null) return [];
            $sql = "SELECT li.$li_pid AS product_id, li.$li_oid AS outlet_id,
                           SUM(CASE WHEN li.$li_qty < 0 THEN -li.$li_qty ELSE li.$li_qty END) AS units_sold_90d,
                           MAX(li.$li_sold) AS last_sold_at
                    FROM $li li
                    WHERE $where
                    GROUP BY li.$li_pid, li.$li_oid";
        }

        $res = $this->q($sql, $params, $types);
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $p = (string)$r['product_id'];
            $o = (string)$r['outlet_id'];
            if ($o === '') continue;
            $out[$p][$o] = ['units_sold_90d' => (float)$r['units_sold_90d'], 'last_sold_at' => $r['last_sold_at']];
        }
        return $out;
    }

    public function inventoryFor(array $product_ids, array $outlet_ids): array
    {
        if (!$product_ids || !$outlet_ids) return [];
        $t = 'vend_inventory';
        $pid = $this->S->col($t, 'product_id');
        $oid = $this->S->col($t, 'outlet_id');
        $oh = $this->S->col($t, 'on_hand');
        $inP = implode(',', array_fill(0, count($product_ids), '?'));
        $inO = implode(',', array_fill(0, count($outlet_ids), '?'));
        $sql = "SELECT $pid AS product_id,$oid AS outlet_id,$oh AS on_hand FROM $t WHERE $pid IN ($inP) AND $oid IN ($inO)";
        $res = $this->q($sql, array_merge(array_values($product_ids), array_values($outlet_ids)), str_repeat('s', count($product_ids) + count($outlet_ids)));
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(string)$r['product_id']][(string)$r['outlet_id']] = (int)$r['on_hand'];
        return $out;
    }

    public function productTypeDefaults(): array
    {
        $t = 'product_types';
        try {
            $this->q("SHOW COLUMNS FROM $t");
        } catch (Throwable $e) {
            return [];
        }
        $pt = $this->S->col($t, 'product_type_code');
        $sq = $this->S->col($t, 'default_seed_qty');
        $aw = $this->S->col($t, 'avg_weight_grams_default');
        $res = $this->q("SELECT $pt AS type_code,$sq AS default_seed_qty,$aw AS avg_weight_grams_default FROM $t");
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(string)$r['type_code']] = ['default_seed_qty' => (int)($r['default_seed_qty'] ?? 0), 'avg_weight_grams_default' => (float)($r['avg_weight_grams_default'] ?? 0)];
        return $out;
    }

    public function classificationFor(array $product_ids): array
    {
        if (!$product_ids) return [];
        $t = 'product_classification_unified';
        $pid = $this->S->col($t, 'product_id');
        $tc = $this->S->col($t, 'type_code');
        $cc = $this->S->col($t, 'category_code');
        $cf = $this->S->col($t, 'confidence');
        $in = implode(',', array_fill(0, count($product_ids), '?'));
        $sql = "SELECT $pid AS product_id,$tc AS type_code,$cc AS category_code,$cf AS confidence FROM $t WHERE $pid IN ($in)";
        $res = $this->q($sql, array_values($product_ids), str_repeat('s', count($product_ids)));
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(string)$r['product_id']] = ['type_code' => $r['type_code'], 'category_code' => $r['category_code'], 'confidence' => (float)($r['confidence'] ?? 0)];
        return $out;
    }

    public function hasSalesSince(string $outlet_id, string $since): bool
    {
        try {
            $chk = $this->q("SHOW TABLES LIKE 'sales_summary_90d'");
            if ($chk->num_rows > 0) {
                $t = 'sales_summary_90d';
                $oid = $this->S->col($t, 'outlet_id');
                $res = $this->q("SELECT 1 FROM $t WHERE $oid = ? LIMIT 1", [$outlet_id], 's');
                return (bool)$res->num_rows;
            }
        } catch (Throwable $e) {
        }
        try {
            $li = 'vend_sales_line_items';
            $li_cols = $this->S->listColumns($li);
            $li_oid = in_array('outlet_id', $li_cols, true) ? $this->S->col($li, 'outlet_id') : null;
            $li_sold = in_array('sold_at', $li_cols, true) ? $this->S->col($li, 'sold_at') : (in_array('created_at', $li_cols, true) ? $this->S->col($li, 'created_at') : null);
            if (!$li_sold && !$li_oid) return false;
            $sql = "SELECT 1 FROM $li WHERE " . ($li_oid ? "$li_oid = ? AND " : "") . "$li_sold >= ? LIMIT 1";
            $params = $li_oid ? [$outlet_id, $since] : [$since];
            $types  = $li_oid ? 'ss' : 's';
            $res = $this->q($sql, $params, $types);
            return (bool)$res->num_rows;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isNewStoreCandidate(string $outlet_id, int $minInvRows = 20, int $noSalesDays = 90): bool
    {
        $invCount = $this->getStoreInventoryCount($outlet_id);
        if ($invCount > $minInvRows) return false;
        $since = (new DateTime("-{$noSalesDays} days"))->format('Y-m-d 00:00:00');
        return !$this->hasSalesSince($outlet_id, $since);
    }

    // Create header/line rows (match your table names/columns)
    public function createTransferHeader(array $h): int
    {
        $t = 'stock_transfers';
        $sql = "INSERT INTO $t (
            outlet_from,outlet_to,status,micro_status,
            transfer_created_by_user,source_module,delivery_mode,
            automation_triggered,run_id,created_by_system,
            product_count,total_quantity,date_created
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
        $params = [
            (string)$h['source_outlet_id'],
            (string)$h['dest_outlet_id'],
            (int)($h['status'] ?? 1),
            (string)($h['micro_status'] ?? 'READY_FOR_PACKING'),
            (int)($h['transfer_created_by_user'] ?? 1),
            (string)($h['source_module'] ?? 'transfer_engine'),
            (string)($h['delivery_mode'] ?? 'standard'),
            (int)($h['automation_triggered'] ?? 1),
            (string)$h['run_id'],
            (string)($h['created_by_system'] ?? 'automatic_stock_transfers_v4'),
            (int)($h['product_count'] ?? 0),
            (int)($h['total_quantity'] ?? 0),
        ];
        $this->exec($sql, $params); // default to 's' types is fine
        return $this->insertId();
    }

    public function createTransferLine(int $transfer_id, array $line): int
    {
        $t = 'stock_products_to_transfer';
        $sql = "INSERT INTO $t (
            transfer_id,product_id,qty_to_transfer,optimal_qty,
            demand_forecast,sales_velocity,min_qty_to_remain
        ) VALUES (?,?,?,?,?,?,?)";
        $params = [
            $transfer_id,
            (string)$line['product_id'],
            (int)$line['qty'],
            (int)($line['optimal_qty'] ?? $line['qty']),
            (int)($line['demand_forecast'] ?? 0),
            (float)($line['sales_velocity'] ?? 0.0),
            0
        ];
        $this->exec($sql, $params);
        return $this->insertId();
    }

    public function begin(): void
    {
        $this->con->begin_transaction();
    }
    public function commit(): void
    {
        $this->con->commit();
    }
    public function rollback(): void
    {
        $this->con->rollback();
    }
}

// ----------------------
// Neural/Events helper
// ----------------------
function log_transfer_event(string $event_type, array $event_data, string $severity = 'info', string $summary = '', string $run_id = '', array $details = []): bool
{
    global $con;
    if (empty($run_id)) $run_id = 'transfer_' . date('YmdHis') . '_' . substr(uniqid('', true), -6);
    $event_data_json = json_encode($event_data, JSON_UNESCAPED_SLASHES);
    $details_json = $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : '{}';
    $sql = "INSERT INTO system_event_log
            (event_type,event_data,source_module,actor_type,actor_id,target_type,target_id,summary,details_json,severity,created_at)
            VALUES ( ?, ?, 'transfer_engine','system','transfer_cron','transfer_run', ?, ?, ?, ?, NOW() )";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        error_log("system_event_log prepare failed: " . $con->error);
        return false;
    }
    $stmt->bind_param('ssssss', $event_type, $event_data_json, $run_id, $summary, $details_json, $severity);
    $ok = $stmt->execute();
    if (!$ok) error_log("system_event_log exec failed: " . $stmt->error);
    // Neural Brain mirror for important signals
    try {
        $nb = get_neural_brain();
        if ($nb && $nb->isEnabled()) {
            if ($severity === 'error' || $severity === 'critical' || str_contains($event_type, 'error')) {
                $nb->storeError($summary ?: $event_type, $details['solution'] ?? '', "Event: $event_type | Severity: $severity | Run: $run_id");
            } else if (str_contains($event_type, 'complete') || str_contains($event_type, 'success')) {
                $nb->storeSolution("Transfer Event: $event_type", "Summary: $summary\nEvent Data: " . json_encode($event_data, JSON_PRETTY_PRINT), ['transfer', 'event', $severity], 0.75);
            }
        }
    } catch (Throwable $e) {
        error_log("Neural mirror failed: " . $e->getMessage());
    }
    return $ok;
}

// ----------------------
// Dashboard (optional)
// ----------------------
function show_dashboard(mysqli $con, SchemaResolver $SCHEMA): void
{
    $outlets = [];
    $dbg = ['query' => '', 'error' => '', 'count' => 0, 'sample' => []];
    try {
        $t = 'vend_outlets';
        $id = $SCHEMA->col($t, 'outlet_id');
        $nm = $SCHEMA->col($t, 'name');
        $wh = $SCHEMA->col($t, 'is_warehouse');
        $st = $SCHEMA->col($t, 'status');
        $sql = "SELECT $id AS outlet_id,$nm AS name,$wh AS is_warehouse,$st AS status,deleted_at FROM $t
                WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' OR deleted_at = '') AND $st = 1 ORDER BY $nm";
        $dbg['query'] = $sql;
        if ($res = $con->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $outlets[] = ['outlet_id' => $row['outlet_id'], 'name' => $row['name'], 'is_warehouse' => (bool)$row['is_warehouse'], 'status' => (int)$row['status']];
                if (count($dbg['sample']) < 5) $dbg['sample'][] = $row;
            }
            $dbg['count'] = count($outlets);
            $dbg['error'] = 'OK';
        } else {
            $dbg['error'] = $con->error;
        }
    } catch (Throwable $e) {
        $dbg['error'] = $e->getMessage();
    }

    if (isset($_GET['debug_outlets'])) {
        echo_html("<pre>=== OUTLET DEBUG ===\nQuery: {$dbg['query']}\nError: {$dbg['error']}\nCount: {$dbg['count']}\nSample: " . json_encode($dbg['sample'], JSON_PRETTY_PRINT) . "\n=== END ===</pre>");
        exit;
    }
    include __DIR__ . '/main.php';
}

// ----------------------
// Freight & Profitability
// ----------------------
final class FreightEngine
{
    private array $rules;
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }
    public function pick(int $total_weight_grams): array
    {
        foreach ($this->rules as $r) if ($total_weight_grams <= (int)$r['max_weight_grams']) return ['container' => $r['container'], 'cost' => (float)$r['cost']];
        $last = end($this->rules) ?: ['container' => 'UNSET', 'cost' => 0.0];
        return ['container' => $last['container'], 'cost' => (float)$last['cost']];
    }
}
final class ProfitGuard
{
    private float $margin_factor;
    public function __construct(float $margin_factor)
    {
        $this->margin_factor = $margin_factor;
    }
    public function isProfitable(float $transfer_margin, float $freight_cost): bool
    {
        return $transfer_margin >= ($freight_cost * $this->margin_factor);
    }
}

// ----------------------
// Engine
// ----------------------
final class Engine
{
    private DAL $DAL;
    private SchemaResolver $S;
    private DecisionLedger $LEDGER;
    private Logger $LOG;
    private int $cover_days;
    private int $buffer_pct;
    private string $rounding_mode;
    private int $min_units_per_line;
    private float $floor_sales_thr;
    private int $default_floor_qty;
    private float $turnover_min_mult;
    private float $turnover_max_mult;
    private int $overflow_days;
    private float $overflow_mult;
    private ?string $store_filter;
    private ?string $new_store_id;
    private string $transfer_type;
    private ?string $source_outlet;
    private ?string $dest_outlet;
    private ?array $store_filter_list;
    private bool $exclude_warehouses;
    private bool $warehouse_only;

    private array $outlets = [];
    private ?string $hub_id = null;
    private array $freight_rules = [];
    private array $ptype_defaults = [];
    private array $category_weights = [];
    private array $product_debug_audit = [];

    public function __construct(DAL $DAL, SchemaResolver $S, DecisionLedger $LEDGER, Logger $LOG, array $params)
    {
        $this->DAL = $DAL;
        $this->S = $S;
        $this->LEDGER = $LEDGER;
        $this->LOG = $LOG;
        $this->cover_days = $params['cover_days'];
        $this->buffer_pct = $params['buffer_pct'];
        $this->rounding_mode = $params['rounding_mode'];
        $this->min_units_per_line = $params['min_units_per_line'];
        $this->floor_sales_thr = $params['floor_sales_threshold'];
        $this->default_floor_qty = $params['default_floor_qty'];
        $this->turnover_min_mult = $params['turnover_min_mult'];
        $this->turnover_max_mult = $params['turnover_max_mult'];
        $this->overflow_days = $params['overflow_days'];
        $this->overflow_mult = $params['overflow_mult'];
        $this->store_filter = $params['store_filter'];
        $this->store_filter_list = $params['store_filter_list'] ?? null;
        $this->new_store_id = $params['new_store_id'];
        $this->transfer_type = $params['transfer_mode'] ?? 'all_stores';
        $this->source_outlet = $params['source_outlet'] ?? null;
        $this->dest_outlet = $params['dest_outlet'] ?? null;
        $this->exclude_warehouses = (bool)($params['exclude_warehouses'] ?? true);
        $this->warehouse_only = (bool)($params['warehouse_only'] ?? false);
    }

    public function prime(): void
    {
        $this->outlets = $this->DAL->outlets();
        $warehouses = array_filter($this->outlets, fn($o) => !empty($o['is_warehouse']));
        if (in_array($this->transfer_type, ['specific', 'specific_transfer'], true) && $this->source_outlet) {
            $this->hub_id = (string)$this->source_outlet;
        } else {
            if (count($warehouses) > 1) {
                uasort($warehouses, fn($a, $b) => strcmp((string)$a['outlet_id'], (string)$b['outlet_id']));
                $first = reset($warehouses);
                $this->hub_id = (string)$first['outlet_id'];
            } elseif (count($warehouses) === 1) {
                $first = reset($warehouses);
                $this->hub_id = (string)$first['outlet_id'];
            }
        }
        if (!$this->hub_id) throw new RuntimeException("No warehouse/source outlet found among " . count($this->outlets) . " outlets.");
        $this->freight_rules    = $this->DAL->freightRules();
        $this->ptype_defaults   = $this->DAL->productTypeDefaults();
        $this->category_weights = $this->DAL->categoryWeights();
        if (!$this->freight_rules) throw new RuntimeException("No active freight_rules found");
    }

    private function calculateDynamicSafetyCap(array $product): int
    {
        $cost = (float)($product['cost'] ?? 0);
        $price = (float)($product['price'] ?? 0);
        if ($cost >= 30.0 || $price >= 80.0) return 3;
        if ($cost >= 15.0 || $price >= 50.0) return 8;
        if ($cost >= 5.0  || $price >= 20.0) return 12;
        return 16;
    }
    private function capSafeTakeForHighHubStock(int $hub_stock, int $safe_take): int
    {
        if ($hub_stock >= 2000) return min($safe_take, 60);
        elseif ($hub_stock >= 1000) return min($safe_take, 80);
        elseif ($hub_stock >= 500)  return min($safe_take, 120);
        return $safe_take;
    }

    /** Detect pack size and round qty accordingly */
    private function applyPackRounding(string $pid, int $qty, array $prod_info): int
    {
        $name = strtolower($prod_info[$pid]['name'] ?? '');
        $sku  = strtolower($prod_info[$pid]['sku'] ?? '');
        $pack = 1;

        // Detect pack sizes
        if (preg_match('/(5\s?pack|5pk)/', $name . $sku)) $pack = 5;
        elseif (preg_match('/(4\s?pack|4pk)/', $name . $sku)) $pack = 4;
        elseif (preg_match('/(3\s?pack|3pk)/', $name . $sku)) $pack = 3;
        elseif (preg_match('/(2\s?pack|2pk)/', $name . $sku)) $pack = 2;

        // If pack >1 → round up to nearest pack
        if ($pack > 1) {
            $rounded = (int)(ceil($qty / $pack) * $pack);
            return $rounded;
        }
        return $qty;
    }


    private function companyMonthsOfSupply(string $pid, array $inv, array $demand): float
    {
        $on_hand_total = 0;
        if (isset($inv[$pid])) foreach ($inv[$pid] as $qty) $on_hand_total += (int)$qty;
        $u90_total = 0.0;
        if (isset($demand[$pid])) foreach ($demand[$pid] as $row) $u90_total += (float)($row['units_sold_90d'] ?? 0);
        $daily = $u90_total / 90.0;
        if ($daily <= 0.0001) return $on_hand_total > 0 ? 999.0 : 0.0;
        return max(0.0, $on_hand_total / ($daily * 30.0));
    }
    private function oversupplyThrottle(string $pid, array $inv, array $demand, float $target_mos = 3.0): float
    {
        $mos = $this->companyMonthsOfSupply($pid, $inv, $demand);
        if ($mos <= $target_mos) return 1.0;
        if ($mos <= 6.0)  return 0.70;
        if ($mos <= 9.0)  return 0.50;
        if ($mos <= 12.0) return 0.35;
        return 0.20;
    }
    private function shouldShipLine(array $pi, int $q, float $min_line_value, float $min_unit_price): bool
    {
        $price = (float)($pi['price'] ?? 0.0);
        $line_value = $price * max(0, $q);
        if ($price < $min_unit_price && $line_value < $min_line_value) return false;
        if ($line_value < ($min_line_value * 0.5)) return false;
        return $q > 0;
    }
    private function ensureWeights(string $pid, array &$pi, array $pc): void
    {
        if (($pi['avg_weight_grams'] ?? 0) > 0) return;
        $cat = $pc['category_code'] ?? '';
        if ($cat && ($this->category_weights[$cat] ?? 0) > 0) {
            $pi['avg_weight_grams'] = (float)$this->category_weights[$cat];
            return;
        }
        $pt = $pi['product_type_code'] ?? '';
        if ($pt && (($this->ptype_defaults[$pt]['avg_weight_grams_default'] ?? 0) > 0)) {
            $pi['avg_weight_grams'] = (float)$this->ptype_defaults[$pt]['avg_weight_grams_default'];
            return;
        }
        $pi['avg_weight_grams'] = 200.0;
        $this->LEDGER->push($pid, 'ALL', 'WEIGHT', 'FALLBACK_200G', []);
    }
    private function applyRounding(int|float $q): int
    {
        return match ($this->rounding_mode) {
            'up' => (int)ceil($q),
            'down' => (int)floor($q),
            default => (int)round($q),
        };
    }


    // ----------------------
    // Smart New Store Seeding
    // ----------------------

    // ----------------------
    // Smart New Store Seeding
    // ----------------------

    /**
     * Multi-donor seeding: use same seed qty logic, but take from donor with most stock
     */
    private function seedNewStoreFromAllStores(
        string $new_store_id,
        array $inv,
        array $prod_info,
        array $classif,
        array &$allocations
    ): void {
        foreach ($prod_info as $pid => $pi) {
            // Find donor with the most stock
            $donor = null;
            $max_qty = 0;
            foreach ($this->outlets as $oid => $o) {
                if ($oid === $new_store_id) continue;
                $qty = (int)($inv[$pid][$oid] ?? 0);
                if ($qty > $max_qty) {
                    $max_qty = $qty;
                    $donor = $oid;
                }
            }
            if (!$donor || $max_qty <= 0) continue;

            // Calculate seed qty (same defaults/bonuses as hub mode)
            $cat = strtolower((string)($classif[$pid]['category_code'] ?? ''));
            $typ = strtolower((string)($pi['product_type_code'] ?? ''));

            $q = (int)get_cli_or_get('seed_qty_default', 2);
            if (str_contains($cat, 'eliquid') || str_contains($typ, 'eliquid')) {
                $q += (int)get_cli_or_get('seed_eliquid_bonus', 3);
            }
            if (str_contains($cat, 'coil') || str_contains($typ, 'coil')) {
                $q += (int)get_cli_or_get('seed_coils_bonus', 2);
            }
            if (str_contains($cat, 'device') || str_contains($typ, 'device')) {
                $q += (int)get_cli_or_get('seed_device_bonus', 1);
            }

            // Cap to donor’s stock and a max (e.g. 10)
            $q = min($q, 10, $max_qty);

            if ($q > 0) {
                $allocations[$new_store_id][] = [
                    'product_id' => $pid,
                    'qty'        => $q,
                    'optimal_qty' => $q,
                    'donor'      => $donor, // <- add donor id
                ];
                $this->LEDGER->push(
                    $pid,
                    $new_store_id,
                    'SEED_MULTI_DONOR',
                    'NEW_STORE_SEED',
                    ['donor' => $donor, 'qty' => $q, 'donor_stock' => $max_qty]
                );
            }
        }
    }


    /** Demand-aware seeding for new stores */
    private function seedNewStore(
        string $dest_id,
        array $inv,
        array $prod_info,
        array $classif,
        array $companyDemand,
        array &$allocations,
        array $opts = []
    ): void {
        $MAX_LINES        = (int)(get_cli_or_get('seed_max_lines', $opts['max_lines'] ?? 120));
        $SEED_QTY_DEFAULT = (int)(get_cli_or_get('seed_qty_default', $opts['seed_qty_default'] ?? 3));
        $COILS_BONUS_QTY  = (int)(get_cli_or_get('seed_coils_bonus', 2));
        $ELIQUID_BONUS_QTY = (int)(get_cli_or_get('seed_eliquid_bonus', 3));
        $DEVICE_BONUS_QTY  = (int)(get_cli_or_get('seed_device_bonus', 1));
        $PRIORITY_LIMIT    = (int)(get_cli_or_get('seed_priority_limit', 500)); // scan top N by company demand

        // Helper callables for hub availability / consumption (passed via $opts from run())
        /** @var callable|null $availableFn */
        $availableFn = $opts['available'] ?? null;
        /** @var callable|null $consumeFn */
        $consumeFn   = $opts['consume']   ?? null;
        $hub_id      = (string)($opts['hub_id'] ?? $this->hub_id);

        // Build a priority list of product IDs by company-wide demand (last 90d)
        $prior = [];
        foreach ($companyDemand as $pid => $perOutlet) {
            $sum = 0.0;
            foreach ($perOutlet as $o => $row) $sum += (float)($row['units_sold_90d'] ?? 0);
            if ($sum > 0) $prior[$pid] = $sum;
        }
        arsort($prior, SORT_NUMERIC);
        if ($PRIORITY_LIMIT > 0) $prior = array_slice($prior, 0, $PRIORITY_LIMIT, true);

        // Category/type heuristics
        $count = 0;
        foreach ($prior as $pid => $_score) {
            if (!isset($prod_info[$pid])) continue;
            $pi = $prod_info[$pid];
            $pc = $classif[$pid] ?? ['type_code' => '', 'category_code' => '', 'confidence' => 0];

            // Hub stock / availability
            $hub_stock = (int)($inv[$pid][$hub_id] ?? 0);
            if ($hub_stock <= 0) continue;

            $available = $availableFn ? (int)$availableFn($pid, $hub_stock) : max(0, $hub_stock - (int)ceil($hub_stock * ($this->buffer_pct / 100.0)));
            if ($available <= 0) continue;

            // Choose seed quantity by category/type
            $cat = strtolower((string)($pc['category_code'] ?? ''));
            $typ = strtolower((string)($pi['product_type_code'] ?? ($pc['type_code'] ?? '')));

            $q = $SEED_QTY_DEFAULT;
            if (str_contains($cat, 'eliquid') || str_contains($typ, 'eliquid')) $q += $ELIQUID_BONUS_QTY;
            if (str_contains($cat, 'coil')    || str_contains($typ, 'coil'))    $q += $COILS_BONUS_QTY;
            if (str_contains($cat, 'device')  || str_contains($typ, 'device'))  $q += $DEVICE_BONUS_QTY;

            // Respect rounding/mins/caps
            $q = max($this->min_units_per_line, $this->applyRounding($q));
            $q = min($q, $available);
            $q = min($q, $this->calculateDynamicSafetyCap($pi));

            if ($q <= 0) continue;

            // Record allocation
            $allocations[$dest_id][] = [
                'product_id'   => $pid,
                'qty'          => $q,
                'optimal_qty'  => $q,
                'sales_velocity' => 0.0,
                'demand_forecast' => 0,
            ];
            $count++;

            // Consume hub availability (shared with main allocator)
            if ($consumeFn) $consumeFn($pid, $q);

            $this->LEDGER->push($pid, $dest_id, 'SEED', 'NEW_STORE_SEED', [
                'qty' => $q,
                'category' => $cat,
                'type' => $typ,
                'hub_stock' => $hub_stock,
                'available' => $available
            ]);

            if ($count >= $MAX_LINES) break;
        }

        if ($count === 0) {
            $this->LEDGER->push('ALL', $dest_id, 'SEED', 'NO_SEED_LINES', []);
        }
    }

    public function run(bool $apply, string $run_id, array $opts = []): array
{
    // =========================================================
    // [RUN-1] Resolve destination outlets
    // =========================================================
    $dest_ids = [];
    if (in_array($this->transfer_type, ['specific','specific_transfer'], true) && $this->dest_outlet) {
        $dest_ids = [(string)$this->dest_outlet];
    } else {
        foreach ($this->outlets as $oid => $o) {
            if ($this->exclude_warehouses && !empty($o['is_warehouse'])) continue;
            if ($this->warehouse_only && empty($o['is_warehouse'])) continue;
            if ($this->store_filter && stripos($o['name'], $this->store_filter) === false) continue;
            if ($this->store_filter_list && !in_array((string)$oid, $this->store_filter_list, true)) continue;
            $dest_ids[] = (string)$oid;
        }
    }
    $dest_ids = array_values(array_unique($dest_ids));
    if (!$dest_ids) throw new RuntimeException("No destination stores selected.");
    if (!$this->hub_id) throw new RuntimeException("Source (hub) outlet not set.");

    // =========================================================
    // [RUN-2] Candidate products (we still use hub list to bound the SKU set)
    // =========================================================
    $hubCandidates = $this->DAL->candidatesFromHub($this->hub_id);
    if (!$hubCandidates) {
        return ['ok'=>true,'run_id'=>$run_id,'transfers'=>[],'message'=>'No hub candidates found'];
    }
    $product_ids = array_keys($hubCandidates);

    // =========================================================
    // [RUN-3] Product meta & classification
    // =========================================================
    $prod_info = $this->DAL->productInfoBulk($product_ids);
    $classif   = $this->DAL->classificationFor($product_ids);

    // =========================================================
    // [RUN-4] Snapshots (CRITICAL: all outlets inv when seed_mode=multi)
    // =========================================================
    $seed_mode = (string)get_cli_or_get('seed_mode', 'hub'); // 'multi' or 'hub'

    // >>> REFERENCE INV-A: Inventory set for multi-donor seeding
    if ($seed_mode === 'multi') {
        // 👇 include ALL outlets so multi-donor can see everyone
        $inv = $this->DAL->inventoryFor($product_ids, array_keys($this->outlets));
    } else {
        $inv = $this->DAL->inventoryFor($product_ids, array_merge($dest_ids, [$this->hub_id]));
    }

    $demand_dest    = $this->DAL->demandBulk($product_ids, $dest_ids);
    $demand_company = $this->DAL->demandBulk($product_ids, array_keys($this->outlets));

    // =========================================================
    // [RUN-5] Freight & profit guards
    // =========================================================
    $freight = new FreightEngine($this->freight_rules);
    $profitF = new ProfitGuard((float)($opts['profit_factor'] ?? (float)get_cli_or_get('profit_factor', 1.0)));

    // hub availability tracker (for hub flows only)
    $hub_taken = [];
    $availableFn = function (string $pid, int $hub_stock) use (&$hub_taken) {
        $retain = (int)ceil($hub_stock * (max(0, min(90, $this->buffer_pct)) / 100.0));
        $avail  = max(0, $hub_stock - $retain);
        $taken  = (int)($hub_taken[$pid] ?? 0);
        return max(0, $avail - $taken);
    };
    $consumeFn = function (string $pid, int $qty) use (&$hub_taken) {
        $hub_taken[$pid] = (int)($hub_taken[$pid] ?? 0) + max(0, (int)$qty);
    };

    // =========================================================
    // [RUN-6] Build allocations (seeding first)
    // =========================================================
    $allocations = [];          // dest_id => [lines...]
    $skip_fairshare_dest = [];  // mark dests that already got multi-donor seeding

    foreach ($dest_ids as $dest_id) {
        $isNew = $this->new_store_id ? ($this->new_store_id === $dest_id)
                                     : $this->DAL->isNewStoreCandidate($dest_id);
        if ($isNew) {
            $this->LEDGER->push('ALL',$dest_id,'SEED','NEW_STORE_DETECTED',[]);
            if ($seed_mode === 'multi') {
                // multi-donor: skim top stockholder per SKU across ALL outlets
                $this->seedNewStoreFromAllStores($dest_id, $inv, $prod_info, $classif, $allocations);
                $skip_fairshare_dest[$dest_id] = true;  // <<< REFERENCE SKIP-A: mark to skip hub fair-share later
            } else {
                // hub-based seeding (existing)
                $this->seedNewStore($dest_id, $inv, $prod_info, $classif, $demand_company, $allocations, [
                    'hub_id'    => $this->hub_id,
                    'available' => $availableFn,
                    'consume'   => $consumeFn
                ]);
            }
        }
    }

    // =========================================================
    // [RUN-7] Hub fair-share (normal replenishment) — skip multi-donor dests
    // =========================================================
    $min_line_value = (float)get_cli_or_get('min_line_value', 12.0);
    $min_unit_price = (float)get_cli_or_get('min_unit_price', 2.00);

    foreach ($product_ids as $pid) {
        $hub_stock = (int)($inv[$pid][$this->hub_id] ?? $hubCandidates[$pid] ?? 0);
        if ($hub_stock <= 0) continue;

        $available = (int)$availableFn($pid, $hub_stock);
        if ($available <= 0) continue;

        $pi = $prod_info[$pid] ?? ['price'=>0.0,'cost'=>0.0,'avg_weight_grams'=>0.0,'product_type_code'=>''];
        $pc = $classif[$pid]   ?? ['category_code'=>'','type_code'=>'','confidence'=>0.0];
        $this->ensureWeights($pid, $pi, $pc);

        $needs = [];
        $total_need = 0.0;

        foreach ($dest_ids as $dest) {
            // >>> REFERENCE SKIP-B: this is the exact line you asked about
            // If multi-donor seeding already populated this dest, skip hub fair-share
            if (!empty($skip_fairshare_dest[$dest])) continue;

            // compute “need” for this dest
            $already = 0;
            if (!empty($allocations[$dest])) {
                foreach ($allocations[$dest] as $ln) if ($ln['product_id'] === $pid) $already += (int)$ln['qty'];
            }

            $units90 = (float)($demand_dest[$pid][$dest]['units_sold_90d'] ?? 0.0);
            $daily   = $units90 / 90.0;
            if ($daily < $this->floor_sales_thr) continue;

            $mult = (float)($this->outlets[$dest]['turnover_multiplier'] ?? 1.0);
            $mult = max($this->turnover_min_mult, min($this->turnover_max_mult, $mult));
            $daily *= $mult;

            $target = $this->applyRounding($daily * max(1, $this->cover_days));
            $onhand = (int)($inv[$pid][$dest] ?? 0);
            $short  = max(0, $target - $onhand - $already);

            if ($short <= 0) continue;
            $short = (int)round($short * $this->oversupplyThrottle($pid, $inv, $demand_company, 3.0));
            if ($short <= 0) continue;

            $short = min($short, $this->calculateDynamicSafetyCap($pi));
            if ($short < $this->min_units_per_line) continue;

            $needs[$dest] = $short;
            $total_need  += $short;
        }

        if ($total_need <= 0) continue;

        // fair-share split
        $assigned   = [];
        $remainders = [];
        foreach ($needs as $dest => $need) {
            $raw = ($available * $need) / $total_need;
            $qty = $this->applyRounding($raw);
            if ($qty <= 0) { $remainders[$dest] = $raw; continue; }
            $qty = min($qty, $need);
            if ($qty < $this->min_units_per_line) { $remainders[$dest] = $raw; continue; }
            $assigned[$dest] = $qty;
        }

        $assigned_total = array_sum($assigned);
        $left = max(0, $available - $assigned_total);
        if ($left > 0 && $remainders) {
            arsort($remainders, SORT_NUMERIC);
            foreach ($remainders as $dest => $rem) {
                $add = min($left, max(0, $needs[$dest] - (int)($assigned[$dest] ?? 0)));
                if ($add >= $this->min_units_per_line) {
                    $assigned[$dest] = (int)($assigned[$dest] ?? 0) + $add;
                    $left -= $add;
                    if ($left <= 0) break;
                }
            }
        }

        $took = 0;
        foreach ($assigned as $dest => $q) {
            if (!$this->shouldShipLine($pi, $q, $min_line_value, $min_unit_price)) continue;
            $allocations[$dest][] = [
                'product_id'     => $pid,
                'qty'            => (int)$q,
                'optimal_qty'    => (int)$q,
                'sales_velocity' => (float)(($demand_dest[$pid][$dest]['units_sold_90d'] ?? 0) / 90.0),
                'demand_forecast'=> (int)$this->applyRounding(($demand_dest[$pid][$dest]['units_sold_90d'] ?? 0) / 90.0 * $this->cover_days),
                // no donor here → will default to hub later
            ];
            $this->LEDGER->push($pid, $dest, 'ALLOCATE','FAIR_SHARE',['qty'=>$q,'need'=>$needs[$dest],'available'=>$available]);
            $took += (int)$q;
        }
        if ($took > 0) $consumeFn($pid, $took);
    }

    // =========================================================
    // [RUN-8] Create transfers — ONE HEADER PER DONOR OUTLET
    // =========================================================
    $max_lines     = (int)get_cli_or_get('max_lines', 1000);
    $results       = ['ok'=>true, 'run_id'=>$run_id, 'apply'=>$apply, 'hub'=>$this->hub_id, 'transfers'=>[]];
    $created_count = 0;

    foreach ($dest_ids as $dest) {
        $lines = $allocations[$dest] ?? [];
        if (!$lines) continue;

        // merge duplicate SKUs
        $merged = [];
        foreach ($lines as $ln) {
            $p = $ln['product_id'];
            if (!isset($merged[$p])) $merged[$p] = $ln;
            else {
                $merged[$p]['qty']         += (int)$ln['qty'];
                $merged[$p]['optimal_qty']  = max((int)$merged[$p]['optimal_qty'], (int)$ln['optimal_qty']);
                if (!isset($merged[$p]['donor']) && isset($ln['donor'])) $merged[$p]['donor'] = $ln['donor'];
            }
        }
        $lines = array_values($merged);

        // sort by value, cap to max_lines
        usort($lines, function($a,$b) use($prod_info){
            $pa = $prod_info[$a['product_id']]['price'] ?? 0;
            $pb = $prod_info[$b['product_id']]['price'] ?? 0;
            return ($pb*$b['qty']) <=> ($pa*$a['qty']);
        });
        if (count($lines) > $max_lines) $lines = array_slice($lines, 0, $max_lines);

        // (optional) pack rounding if you have applyPackRounding()
        foreach ($lines as &$ln) {
            if (method_exists($this, 'applyPackRounding')) {
                $ln['qty'] = $this->applyPackRounding($ln['product_id'], (int)$ln['qty'], $prod_info);
            }
        } unset($ln);

        // >>> REFERENCE GROUP-A: group by donor → each donor becomes its own transfer header
        $groupedByDonor = [];
        foreach ($lines as $ln) {
            $donor = $ln['donor'] ?? $this->hub_id; // hub fallback for hub-fairshare lines
            $groupedByDonor[$donor][] = $ln;
        }

        foreach ($groupedByDonor as $donorId => $donorLines) {
            // totals for this donor group
            $total_qty    = 0;
            $total_weight = 0.0;
            $margin       = 0.0;
            foreach ($donorLines as $ln) {
                $pi  = $prod_info[$ln['product_id']] ?? ['price'=>0,'cost'=>0,'avg_weight_grams'=>0];
                $qty = (int)$ln['qty'];
                $total_qty    += $qty;
                $total_weight += (float)($pi['avg_weight_grams'] ?? 0) * $qty;
                $margin       += max(0.0, ((float)($pi['price'] ?? 0) - (float)($pi['cost'] ?? 0))) * $qty;
            }
            if ($total_qty <= 0) continue;

            $pick = $freight->pick((int)ceil($total_weight));

            // header: donor → dest
            $header = [
                'source_outlet_id'         => $donorId,  // 👈 THE donor outlet
                'dest_outlet_id'           => $dest,
                'status'                   => 0,
                'micro_status'             => 'READY_FOR_DELIVERY',
                'transfer_created_by_user' => (int)get_cli_or_get('created_by_user', 1),
                'source_module'            => 'transfer_engine',
                'delivery_mode'            => (string)$pick['container'],
                'automation_triggered'     => 1,
                'run_id'                   => $run_id,
                'created_by_system'        => 'automatic_stock_transfers_v4',
                'product_count'            => (int)count($donorLines),
                'total_quantity'           => (int)$total_qty,
            ];

            $created_id = 0;
            if ($apply) {
                $this->DAL->begin();
                try {
                    $created_id = $this->DAL->createTransferHeader($header);
                    foreach ($donorLines as $ln) $this->DAL->createTransferLine($created_id, $ln);
                    $this->DAL->commit();
                } catch (Throwable $e) {
                    $this->DAL->rollback();
                    $this->LEDGER->push('ALL',$dest,'TRANSFER','DB_ERROR',['error'=>$e->getMessage()]);
                    throw $e;
                }
            }

            $results['transfers'][] = [
                'transfer_id'   => $created_id,
                'source'        => $donorId,
                'destination'   => $dest,
                'delivery_mode' => $pick['container'],
                'freight_cost'  => (float)$pick['cost'],
                'margin'        => (float)$margin,
                'product_count' => (int)count($donorLines),
                'total_quantity'=> (int)$total_qty,
                'total_weight_g'=> (int)ceil($total_weight),
                'lines'         => $donorLines,
            ];
            $created_count++;
            $this->LEDGER->push('ALL',$dest,'TRANSFER',$apply?'CREATED':'SIMULATED',
                ['transfer_id'=>$created_id,'container'=>$pick['container']]);
        }
    }

    $results['created_count'] = $created_count;
    return $results;
    }
}

// ----------------------
// Param harness & entry
// ----------------------
try {
    $trace = as_int(get_cli_or_get('trace', 3), 3, 0, 9);
    $LOG   = new Logger($trace);
    $LEDGER = new DecisionLedger();
    $SCHEMA = new SchemaResolver($con);
    $DAL   = new DAL($con, $SCHEMA, $LOG);

    // Params (GET/CLI)
    $params = [
        'cover_days'           => as_int(get_cli_or_get('cover_days', 14), 14, 1, 90),
        'buffer_pct'           => as_int(get_cli_or_get('buffer_pct', 20), 20, 0, 90),
        'rounding_mode'        => (string)get_cli_or_get('rounding', 'nearest'),
        'min_units_per_line'   => as_int(get_cli_or_get('min_units', 2), 2, 1, 1000),
        'floor_sales_threshold' => as_float(get_cli_or_get('floor_sales_threshold', 0.05), 0.05, 0, 100),
        'default_floor_qty'    => as_int(get_cli_or_get('default_floor_qty', 1), 1, 0, 1000),
        'turnover_min_mult'    => as_float(get_cli_or_get('turnover_min_mult', 0.8), 0.8, 0.1, 5.0),
        'turnover_max_mult'    => as_float(get_cli_or_get('turnover_max_mult', 1.4), 1.4, 0.1, 10.0),
        'overflow_days'        => as_int(get_cli_or_get('overflow_days', 7), 7, 0, 60),
        'overflow_mult'        => as_float(get_cli_or_get('overflow_mult', 1.15), 1.15, 0.1, 10.0),

        'transfer_mode'        => (string)get_cli_or_get('transfer_mode', 'all_stores'),
        'source_outlet'        => (string)get_cli_or_get('source_outlet', ''),
        'dest_outlet'          => (string)get_cli_or_get('dest_outlet', ''),
        'store_filter'         => (string)get_cli_or_get('store_filter', ''),
        'store_filter_list'    => (function ($s) {
            $s = (string)$s;
            if ($s === '') return null;
            $a = array_filter(array_map('trim', explode(',', $s)));
            return $a ?: null;
        })(get_cli_or_get('store_filter_list', '')),
        'new_store_id'         => (string)get_cli_or_get('new_store_id', ''),

        'exclude_warehouses'   => boolish(get_cli_or_get('exclude_warehouses', '1'), true),
        'warehouse_only'       => boolish(get_cli_or_get('warehouse_only', '0'), false),
    ];

    $mode  = (string)get_cli_or_get('mode', '');
    $apply = boolish(get_cli_or_get('apply', $mode === 'apply' ? '1' : '0'), false);

    $run_id = (string)get_cli_or_get('run_id', 'run_' . date('Ymd_His'));

    $ENG = new Engine($DAL, $SCHEMA, $LEDGER, $LOG, $params);
    $ENG->prime();

    $opts = [
        'profit_factor' => (float)get_cli_or_get('profit_factor', 1.0),
    ];

    $results = $ENG->run($apply, $run_id, $opts);

    // ---------- JSON replay dumps ----------
    $base = __DIR__ . '/../../private_html/cis_transfer_runs';
    ensure_dir($base);
    $dir  = $base . '/' . $run_id;
    ensure_dir($dir);

    $replay = [
        'meta'     => ['run_id' => $run_id, 'ts' => date('c'), 'apply' => $apply, 'params' => $params],
        'results'  => $results,
        'ledger'   => $LEDGER->export(),
        'logs'     => $LOG->export(),
    ];

    // Write atomic
    @file_put_contents($dir . '/inputs.json',         jenc(['params' => $params]), LOCK_EX);
    @file_put_contents($dir . '/decisions.json',      jenc($LEDGER->export()), LOCK_EX);
    @file_put_contents($dir . '/logs.json',           jenc($LOG->export()), LOCK_EX);
    @file_put_contents($dir . '/outputs.json',        jenc($results), LOCK_EX);
    @file_put_contents($base . '/LAST_RUN_RESULTS.json', jenc($replay), LOCK_EX);
    // Visible emergency mirror
    @file_put_contents($base . '/EMERGENCY_LAST_RUN.json', jenc($replay));

    // Optional event log
    log_transfer_event('transfer_run_complete', ['run_id' => $run_id, 'apply' => $apply, 'created' => $results['created_count'] ?? 0], 'info', 'Transfer engine completed', $run_id, ['params' => $params]);

    // ---------- Response ----------
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo jenc($replay);
    } else {
        // Simple HTML summary
        echo_html('<div style="font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif; padding:16px">');
        echo_html('<h2 style="margin:0 0 8px">Enterprise Stock Transfer Engine — Pass 2</h2>');

        // Big caution banner (do not ship until confirmed)
        echo_html('<div style="background:#fff3cd;border:1px solid #ffe08a;padding:14px 16px;border-radius:10px;margin-bottom:16px;font-weight:600;font-size:14px">
            ⚠️ DO NOT SEND OR DISPATCH ANY TRANSFER UNTIL CONFIRMED. Ensure each box is clearly labelled with <u>TRANSFER NUMBER</u>, <u>STORE FROM</u>, and <u>STORE TO</u>.
        </div>');

        $created = (int)($results['created_count'] ?? 0);
        echo_html('<div style="margin-bottom:12px">Transfers ' . ($apply ? 'created' : 'proposed') . ': <b>' . $created . '</b></div>');
        echo_html('<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:12px">');

        foreach ($results['transfers'] as $t) {
            echo_html('<div style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;background:#fff">');
            echo_html('<div style="font-weight:700;margin-bottom:6px">From ' . h($t['source']) . ' → ' . h($t['destination']) . '</div>');
            echo_html('<div style="font-size:12px;color:#555;margin-bottom:6px">Container: ' . h($t['delivery_mode']) . ' • Weight: ' . (int)$t['total_weight_g'] . 'g • Qty: ' . $t['total_quantity'] . ' • Lines: ' . $t['product_count'] . '</div>');
            if (!empty($t['transfer_id'])) echo_html('<div style="font-size:12px;color:#111;margin-bottom:6px">Transfer ID: <b>' . (int)$t['transfer_id'] . '</b></div>');
            echo_html('<details><summary style="cursor:pointer;font-size:12px">Show lines</summary><div style="max-height:220px;overflow:auto;margin-top:6px">');
            echo_html('<table style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr><th style="text-align:left;border-bottom:1px solid #eee;padding:4px">Product</th><th style="text-align:right;border-bottom:1px solid #eee;padding:4px">Qty</th></tr></thead><tbody>');
            foreach ($t['lines'] as $ln) {
                $pid   = $ln['product_id'] ?? '';
                $pinfo = $prod_info[$pid] ?? [];

                // Always ensure $pname is a string
                if (!empty($pinfo['name'])) {
                    $pname = (string)$pinfo['name'];
                } elseif (!empty($pid)) {
                    $pname = (string)$pid;  // fallback to product_id
                } else {
                    $pname = 'Unknown Product';
                }

                $donor = $ln['donor'] ?? '';

                echo_html(
                    '<tr><td style="padding:4px 2px">'
                        . h($pname)
                        . ($donor ? '<br><small style="color:#666">from ' . h((string)$donor) . '</small>' : '')
                        . '</td><td style="text-align:right;padding:4px 2px">'
                        . (int)($ln['qty'] ?? 0)
                        . '</td></tr>'
                );
            }



            echo_html('</tbody></table></div></details>');
            echo_html('</div>');
        }
        echo_html('</div>');

        echo_html('<div style="margin-top:14px;font-size:12px;color:#666">Replay: <code>private_html/cis_transfer_runs/' . h($run_id) . '</code> • Last: <code>private_html/cis_transfer_runs/LAST_RUN_RESULTS.json</code></div>');
        echo_html('</div>');
    }
} catch (Throwable $e) {
    // Emergency dump
    $base = __DIR__ . '/../../private_html/cis_transfer_runs';
    ensure_dir($base);
    $err = [
        'ts'   => date('c'),
        'err'  => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString()),
    ];
    @file_put_contents($base . '/EMERGENCY_LAST_RUN.json', jenc($err));
    http_response_code(500);
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo jenc(['ok' => false, 'error' => $e->getMessage()]);
    } else {
        echo_html('<pre style="color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;padding:12px;border-radius:8px">FATAL: ' . h($e->getMessage()) . ' in ' . h($e->getFile()) . ':' . (int)$e->getLine() . "</pre>");
    }
}
?>