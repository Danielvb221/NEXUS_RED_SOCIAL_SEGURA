<?php
require __DIR__ . '/config/database.php';
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$checks = [
    'NEXUS_OLTP' => $pdo_oltp !== null,
    'NEXUS (Data Warehouse)' => $pdo_dw !== null,
];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Diagnóstico de conexiones NEXUS</title><style>body{font-family:system-ui,sans-serif;background:#111827;color:#f3f4f6;padding:2rem;max-width:800px;margin:auto}.box{background:#1f2937;border-radius:12px;padding:1rem;margin:1rem 0}.ok{color:#86efac}.bad{color:#fca5a5}</style></head><body><h1>Diagnóstico de conexiones NEXUS</h1><p>Esta página verifica las conexiones configuradas sin mostrar credenciales.</p><?php foreach($checks as $name=>$ok): ?><div class="box"><strong><?=h($name)?></strong> — <span class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'Conectado' : 'Sin conexión' ?></span></div><?php endforeach; ?><?php if(!$pdo_oltp || !$pdo_dw): ?><div class="box">Revisa config/secrets.local.php, drivers PHP y firewall de Azure. Detalle OLTP: <?=h($db_error ?? 'No disponible')?>.</div><?php endif; ?><p><a href="index.php">Volver a NEXUS</a></p></body></html>
