<?php
// Núcleo compartilhado: config, banco, auth, helpers e funil.
declare(strict_types=1);

date_default_timezone_set('UTC');
header('X-Content-Type-Options: nosniff');

$CFG_FILE = dirname(__DIR__) . '/config.php';
if (!file_exists($CFG_FILE)) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'config.php não encontrado. Copie config.example.php para config.php e preencha.']);
  exit;
}
$CFG = require $CFG_FILE;

const STAGES = ['novo', 'contato', 'agendado', 'compareceu', 'ganho', 'perdido'];
const STAGE_LABELS = [
  'novo' => 'Novo lead',
  'contato' => 'Em contato',
  'agendado' => 'Agendado',
  'compareceu' => 'Compareceu',
  'ganho' => 'Ganho',
  'perdido' => 'Perdido',
];

// ---------- Armazenamento (arquivo JSON) ----------
// Todos os leads ficam em data/leads.json, protegido pelo .htaccess.
function store_path(): string { return dirname(__DIR__) . '/data/leads.json'; }
function store_read(): array {
  $f = store_path();
  if (!file_exists($f)) return ['leads' => []];
  $d = json_decode((string)file_get_contents($f), true);
  return is_array($d) && isset($d['leads']) ? $d : ['leads' => []];
}
/** Lê, aplica $fn(&$data) sob lock e grava. Retorna o que $fn retornar. */
function store_update(callable $fn) {
  $f = store_path();
  if (!is_dir(dirname($f))) mkdir(dirname($f), 0755, true);
  $h = fopen($f, 'c+');
  if (!$h) json_out(500, ['error' => 'Não foi possível abrir data/leads.json. Verifique a permissão da pasta data/.']);
  flock($h, LOCK_EX);
  $raw = stream_get_contents($h);
  $data = json_decode($raw ?: '', true);
  if (!is_array($data) || !isset($data['leads'])) $data = ['leads' => []];
  $ret = $fn($data);
  ftruncate($h, 0); rewind($h);
  fwrite($h, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
  fflush($h); flock($h, LOCK_UN); fclose($h);
  return $ret;
}

// ---------- Helpers ----------
function now(): string { return gmdate('Y-m-d\TH:i:s\Z'); }
function json_out(int $status, $body): never {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function body(): array {
  $raw = file_get_contents('php://input') ?: '';
  if (strlen($raw) > 65536) json_out(413, ['error' => 'Requisição grande demais']);
  $ct = $_SERVER['CONTENT_TYPE'] ?? '';
  if (str_contains($ct, 'application/json')) {
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
  }
  if ($_POST) return $_POST;
  parse_str($raw, $d);
  return $d ?: [];
}
function clean($v, int $max = 200): ?string {
  if ($v === null) return null;
  $s = trim((string)$v);
  if ($s === '') return null;
  return mb_substr($s, 0, $max);
}
function normalize_phone($v): string {
  $d = preg_replace('/\D/', '', (string)$v);
  if (strlen($d) === 10 || strlen($d) === 11) $d = '55' . $d;
  return $d;
}
function valid_phone(string $d): bool { return strlen($d) >= 12 && strlen($d) <= 13; }
function client_ip(): string {
  $xf = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
  if ($xf) return trim(explode(',', $xf)[0]);
  return $_SERVER['REMOTE_ADDR'] ?? '';
}
function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }

// ---------- Auth ----------
function auth_header(): string {
  foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
    if (!empty($_SERVER[$k])) return $_SERVER[$k];
  }
  if (function_exists('apache_request_headers')) {
    $h = apache_request_headers();
    foreach ($h as $k => $v) if (strcasecmp($k, 'Authorization') === 0) return $v;
  }
  if (!empty($_SERVER['PHP_AUTH_USER'])) {
    return 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . ($_SERVER['PHP_AUTH_PW'] ?? ''));
  }
  return '';
}
function who(): ?string {
  global $CFG;
  $h = auth_header();
  if ($CFG['api_token'] && hash_equals('Bearer ' . $CFG['api_token'], $h)) return 'api';
  if (str_starts_with($h, 'Basic ')) {
    $dec = base64_decode(substr($h, 6), true) ?: '';
    [$u, $p] = array_pad(explode(':', $dec, 2), 2, '');
    if ($CFG['admin_pass'] && hash_equals($CFG['admin_user'], $u) && hash_equals($CFG['admin_pass'], $p)) return $u;
  }
  return null;
}
function require_auth(bool $asJson = true): string {
  $w = who();
  if ($w) return $w;
  http_response_code(401);
  header('WWW-Authenticate: Basic realm="Bravvo CRM", charset="UTF-8"');
  if ($asJson) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error' => 'Acesso restrito']); }
  else { header('Content-Type: text/plain; charset=utf-8'); echo 'Acesso restrito'; }
  exit;
}

// ---------- Leads ----------
function cast_lead(array $r): array {
  $r['valor'] = ($r['valor'] ?? null) === null ? null : (float)$r['valor'];
  $r['stage_label'] = STAGE_LABELS[$r['stage']] ?? $r['stage'];
  $r['events'] = array_values($r['events'] ?? []);
  return $r;
}
function get_lead(string $id): ?array {
  $d = store_read();
  return isset($d['leads'][$id]) ? cast_lead($d['leads'][$id]) : null;
}
function all_leads(): array {
  $d = store_read();
  $out = array_map('cast_lead', array_values($d['leads']));
  usort($out, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
  return $out;
}
function make_event(string $type, ?string $from, ?string $to, ?string $text, string $by): array {
  return ['at' => now(), 'type' => $type, 'from_stage' => $from, 'to_stage' => $to, 'text' => $text, 'by' => $by];
}

// ---------- Rate limit simples (arquivo temporário) ----------
function rate_limited(string $ip, int $max = 10): bool {
  $f = sys_get_temp_dir() . '/bravvo_rl_' . md5($ip);
  $t = time();
  $arr = file_exists($f) ? array_filter(array_map('intval', file($f, FILE_IGNORE_NEW_LINES)), fn($x) => $t - $x < 60) : [];
  $arr[] = $t;
  @file_put_contents($f, implode("\n", $arr));
  return count($arr) > $max;
}

// ---------- Meta Conversions API (opcional) ----------
function send_capi_lead(array $lead, string $eventId): void {
  global $CFG;
  if (!$CFG['meta_pixel_id'] || !$CFG['meta_capi_token'] || !function_exists('curl_init')) return;
  $sha = fn($s) => hash('sha256', $s);
  $parts = preg_split('/\s+/', mb_strtolower(trim($lead['nome'])));
  $first = array_shift($parts);
  $user = [
    'ph' => [$sha($lead['whatsapp'])],
    'fn' => [$sha($first)],
  ];
  if ($parts) $user['ln'] = [$sha(implode(' ', $parts))];
  if (!empty($lead['ip'])) $user['client_ip_address'] = $lead['ip'];
  if (!empty($lead['user_agent'])) $user['client_user_agent'] = $lead['user_agent'];
  if (!empty($lead['fbp'])) $user['fbp'] = $lead['fbp'];
  if (!empty($lead['fbc'])) $user['fbc'] = $lead['fbc'];
  $payload = ['data' => [[
    'event_name' => 'Lead',
    'event_time' => time(),
    'event_id' => $eventId,
    'action_source' => 'website',
    'event_source_url' => $lead['landing_url'] ?? null,
    'user_data' => $user,
  ]]];
  if ($CFG['meta_test_event_code']) $payload['test_event_code'] = $CFG['meta_test_event_code'];
  $ch = curl_init("https://graph.facebook.com/v21.0/{$CFG['meta_pixel_id']}/events?access_token={$CFG['meta_capi_token']}");
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 4,
  ]);
  $res = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code >= 300) error_log("[capi] falhou $code $res");
}
