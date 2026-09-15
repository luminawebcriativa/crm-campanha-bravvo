<?php
// GET /api/stats → contagem por etapa, conversão e receita
require __DIR__ . '/_bootstrap.php';
require_auth();

$by = array_fill_keys(STAGES, 0);
$receita = 0.0;
$total = 0;
foreach (all_leads() as $r) {
  $total++;
  $by[$r['stage']] = ($by[$r['stage']] ?? 0) + 1;
  if ($r['stage'] === 'ganho') $receita += (float)($r['valor'] ?? 0);
}
json_out(200, [
  'total' => $total,
  'byStage' => $by,
  'ganhos' => $by['ganho'],
  'perdidos' => $by['perdido'],
  'receita' => $receita,
  'taxa_conversao' => $total ? round(100 * $by['ganho'] / $total, 1) : 0,
]);
