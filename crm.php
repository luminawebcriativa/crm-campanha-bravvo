<?php
require __DIR__ . '/api/_bootstrap.php';
require __DIR__ . '/_page.php';
require_auth(false);
render_page('crm.html');
