<?php
// GET /api/funil → etapas e rótulos do funil (usado pelo CRM). Nome 'funil' porque 'config.php' é bloqueado no servidor.
require __DIR__ . '/_bootstrap.php';
require_auth();
json_out(200, ['stages' => STAGES, 'labels' => STAGE_LABELS, 'whatsapp' => $CFG['whatsapp_number']]);
