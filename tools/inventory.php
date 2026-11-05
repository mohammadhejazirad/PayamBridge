<?php

declare(strict_types=1);

$inventory = json_decode(file_get_contents(__DIR__.'/../research/api-inventory.json'), true, flags: JSON_THROW_ON_ERROR);
$operations = $inventory['operations'];
$seen = [];
$counts = ['total' => count($operations), 'implemented' => 0, 'contract_tested' => 0, 'live_verified' => 0];
foreach ($operations as $operation) {
    $id = $operation['id'];
    if (isset($seen[$id])) {
        throw new RuntimeException('Duplicate operation ID: '.$id);
    }
    $seen[$id] = true;
    foreach (['provider', 'surface', 'operation', 'source_url', 'source_revision', 'wire_method', 'implementation', 'contract_test', 'live_test'] as $required) {
        if (! isset($operation[$required]) || $operation[$required] === '') {
            throw new RuntimeException('Missing inventory field '.$required.' in '.$id);
        }
    }
    $implemented = $operation['implementation'] === 'implemented';
    if ($implemented && ($operation['response_model'] === null || $operation['schema_status'] !== 'normalized-initial-slice')) {
        throw new RuntimeException('Implemented operation has no normalized contract: '.$id);
    }
    if ($implemented) {
        foreach ($operation['parameters'] as $parameter) {
            foreach (['wire_name', 'type', 'required', 'nullable', 'omission_semantics'] as $field) {
                if (! array_key_exists($field, $parameter) || $parameter[$field] === null) {
                    throw new RuntimeException('Unspecified implemented parameter '.$field.' in '.$id);
                }
            }
        }
    }
    $counts['implemented'] += (int) $implemented;
    $counts['contract_tested'] += (int) ($operation['contract_test'] === 'passed');
    $counts['live_verified'] += (int) ($operation['live_test'] === 'passed');
}

echo json_encode([
    'scope' => $inventory['scope'],
    'complete_product_inventory' => $inventory['complete_product_inventory'],
    'counts' => $counts,
    'percentages' => array_map(static fn (int $count): float => round(100 * $count / max(1, $counts['total']), 2), array_diff_key($counts, ['total' => true])),
    'limitations' => ['Ratios apply only to the current partial inventory; pending and blocked operations stay in the denominator.', 'Source-based contract fixtures are synthetic. No valid-token live tests have run.'],
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
