<?php
// Copie para config.php e preencha. Nunca suba o config.php para o git.
return [
  // Login do CRM (/crm) e da API administrativa
  'admin_user' => 'bravvo',
  'admin_pass' => 'troque-esta-senha',

  // Token para automações (CLI, Claude, n8n...). Header: Authorization: Bearer <token>
  'api_token' => 'troque-este-token',

  // Pixel da Meta (obrigatório para o evento Lead na página de obrigado)
  'meta_pixel_id' => '',

  // Opcional: Conversions API (envio server-side do evento Lead, deduplicado com o Pixel)
  'meta_capi_token' => '',
  'meta_test_event_code' => '',

  // WhatsApp da barbearia, só números com DDI
  'whatsapp_number' => '5562985633838',
];
