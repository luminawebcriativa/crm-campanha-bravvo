<?php
// Renderiza um template de templates/ substituindo {{CHAVE}} pelas configurações públicas.
declare(strict_types=1);
function render_page(string $file): never {
  $cfgFile = __DIR__ . '/config.php';
  $cfg = file_exists($cfgFile) ? require $cfgFile : [];
  $vars = [
    'META_PIXEL_ID' => $cfg['meta_pixel_id'] ?? '',
    'WHATSAPP_NUMBER' => preg_replace('/\D/', '', $cfg['whatsapp_number'] ?? ''),
  ];
  $html = file_get_contents(__DIR__ . '/templates/' . $file);
  $html = preg_replace_callback('/\{\{(\w+)\}\}/', fn($m) => $vars[$m[1]] ?? '', $html);
  header('Content-Type: text/html; charset=utf-8');
  echo $html;
  exit;
}
