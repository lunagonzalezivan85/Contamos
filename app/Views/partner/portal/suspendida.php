<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($title ?? 'Servicio suspendido') ?></title>
<style>
    * { margin: 0; box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: linear-gradient(135deg, #1A202C 0%, #2E3542 100%);
        min-height: 100vh; display: flex; align-items: center; justify-content: center;
        padding: 20px;
    }
    .card {
        background: #fff; border-radius: 20px; max-width: 420px; width: 100%;
        padding: 38px 30px; text-align: center;
        box-shadow: 0 24px 60px rgba(0,0,0,.35);
    }
    .ico {
        width: 68px; height: 68px; margin: 0 auto 16px; border-radius: 20px;
        background: #FEF3C7; display: flex; align-items: center; justify-content: center;
        font-size: 34px;
    }
    h1 { font-size: 20px; color: #1A202C; margin-bottom: 8px; }
    p { color: #64748B; font-size: 14px; line-height: 1.55; }
    .emp { font-weight: 700; color: #2E3542; }
    .salir {
        display: inline-block; margin-top: 22px; padding: 11px 26px;
        background: #2E3542; color: #fff; border-radius: 12px;
        font-size: 14px; font-weight: 600; text-decoration: none;
    }
    .salir:hover { background: #1A202C; }
</style>
</head>
<body>
<div class="card">
    <div class="ico">⏸</div>
    <h1>Servicio suspendido</h1>
    <p>
        La suscripción de <span class="emp"><?= esc($tenant['nombre'] ?? 'la empresa') ?></span>
        está <?= strtolower($estado['estado'] ?? 'vencida') ?> por falta de pago.
        Tu administrador debe realizar el pago para reactivar el acceso.
    </p>
    <a class="salir" href="<?= base_url($slug . '/portal/salir') ?>">Salir</a>
</div>
</body>
</html>
