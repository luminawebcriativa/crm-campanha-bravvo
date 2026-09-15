<?php
// POST /api/leads  → cria lead (público, usado pelo formulário)
// GET  /api/leads  → lista todos com histórico (autenticado)
require __DIR__ . '/_bootstrap.php';

if (method() === 'POST') {
  $ip = client_ip();
  if (rate_limited($ip)) json_out(429, ['error' => 'Muitas tentativas. Tente de novo em 1 minuto.']);
  $b = body();
  if (!empty($b['website'])) json_out(200, ['ok' => true, 'id' => null]); // honeypot

  $nome = clean($b['nome'] ?? null, 120);
  $wa = normalize_phone($b['whatsapp'] ?? '');
  if (!$nome || mb_strlen($nome) < 2) json_out(400, ['error' => 'Informe seu nome.']);
  if (!valid_phone($wa)) json_out(400, ['error' => 'Informe um WhatsApp válido com DDD.']);

  $id = bin2hex(random_bytes(6));
  $ts = now();
  $fbclid = clean($b['fbclid'] ?? null, 300);
  $fbc = clean($b['fbc'] ?? null, 300) ?: ($_COOKIE['_fbc'] ?? null) ?: ($fbclid ? 'fb.1.' . (int)(microtime(true) * 1000) . ".$fbclid" : null);
  $fbp = clean($b['fbp'] ?? null, 100) ?: ($_COOKIE['_fbp'] ?? null);
  $lead = [
    'id' => $id, 'created_at' => $ts, 'updated_at' => $ts,
    'nome' => $nome, 'whatsapp' => $wa,
    'servico' => clean($b['servico'] ?? null, 80), 'horario' => clean($b['horario'] ?? null, 80), 'deslocamento' => clean($b['deslocamento'] ?? null, 80),
    'stage' => 'novo', 'lost_reason' => null, 'valor' => null,
    'utm_source' => clean($b['utm_source'] ?? null), 'utm_medium' => clean($b['utm_medium'] ?? null), 'utm_campaign' => clean($b['utm_campaign'] ?? null),
    'utm_content' => clean($b['utm_content'] ?? null), 'utm_term' => clean($b['utm_term'] ?? null),
    'fbclid' => $fbclid, 'fbp' => $fbp, 'fbc' => $fbc,
    'ip' => $ip, 'user_agent' => clean($_SERVER['HTTP_USER_AGENT'] ?? null, 300),
    'referrer' => clean($b['referrer'] ?? null, 500), 'landing_url' => clean($b['landing_url'] ?? null, 1000),
    'events' => [make_event('created', null, 'novo', 'Lead criado pelo formulário', 'site')],
  ];
  $dup = store_update(function (&$d) use ($lead, $wa) {
    $limite = gmdate('Y-m-d\TH:i:s\Z', time() - 600);
    foreach ($d['leads'] as $l) if ($l['whatsapp'] === $wa && $l['created_at'] > $limite) return $l['id'];
    $d['leads'][$lead['id']] = $lead;
    return null;
  });
  if ($dup) json_out(200, ['ok' => true, 'id' => $dup, 'duplicate' => true]);

  $eventId = "lead_$id";
  // responde primeiro, envia CAPI depois (não segura o usuário)
  http_response_code(201);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  $out = json_encode(['ok' => true, 'id' => $id, 'event_id' => $eventId]);
  header('Content-Length: ' . strlen($out));
  header('Connection: close');
  echo $out;
  if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else { @ob_end_flush(); flush(); }
  send_capi_lead(get_lead($id), $eventId);
  exit;
}

if (method() === 'GET') {
  require_auth();
  json_out(200, all_leads());
}

json_out(405, ['error' => 'Método não permitido']);
