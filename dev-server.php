<?php
// Só para testes locais. Não suba para a Hostinger.
// Uso: php -S localhost:8080 dev-server.php
$root = __DIR__;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// simula subpasta: BASE=/agendar php -S localhost:8080 dev-server.php
$base = rtrim(getenv('BASE') ?: '', '/');
if ($base) { if (!str_starts_with($path, $base . '/') && $path !== $base) { http_response_code(404); echo 'Fora da subpasta'; return true; } $path = substr($path, strlen($base)) ?: '/'; }
if (preg_match('#^/(crm|obrigado)/$#', $path, $mm)) { header('Location: ' . $base . '/' . $mm[1], true, 301); return true; }
$map = ['#^/?$#'=>'index.php','#^/obrigado/?$#'=>'obrigado.php','#^/crm/?$#'=>'crm.php','#^/api/leads/?$#'=>'api/leads.php','#^/api/stats/?$#'=>'api/stats.php','#^/api/config/?$#'=>'api/config.php'];
foreach ($map as $re => $f) if (preg_match($re, $path)) { chdir(dirname("$root/$f")); require "$root/$f"; return true; }
if (preg_match('#^/api/leads/([a-z0-9]+)/?$#', $path, $m)) { $_GET['id'] = $m[1]; chdir("$root/api"); require "$root/api/lead.php"; return true; }
$f = realpath($root . $path);
if ($f && str_starts_with($f, $root) && is_file($f) && !preg_match('#/(templates/|data/|config|_page|api/_bootstrap|\.htaccess|\.git|\.env|crm\.js|dev-server|README)#', $path)) {
  $mime = ['png'=>'image/png','jpg'=>'image/jpeg','svg'=>'image/svg+xml','css'=>'text/css','js'=>'text/javascript','webp'=>'image/webp'][strtolower(pathinfo($f, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
  header("Content-Type: $mime"); header('Cache-Control: max-age=3600'); readfile($f); return true;
}
http_response_code(404); echo 'Página não encontrada'; return true;
