<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CIS Transfer Engine — Run <?=h($run_id)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
.main-container { background: rgba(255,255,255,0.98); backdrop-filter: blur(10px); border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
.card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-radius: 12px; transition: transform 0.2s; }
.card:hover { transform: translateY(-2px); }
.stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.badge-status { font-size: 0.75rem; padding: 0.4rem 0.8rem; }
.nested-table { background: #f8f9ff; border: 1px solid #e3e8f7; }
.reason-badge { font-size: 0.7rem; }
.sticky-toolbar { position: sticky; top: 0; z-index: 10; background: #fff; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
.table thead th { position: sticky; top: 0; background: #212529; color: #fff; z-index: 5; }
.kpi { font-weight: 700; font-size: 1.25rem; }
.small-muted { font-size: .85rem; color: #6c757d; }
</style>
</head>
<body class="p-4">


<?php
// ---------- PREP: Build fast lookup maps & aggregates (Hardened) ----------
$transfers = $dump['transfers'] ?? [];
$uniqueOutletIds = [];
$uniqueProductIds = [];
$uniqueContainers = [];
$aggByDest = []; // dest_id => [orders, lines, weight, value, freight]

foreach ($transfers as $t) {
    $src = (string)($t['source_outlet_id'] ?? '');
    $dst = (string)($t['dest_outlet_id'] ?? '');
    if ($src !== '') $uniqueOutletIds[$src] = true;
    if ($dst !== '') $uniqueOutletIds[$dst] = true;

    $container = (string)($t['freight_container'] ?? '');
    if ($container !== '') $uniqueContainers[$container] = true;

    $lines = is_array($t['lines'] ?? null) ? $t['lines'] : [];
    $sumWeight = 0; $sumValue = 0; $lineCount = 0;
    foreach ($lines as $ln) {
        $lineCount++;
        $sumWeight += (int)($ln['est_weight_grams'] ?? 0);
        $sumValue  += (float)($ln['est_value'] ?? 0.0);
        $pid = (string)($ln['product_id'] ?? '');
        if ($pid !== '') $uniqueProductIds[$pid] = true;
    }
    $aggByDest[$dst] ??= ['orders'=>0,'lines'=>0,'weight'=>0,'value'=>0.0,'freight'=>0.0];
    $aggByDest[$dst]['orders']  += 1;
    $aggByDest[$dst]['lines']   += $lineCount;
    $aggByDest[$dst]['weight']  += $sumWeight;
    $aggByDest[$dst]['value']   += $sumValue;
    $aggByDest[$dst]['freight'] += (float)($t['freight_estimate'] ?? 0.0);
}

// Bulk fetch outlet names (schema: vend_outlets.id,name)
$OUTLET_NAME_MAP = [];
if (!empty($uniqueOutletIds)) {
    $ids = array_keys($uniqueOutletIds);
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $con->prepare("SELECT id, name FROM vend_outlets WHERE id IN ($place)");
    if ($stmt) {
        $types = str_repeat('s', count($ids));
        $stmt->bind_param($types, ...$ids);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $OUTLET_NAME_MAP[(string)$row['id']] = $row['name'];
            }
        }
        $stmt->close();
    }
}

// Bulk fetch product names & sku (schema: vend_products.id,name,sku)
$PRODUCT_INFO_MAP = [];
if (!empty($uniqueProductIds)) {
    $ids = array_keys($uniqueProductIds);
    foreach (array_chunk($ids, 800) as $chunk) {
        $place = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $con->prepare("SELECT id, name, sku FROM vend_products WHERE id IN ($place)");
        if ($stmt) {
            $types = str_repeat('s', count($chunk));
            $stmt->bind_param($types, ...$chunk);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $PRODUCT_INFO_MAP[(string)$row['id']] = ['name'=>$row['name'], 'sku'=>$row['sku']];
                }
            }
            $stmt->close();
        }
    }
}


function productInfo(string $pid, array $ln) : array {
    global $PRODUCT_INFO_MAP;
    if (isset($PRODUCT_INFO_MAP[$pid])) return $PRODUCT_INFO_MAP[$pid];
    $dbg = $ln['debug_json'] ?? [];
    $nm  = (is_array($dbg) && !empty($dbg['name'])) ? (string)$dbg['name'] : ("Product ".substr($pid,0,8).'…');
    $sku = (is_array($dbg) && !empty($dbg['sku']))  ? (string)$dbg['sku']  : '';
    return ['name'=>$nm, 'sku'=>$sku];
}

/**
 * Extract analytics (best-effort) from a line’s debug_json / fields.
 * Returns array with keys:
 * - src_on_hand, dst_on_hand, demand_7d, demand_14d, cover_days, mos, unit_price
 * - flags (array of strings), reason_code, rounding_mode, rounding_applied (bool)
 */
function extractLineAnalytics(array $ln) : array {
    $dbg = is_array($ln['debug_json'] ?? null) ? $ln['debug_json'] : [];

    $src_on_hand = $dbg['on_hand_hub']   ?? $dbg['source_on_hand'] ?? null;
    $dst_on_hand = $dbg['on_hand_store'] ?? $dbg['dest_on_hand']   ?? null;
    $d7   = $dbg['demand_7d']  ?? $dbg['demand7']  ?? null;
    $d14  = $dbg['demand_14d'] ?? $dbg['demand14'] ?? null;
    $cov  = $dbg['coverage_days'] ?? $dbg['cover_days'] ?? null;
    $mos  = $dbg['mos'] ?? $dbg['months_of_supply'] ?? null;
    $price= $dbg['unit_price'] ?? $dbg['price'] ?? null;

    $rounding_mode   = $dbg['rounding_mode'] ?? null;
    $rounding_applied= (bool)($dbg['rounding_applied'] ?? $dbg['rounded'] ?? false);

    // Decision flags (prefer explicit arrays if present)
    $flags = [];
    foreach (['flags','decision_flags','filters_applied','gates'] as $k) {
        if (!empty($dbg[$k]) && is_array($dbg[$k])) {
            foreach ($dbg[$k] as $f) $flags[] = (string)$f;
        }
    }
    // heuristic booleans -> flags
    $booleans = [
        'profitability_passed' => 'profit_ok',
        'min_line_value_passed'=> 'min_line_value_ok',
        'min_unit_price_passed'=> 'min_unit_price_ok',
        'oversupply_throttle'  => 'oversupply_throttle',
        'hub_throttled'        => 'hub_throttled',
        'cap_applied'          => 'cap_applied',
        'seed'                 => 'seed',
        'overflow_move'        => 'overflow_move',
        'shipping_gate_blocked'=> 'shipping_blocked',
    ];
    foreach ($booleans as $k=>$label) {
        if (isset($dbg[$k]) && ($dbg[$k] === true || $dbg[$k] === 1 || $dbg[$k] === '1')) $flags[] = $label;
    }
    $flags = array_values(array_unique($flags));

    $reason_code = (string)($ln['reason_code'] ?? ($dbg['reason_code'] ?? ''));

    return [
        'src_on_hand' => is_null($src_on_hand) ? '' : (int)$src_on_hand,
        'dst_on_hand' => is_null($dst_on_hand) ? '' : (int)$dst_on_hand,
        'demand_7d'   => is_null($d7) ? '' : (float)$d7,
        'demand_14d'  => is_null($d14)? '' : (float)$d14,
        'cover_days'  => is_null($cov)? '' : (float)$cov,
        'mos'         => is_null($mos)? '' : (float)$mos,
        'unit_price'  => is_null($price)? '' : (float)$price,
        'rounding_mode'    => $rounding_mode ?: '',
        'rounding_applied' => $rounding_applied,
        'flags'       => $flags,
        'reason_code' => $reason_code,
    ];
}

// Totals
$totalTransfers  = count($transfers);
$totalHeaders    = (int)($dump['applied']['headers'] ?? 0);
$totalLines      = (int)($dump['applied']['lines'] ?? 0);
$totalCandidates = (int)($dump['summary']['candidate_products'] ?? 0);
$totalDests      = (int)($dump['summary']['destinations'] ?? 0);

// Lists for filters
$containerList = array_keys($uniqueContainers);
$destOptions   = array_keys($aggByDest);
sort($containerList);
sort($destOptions);



// Helpers using maps with robust fallback to line debug_json
function outletName(string $id) : string {
    global $OUTLET_NAME_MAP;
    return $OUTLET_NAME_MAP[$id] ?? ("Outlet #".$id);
}


// Totals
$totalTransfers = count($transfers);
$totalHeaders   = (int)($dump['applied']['headers'] ?? 0);
$totalLines     = (int)($dump['applied']['lines'] ?? 0);
$totalCandidates= (int)($dump['summary']['candidate_products'] ?? 0);
$totalDests     = (int)($dump['summary']['destinations'] ?? 0);

// Build unique lists for filter selects
$containerList = array_keys($uniqueContainers);
$destOptions = array_keys($aggByDest);
sort($containerList);
sort($destOptions);
?>

<div class="container-fluid main-container p-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1"><i class="bi bi-arrow-left-right text-success"></i> CIS Transfer Engine</h1>
                    <div class="text-muted">
                        <i class="bi bi-calendar-event"></i> Run <?=h($run_id)?> • 
                        <?php if ($simulate): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-eye"></i> Simulation Mode</span>
                        <?php else: ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Live Apply Mode</span>
                        <?php endif; ?>
                        • Engine <?=h($engine_ver)?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($simulate): ?>
                        <button class="btn btn-success" id="createAsTransferBtn">
                            <i class="bi bi-database-add"></i> CREATE AS TRANSFER
                        </button>
                        <button class="btn btn-outline-success" id="insertAllBtn">
                            <i class="bi bi-collection"></i> INSERT ALL
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <a class="btn btn-outline-success" href="?format=json">>
                        <i class="bi bi-file-earmark-code"></i> Export JSON
                    </a>
                    <button class="btn btn-success" id="btnExportCsv">>
                        <i class="bi bi-download"></i> Export CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="bi bi-box-seam fs-2 mb-2"></i>
                    <div class="kpi"><?= $totalCandidates ?></div>
                    <div>Candidate Products</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="bi bi-geo-alt fs-2 mb-2"></i>
                    <div class="kpi"><?= $totalDests ?></div>
                    <div>Destinations</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="bi bi-clipboard-check fs-2 mb-2"></i>
                    <div class="kpi"><?= $totalHeaders ?></div>
                    <div>Transfer Headers</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="bi bi-list-ul fs-2 mb-2"></i>
                    <div class="kpi"><?= $totalLines ?></div>
                    <div>Transfer Lines</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sticky Filter Toolbar -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="p-3 sticky-toolbar">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Destination</label>
                        <select class="form-select form-select-sm" id="filterDest">
                            <option value="">All Destinations</option>
                            <?php foreach ($destOptions as $dst): ?>
                                <option value="<?=h(outletName($dst))?>"><?=h(outletName($dst))?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Container</label>
                        <select class="form-select form-select-sm" id="filterContainer">
                            <option value="">All Containers</option>
                            <?php foreach ($containerList as $c): ?>
                                <option value="<?=h($c)?>"><?=h($c)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Min Lines</label>
                        <input type="number" class="form-control form-control-sm" id="filterMinLines" min="0" value="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Min Value ($)</label>
                        <input type="number" class="form-control form-control-sm" id="filterMinValue" min="0" step="1" value="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Search</label>
                        <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="Product, SKU or Outlet">
                    </div>
                    <div class="col-12 mt-2 d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="btnExpandAll"><i class="bi bi-arrows-expand"></i> Expand All</button>
                        <button class="btn btn-sm btn-outline-secondary" id="btnCollapseAll"><i class="bi bi-arrows-collapse"></i> Collapse All</button>
                        <span class="small-muted ms-auto"><i class="bi bi-file-earmark-text"></i> Replay file: <code><?=h($file)?></code></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Parameters & Skips -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-sliders"></i> Run Parameters</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        $params_display = [
                            'Cover Days' => $cover_days,
                            'Buffer %' => $buffer_pct . '%',
                            'Rounding Mode' => ucfirst($rounding_mode),
                            'Min Units/Line' => $min_units_per_line,
                            'Floor Sales Threshold' => $floor_sales_thr,
                            'Default Floor Qty' => $default_floor_qty,
                            'Turnover Min Multiplier' => $turnover_min_mult,
                            'Turnover Max Multiplier' => $turnover_max_mult,
                            'Margin Factor' => $margin_factor,
                            'Max Products' => $max_products ?: 'Unlimited',
                            'Transfer Mode' => ucfirst(str_replace('_', ' ', $transfer_mode)),
                            'Source Outlet' => $source_outlet ? "#{$source_outlet}" : 'Auto (Warehouse)',
                            'Destination Outlet' => $dest_outlet ? "#{$dest_outlet}" : 'Auto (All Stores)',
                            'Warehouse Only' => $warehouse_only ? 'Yes' : 'No',
                            'Exclude Warehouses' => $exclude_warehouses ? 'Yes' : 'No'
                        ];
                        foreach ($params_display as $label => $value): ?>
                            <div class="col-md-6 mb-2">
                                <strong><?= $label ?>:</strong> 
                                <span class="text-muted"><?= h((string)$value) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Skip Reasons Summary</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($dump['summary']['skips'])): ?>
                        <?php foreach ($dump['summary']['skips'] as $reason => $count): 
                            $label = ucfirst(str_replace('_',' ',$reason));
                            $w = max(4, min(100, (int)$count)); // simple bar width
                        ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span class="badge bg-secondary reason-badge"><?= h($label) ?></span>
                                    <span class="fw-bold"><?= (int)$count ?></span>
                                </div>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-secondary" role="progressbar" style="width: <?=$w?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0"><i class="bi bi-check-circle text-success"></i> No skips recorded</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Destination Breakdown -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Destination Breakdown</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($aggByDest)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-exclamation-circle fs-1 mb-3"></i>
                    <h6>No Destinations</h6>
                    <p class="mb-0">Check demand data, min line qty, buffer %, or profitability rules.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Destination</th>
                                <th class="text-end">Orders</th>
                                <th class="text-end">Lines</th>
                                <th class="text-end">Weight (kg)</th>
                                <th class="text-end">Value ($K)</th>
                                <th class="text-end">Freight ($)</th>
                                <th class="text-end">Stats</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($aggByDest as $dst => $a): 
                            $weightKg = (float)$a['weight'] / 1000;
                            $valueK = (float)$a['value'] / 1000;
                            $freightCost = (float)$a['freight'];
                            $costPerKg = $weightKg > 0 ? ($freightCost / $weightKg) : 0;
                            $valuePerKg = $weightKg > 0 ? ((float)$a['value'] / $weightKg) : 0;
                            
                            // Enhanced calculations
                            $avgBoxWeight = 15; // kg per box estimate
                            $estimatedBoxes = $weightKg > 0 ? ceil($weightKg / $avgBoxWeight) : 0;
                            $costPerBox = $estimatedBoxes > 0 ? ($freightCost / $estimatedBoxes) : 0;
                            $profitMargin = (float)$a['value'] > 0 ? (((float)$a['value'] - $freightCost) / (float)$a['value']) * 100 : 0;
                            $linesPerOrder = (int)$a['orders'] > 0 ? ((int)$a['lines'] / (int)$a['orders']) : 0;
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?=h(outletName($dst))?></div>
                                    <small class="text-muted">ID: <?=h($dst)?></small>
                                </td>
                                <td class="text-end fw-bold"><?= (int)$a['orders'] ?></td>
                                <td class="text-end"><?= (int)$a['lines'] ?></td>
                                <td class="text-end"><?= number_format($weightKg, 1) ?> kg</td>
                                <td class="text-end text-success fw-bold">$<?= number_format($valueK, 1) ?>K</td>
                                <td class="text-end text-warning fw-bold">$<?= number_format($freightCost, 2) ?></td>
                                <td class="text-end">
                                    <small class="text-muted">
                                        <strong><?= $estimatedBoxes ?> boxes</strong><br>
                                        $<?= number_format($costPerBox, 2) ?>/box<br>
                                        $<?= number_format($costPerKg, 2) ?>/kg<br>
                                        <?= number_format($profitMargin, 1) ?>% margin<br>
                                        <?= number_format($linesPerOrder, 1) ?> lines/order
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Planned Transfers -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0"><i class="bi bi-truck"></i> Planned Transfer Orders</h5>
                <small>Each order contains multiple product line items</small>
            </div>
            <div class="text-white-50"><?= $totalTransfers ?> orders</div>
        </div>
        <div class="card-body p-0">
            <?php if (!$transfers): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-exclamation-circle fs-1 mb-3"></i>
                    <h6>No Transfers Generated</h6>
                    <p class="mb-0">This may be due to profitability guards, demand/stock constraints, or store filter restrictions.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                   <table class="table nested-table table-sm mb-0">
  <thead class="table-light">
    <tr>
      <th>Product</th>
      <th class="text-end">Qty</th>
      <th class="text-end">On Hand (Src)</th>
      <th class="text-end">On Hand (Dst)</th>
      <th class="text-end">Demand 14d</th>
      <th class="text-end">MOS</th>
      <th class="text-end">Weight (g)</th>
      <th class="text-end">Est. Value</th>
      <th>Reason</th>
      <th>Flags</th>
      <th>Details</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($lines as $ln):
      $pid = (string)($ln['product_id'] ?? '');
      $pi  = productInfo($pid, $ln);
      $pname = $pi['name'];
      $psku  = $pi['sku'];
      $qty   = (int)($ln['qty'] ?? 0);
      $w     = (int)($ln['est_weight_grams'] ?? 0);
      $v     = (float)($ln['est_value'] ?? 0.0);
      $dbg   = $ln['debug_json'] ?? [];
      $dbgEnc= base64_encode(json_encode($dbg, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

      $a = extractLineAnalytics($ln);
      $srcOn = $a['src_on_hand']; $dstOn = $a['dst_on_hand'];
      $d14   = $a['demand_14d'];  $mos = $a['mos'];
      $flags = $a['flags'];       $reason = $a['reason_code'];
      $rcClass = 'bg-secondary';
      $tag = strtolower((string)$reason);
      if (str_contains($tag,'demand')) $rcClass='bg-primary';
      elseif (str_contains($tag,'seed')) $rcClass='bg-warning text-dark';
      elseif (str_contains($tag,'overflow')) $rcClass='bg-danger';

      $flagsText = $flags ? implode(', ', $flags) : '';
    ?>
    <tr class="line-row"
        data-search="<?=h(strtolower($pname.' '.$psku.' '.$pid))?>"
        data-dbg="<?=$dbgEnc?>">
      <td>
        <div class="fw-bold">
          <a href="https://vapeshed.vendhq.com/product/<?=h($pid)?>" target="_blank" class="text-decoration-none">
            <?=h($pname)?>
            <span class="badge bg-primary ms-1" title="Open in Lightspeed"><i class="bi bi-box-arrow-up-right"></i></span>
          </a>
        </div>
        <?php if ($psku): ?><small class="text-muted">SKU: <?=h($psku)?></small><?php endif; ?>
      </td>
      <td class="text-end fw-bold"><?= number_format($qty) ?></td>
      <td class="text-end text-info fw-bold"><?= $srcOn === '' ? '—' : number_format((int)$srcOn) ?></td>
      <td class="text-end text-warning fw-bold"><?= $dstOn === '' ? '—' : number_format((int)$dstOn) ?></td>
      <td class="text-end"><?= $d14 === '' ? '—' : number_format((float)$d14,1) ?></td>
      <td class="text-end"><?= $mos === '' ? '—' : number_format((float)$mos,2) ?></td>
      <td class="text-end text-muted"><?= number_format($w) ?></td>
      <td class="text-end text-success fw-bold">$<?= number_format($v, 2) ?></td>
      <td><span class="badge <?=$rcClass?>"><?= h(ucfirst(str_replace('_',' ',$reason))) ?></span></td>
      <td class="small"><?= h($flagsText) ?></td>
      <td>
        <?php if (!empty($dbg)): ?>
          <button class="btn btn-sm btn-outline-info" onclick="showDetails('<?=$dbgEnc?>')">
            <i class="bi bi-info-circle"></i>
          </button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Data Tabs -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="ledger-tab" data-bs-toggle="tab" href="#ledger" role="tab">
                        <i class="bi bi-journal-text"></i> Decision Ledger
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="skipped-tab" data-bs-toggle="tab" href="#skipped" role="tab">
                        <i class="bi bi-x-circle"></i> Skipped Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="logs-tab" data-bs-toggle="tab" href="#logs" role="tab">
                        <i class="bi bi-file-text"></i> System Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="raw-tab" data-bs-toggle="tab" href="#raw" role="tab">
                        <i class="bi bi-code-square"></i> Raw Data
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="ledger" role="tabpanel">
                    <h6 class="mb-3">Decision Ledger (Sample — First 200 entries)</h6>
                    <pre class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"><code><?=h(jenc(array_slice($dump['ledger'] ?? [], 0, 200)))?></code></pre>
                    <small class="text-muted">Full ledger is available in the replay JSON file.</small>
                </div>
                <div class="tab-pane fade" id="logs" role="tabpanel">
                    <h6 class="mb-3">System Logs</h6>
                    <pre class="bg-light p-3 rounded border" style="max-height: 400px; overflow-y: auto;"><code><?=h(jenc($dump['logs'] ?? []))?></code></pre>
                </div>
                <div class="tab-pane fade" id="raw" role="tabpanel">
                    <h6 class="mb-3">Complete Data Dump</h6>
                    <pre class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"><code><?=h(jenc($dump ?? []))?></code></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Transfer Line Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="modalContent" class="bg-light p-3 rounded border"></pre>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
/*!
 * CIS Transfer Engine — Report JS (Production-Hardened)
 * - Expand/Collapse all, rich filters
 * - Robust Details modal
 * - Detailed CSV exporter (per-line analytics & flags)
 */
(function(){
  'use strict';
  const $  = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));

  // Expand/collapse
  $('#btnExpandAll')?.addEventListener('click', () => {
    $$('.transfer-row + .collapse').forEach(el => new bootstrap.Collapse(el, { show:true }));
  });
  $('#btnCollapseAll')?.addEventListener('click', () => {
    $$('.transfer-row + .collapse.show').forEach(el => new bootstrap.Collapse(el, { toggle:true }));
  });

  // Filtering
  function applyFilters() {
    const dest   = ($('#filterDest')?.value || '').trim();
    const cont   = ($('#filterContainer')?.value || '').trim().toLowerCase();
    const minLines = parseInt($('#filterMinLines')?.value || '0', 10);
    const minValue = parseFloat($('#filterMinValue')?.value || '0');
    const search = ($('#filterSearch')?.value || '').trim().toLowerCase();

    $$('#ordersTable tbody tr.transfer-row').forEach(row => {
      const rowDest = row.getAttribute('data-dest') || '';
      const rowCont = (row.getAttribute('data-container') || '').toLowerCase();
      const rowLines = parseInt(row.getAttribute('data-lines') || '0', 10);
      const rowValue = parseFloat(row.getAttribute('data-value') || '0');
      const rowSearch= (row.getAttribute('data-search') || '').toLowerCase();

      let ok = true;
      if (dest && rowDest !== dest) ok = false;
      if (ok && cont && rowCont !== cont) ok = false;
      if (ok && rowLines < minLines) ok = false;
      if (ok && rowValue < minValue) ok = false;
      if (ok && search && !rowSearch.includes(search)) ok = false;

      row.style.display = ok ? '' : 'none';
      const next = row.nextElementSibling;
      if (next && next.classList.contains('collapse')) {
        next.style.display = ok ? '' : 'none';
      }
    });
  }
  ['#filterDest','#filterContainer','#filterMinLines','#filterMinValue','#filterSearch'].forEach(id => {
    $(id)?.addEventListener('input', applyFilters);
  });
  applyFilters();

  // Details modal
  function showDetails(encodedJson) {
    try {
      const decoded = JSON.parse(atob(encodedJson));
      $('#modalContent').textContent = JSON.stringify(decoded, null, 2);
      new bootstrap.Modal($('#detailsModal')).show();
    } catch (e) {
      alert('Error parsing debug data: ' + e.message);
    }
  }
  window.showDetails = showDetails;

  // CSV Export — Detailed (orders + lines + analytics + flags)
  function toCsvRow(arr) {
    return arr.map(v => `"${String(v ?? '').replace(/"/g,'""')}"`).join(',');
  }

  $('#btnExportCsv')?.addEventListener('click', () => {
    const rows = [];
    rows.push([
      'run_id','source_id','source_name','dest_id','dest_name','container','freight_est',
      'product_id','product_name','sku','qty','on_hand_src','on_hand_dst','demand_7d','demand_14d',
      'cover_days','mos','unit_price','est_weight_g','est_value','reason','flags','rounding_mode','rounding_applied','debug_json_b64'
    ]);

    // Traverse visible orders
    $$('#ordersTable tbody tr.transfer-row').forEach(orderRow => {
      if (orderRow.style.display === 'none') return;

      const srcCell = orderRow.children[0];
      const dstCell = orderRow.children[1];
      const container = (orderRow.getAttribute('data-container') || '');
      const freightTxt = orderRow.children[3]?.textContent || '$0';
      const freight = (freightTxt.replace(/[^\d.]/g,'') || '0');

      // We stored the human name twice (badge + small). Extract IDs from the small text if present; else fall back.
      const srcName = srcCell?.querySelector('.small')?.textContent?.trim() || srcCell?.textContent?.trim() || '';
      const dstName = dstCell?.querySelector('.small')?.textContent?.trim() || dstCell?.textContent?.trim() || '';

      // IDs are in the next sibling collapse table rows; we also kept data-dest-id on the main row
      const destId = orderRow.getAttribute('data-dest-id') || '';
      // Source id is not carried as data attr; use name as fallback
      const sourceId = srcName;

      const linesRow = orderRow.nextElementSibling;
      if (!linesRow) return;

      // Each line-row now carries data-dbg with base64(JSON)
      linesRow.querySelectorAll('tbody tr.line-row').forEach(line => {
        const tds = line.querySelectorAll('td');

        const nameLink = tds[0]?.querySelector('.fw-bold a');
        const pname = nameLink ? nameLink.firstChild?.textContent?.trim() || '' : (tds[0]?.querySelector('.fw-bold')?.textContent?.trim() || '');
        const skuEl = tds[0]?.querySelector('.text-muted');
        const sku = skuEl ? (skuEl.textContent.replace('SKU:','').trim()) : '';

        const qty     = (tds[1]?.textContent || '').replace(/[^\d-]/g,'');
        const srcOn   = (tds[2]?.textContent || '').replace(/[^\d-]/g,'');
        const dstOn   = (tds[3]?.textContent || '').replace(/[^\d-]/g,'');
        const d14     = (tds[4]?.textContent || '').replace(/[^\d.-]/g,'');
        const mos     = (tds[5]?.textContent || '').replace(/[^\d.-]/g,'');
        const w       = (tds[6]?.textContent || '').replace(/[^\d-]/g,'');
        const valTxt  = (tds[7]?.textContent || '').replace(/[^\d.-]/g,'');
        const reason  = tds[8]?.innerText?.trim() || '';
        const flags   = tds[9]?.innerText?.trim() || '';

        let pid = '';
        if (nameLink) {
          const href = nameLink.getAttribute('href') || '';
          const m = href.match(/\/product\/([^\/\?]+)/);
          if (m) pid = m[1];
        }

        const dbgB64 = line.getAttribute('data-dbg') || '';
        let d7='', cov='', unitPrice='', roundingMode='', roundingApplied='';
        try {
          if (dbgB64) {
            const j = JSON.parse(atob(dbgB64));
            d7 = j.demand_7d ?? j.demand7 ?? '';
            cov = j.coverage_days ?? j.cover_days ?? '';
            unitPrice = j.unit_price ?? j.price ?? '';
            roundingMode = j.rounding_mode ?? '';
            roundingApplied = (j.rounding_applied || j.rounded) ? '1' : '0';
          }
        } catch (_) {}

        rows.push([
          ('<?=h($dump['run_id'] ?? 'manual')?>'),
          sourceId, srcName, destId, dstName, container, freight,
          pid, pname, sku, qty, srcOn, dstOn, d7, d14, cov, mos, unitPrice, w, valTxt, reason, flags, roundingMode, roundingApplied, dbgB64
        ]);
      });
    });

    const csv = rows.map(toCsvRow).join('\r\n');
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'transfer_run_<?=h($run_id)?>_detailed.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  });

})();
</script>


</body>
</html>