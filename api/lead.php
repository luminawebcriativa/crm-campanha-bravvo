<?php
// /api/leads/{id}
//   GET     → um lead com histórico
//   PATCH   → {stage, lost_reason, valor, note, nome, whatsapp, servico, horario, setor, quando, by}  (POST também aceito)
//   DELETE  → remove
require __DIR__ . '/_bootstrap.php';

$who = require_auth();
$id = preg_replace('/[^a-z0-9]/', '', (string)($_GET['id'] ?? ''));
$lead = $id ? get_lead($id) : null;
if (!$lead) json_out(404, ['error' => 'Lead não encontrado']);

$m = method();
if ($m === 'GET') json_out(200, $lead);

if ($m === 'PATCH' || $m === 'POST') {
  $b = body();
  $by = clean($b['by'] ?? null, 40) ?: $who;

  // validações antes de tocar no arquivo
  $stage = null; $lost = null;
  if (array_key_exists('stage', $b)) {
    $stage = (string)$b['stage'];
    if (!in_array($stage, STAGES, true)) json_out(400, ['error' => 'Etapa inválida. Use: ' . implode(', ', STAGES)]);
    $lost = $stage === 'perdido' ? clean($b['lost_reason'] ?? null, 200) : null;
    if ($stage === 'perdido' && !$lost) json_out(400, ['error' => 'Informe o motivo da perda (lost_reason).']);
  }
  $valor = (isset($b['valor']) && $b['valor'] !== '') ? (float)str_replace(',', '.', (string)$b['valor']) : null;
  $wa = array_key_exists('whatsapp', $b) ? normalize_phone($b['whatsapp']) : null;
  if ($wa !== null && !valid_phone($wa)) json_out(400, ['error' => 'WhatsApp inválido']);
  $note = clean($b['note'] ?? null, 2000);
  $info = [
    'nome' => clean($b['nome'] ?? null, 120), 'whatsapp' => $wa ?: null,
    'servico' => clean($b['servico'] ?? null, 80), 'horario' => clean($b['horario'] ?? null, 80), 'setor' => clean($b['setor'] ?? null, 80), 'quando' => clean($b['quando'] ?? null, 40),
  ];

  store_update(function (&$d) use ($id, $stage, $lost, $valor, $info, $note, $by) {
    if (!isset($d['leads'][$id])) return;
    $l = &$d['leads'][$id];
    $ts = now(); $changed = false;
    if ($stage !== null) {
      if ($stage !== $l['stage']) {
        $l['events'][] = make_event('stage', $l['stage'], $stage, $lost ? "Perdido: $lost" : null, $by);
        $l['stage'] = $stage; $changed = true;
      }
      $l['lost_reason'] = $stage === 'perdido' ? $lost : null;
    }
    foreach ($info as $k => $v) if ($v !== null) { $l[$k] = $v; $changed = true; }
    if ($valor !== null) { $l['valor'] = $valor; $changed = true; }
    if ($note) { $l['events'][] = make_event('note', null, null, $note, $by); $changed = true; }
    if ($changed) $l['updated_at'] = $ts;
  });
  json_out(200, get_lead($id));
}

if ($m === 'DELETE') {
  store_update(function (&$d) use ($id) { unset($d['leads'][$id]); });
  json_out(200, ['ok' => true]);
}

json_out(405, ['error' => 'Método não permitido']);
