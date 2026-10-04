<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title) ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; color: #1A202C; background: #EEF2F6; font-size: 13px; line-height: 1.55; }
    .bar { position: sticky; top: 0; display: flex; justify-content: space-between; align-items: center;
           background: #2E3542; color: #fff; padding: 12px 24px; z-index: 9; }
    .bar a, .bar button { color: #fff; text-decoration: none; background: #30CB9A; border: 0; border-radius: 8px;
        padding: 8px 18px; font-size: 13px; font-weight: 600; cursor: pointer; }
    .bar a.volver { background: transparent; border: 1px solid rgba(255,255,255,.4); }
    .doc { max-width: 800px; margin: 26px auto; background: #fff; padding: 44px 52px;
           box-shadow: 0 2px 10px rgba(30,40,60,.08); border-radius: 6px; }
    .doc h1 { font-size: 18px; text-align: center; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
    .doc h2 { font-size: 12.5px; text-align: center; color: #4A5568; font-weight: 500; margin-bottom: 22px; }
    .doc h3 { font-size: 13px; text-transform: uppercase; margin: 18px 0 8px; border-bottom: 1.5px solid #E2E8F0; padding-bottom: 5px; }
    .doc-meta { display: flex; justify-content: space-between; font-size: 12px; color: #4A5568;
                border-bottom: 2px solid #30CB9A; padding-bottom: 10px; margin-bottom: 18px; }
    table.plan { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 8px; }
    table.plan th { background: #2E3542; color: #fff; padding: 7px 8px; text-align: right; font-size: 11px; }
    table.plan th:first-child, table.plan td:first-child, table.plan th:nth-child(2), table.plan td:nth-child(2) { text-align: left; }
    table.plan td { padding: 6px 8px; border-bottom: 1px solid #EEF2F6; text-align: right; }
    table.plan tr:nth-child(even) td { background: #F8FAFC; }
    .kv { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin: 10px 0; }
    .kv div { display: flex; justify-content: space-between; border-bottom: 1px dotted #CBD5E0; padding: 3px 0; }
    .kv label { color: #4A5568; font-size: 12px; }
    .kv p { font-weight: 600; }
    .tot { margin-top: 10px; padding: 8px 14px; background: #F0FBF7; border-left: 3px solid #30CB9A; font-size: 12.5px; }
    .doc p.body { text-align: justify; margin: 10px 0; }
    .firmas { display: flex; justify-content: space-between; gap: 60px; margin-top: 60px; }
    .firmas div { flex: 1; text-align: center; }
    .firmas .linea { border-top: 1.5px solid #1A202C; padding-top: 6px; font-size: 11.5px; margin-top: 60px; }
    .firma2 { margin-top: 50px; width: 260px; }
    ul.gar { list-style: none; margin: 8px 0; }
    ul.gar li { padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 8px; margin-bottom: 6px;
                display: flex; justify-content: space-between; }
    .borrador { position: absolute; top: 14px; right: 16px; font-size: 10.5px; color: #B45309;
                background: #FFF4E5; border: 1px solid #FDE2C8; border-radius: 6px; padding: 3px 9px; }
    .doc { position: relative; }
    @media print {
        body { background: #fff; }
        .bar { display: none; }
        .doc { margin: 0; box-shadow: none; border-radius: 0; padding: 20px 28px;
               page-break-before: always; max-width: 100%; }
        .doc:first-of-type { page-break-before: avoid; }
        table.plan th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
<?php
$cliente  = trim(($s['nombres'] ?? '') . ' ' . ($s['apellidos'] ?? ''));
$dirCli   = !empty($secciones['direccion']) ? trim(($secciones['direccion'][0]['ciudad'] ?? '') . ', ' . ($secciones['direccion'][0]['barrio'] ?? '') . ' ' . ($secciones['direccion'][0]['detalle'] ?? ''), ', ') : '—';
$telCli   = $s['telefono'] ?? '—';
$gestor   = trim(($s['gestor'] ?? '') ?: 'Sin asignar');
$freqTxt  = ($lblFreq[$plan['freq']] ?? $plan['freq']) . ($plan['freq'] === 'DI' ? ' (' . (int) ($s['dias_semana'] ?? 3) . ' días/sem)' : '');
$esBorrador = !$plan['aprobado'];
$fechaDoc   = date('d/m/Y');
?>

<div class="bar">
    <span>Solicitud #<?= (int) $s['id'] ?> — <?= esc($cliente) ?></span>
    <span style="display:flex; align-items:center; gap:10px;">
        <a class="volver" href="<?= base_url('credito/solicitudes/' . $s['id']) ?>">← Volver a la solicitud</a>
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </span>
</div>

<!-- ======================== 1. PLAN DE PAGO ======================== -->
<section class="doc">
    <?php if ($esBorrador): ?><span class="borrador">Simulación — sin aprobar</span><?php endif; ?>
    <div class="doc-meta">
        <span><strong><?= esc($tenant['nombre'] ?? '') ?></strong></span>
        <span>Crédito: <strong><?= esc($s['codigo_credito'] ?? 'Sin asignar') ?></strong> · Emitido: <?= $fechaDoc ?></span>
    </div>
    <h1>Plan de pago</h1>
    <h2>Cliente: <?= esc($cliente) ?> · Cédula <?= esc($s['cedula'] ?? '—') ?></h2>

    <div class="kv">
        <div><label>Monto <?= $plan['aprobado'] ? 'aprobado' : 'solicitado' ?></label><p><?= esc($mon) ?> <?= number_format($plan['monto'], 2) ?></p></div>
        <div><label>Tasa mensual</label><p><?= number_format($plan['tasa'], 2) ?>%</p></div>
        <div><label>Plazo</label><p><?= (float) $plan['plazo'] ?> meses</p></div>
        <div><label>Frecuencia</label><p><?= esc($freqTxt) ?></p></div>
        <div><label>Cuota</label><p><?= esc($mon) ?> <?= number_format($plan['cuota'], 2) ?></p></div>
        <div><label>Nº de pagos</label><p><?= $plan['pagos'] ?></p></div>
        <div><label>Primer pago</label><p><?= esc($plan['fecha_inicio']) ?></p></div>
        <div><label>Último pago</label><p><?= esc($plan['fecha_fin']) ?></p></div>
    </div>

    <table class="plan">
        <thead><tr><th>#</th><th>Fecha</th><th>Cuota</th><th>Interés</th><th>Capital</th><th>Saldo</th></tr></thead>
        <tbody>
        <?php foreach ($plan['rows'] as $r): ?>
            <tr>
                <td><?= $r['n'] ?></td>
                <td><?= esc($r['fecha']) ?></td>
                <td><?= number_format($r['cuota'], 2) ?></td>
                <td><?= number_format($r['interes'], 2) ?></td>
                <td><?= number_format($r['capital'], 2) ?></td>
                <td><?= number_format($r['saldo'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="tot">Total a pagar: <strong><?= esc($mon) ?> <?= number_format($plan['total'], 2) ?></strong>
        · Intereses: <?= esc($mon) ?> <?= number_format($plan['intereses'], 2) ?></div>
</section>

<!-- ======================== 2. CONTRATO ======================== -->
<?php
// Costo total del crédito (Ley 842 — transparencia al consumidor):
// intereses del plan + cargos administrativos/seguro del tenant → CAT anualizado.
$_cargos   = round((float) $plan['monto'] * ((float) ($tenant['comision_pct'] ?? 0) + (float) ($tenant['seguro_pct'] ?? 0)) / 100, 2);
$_costoFin = (float) $plan['intereses'] + $_cargos;
$_cat      = ((float) $plan['monto'] > 0 && (float) $plan['plazo'] > 0)
    ? round($_costoFin / (float) $plan['monto'] / ((float) $plan['plazo'] / 12) * 100, 2) : 0.0;
$_moraPct  = (float) ($tenant['mora_diaria_pct'] ?? 0);
?>
<section class="doc">
    <?php if ($esBorrador): ?><span class="borrador">Simulación — sin aprobar</span><?php endif; ?>
    <div class="doc-meta">
        <span><strong><?= esc($tenant['nombre'] ?? '') ?></strong><?= !empty($tenant['ruc']) ? ' · RUC ' . esc($tenant['ruc']) : '' ?><?= !empty($tenant['conami_registro']) ? ' · Reg. CONAMI ' . esc($tenant['conami_registro']) : '' ?></span>
        <span>Contrato de crédito: <strong><?= esc($s['codigo_credito'] ?? 'Pendiente') ?></strong> · <?= $fechaDoc ?></span>
    </div>
    <h1>Contrato de crédito</h1>
    <h2>Entre <?= esc($tenant['nombre'] ?? '') ?> y <?= esc($cliente) ?></h2>

    <h3>Primero — Las partes</h3>
    <p class="body">
        Comparecen: por una parte <strong><?= esc($tenant['razon_social'] ?? $tenant['nombre'] ?? '') ?></strong><?= !empty($tenant['ruc']) ? ', con RUC ' . esc($tenant['ruc']) : '' ?><?= !empty($tenant['direccion']) ? ', con domicilio en ' . esc($tenant['direccion']) : '' ?>,
        en adelante «EL ACREEDOR»; y por la otra <strong><?= esc($cliente) ?></strong>, mayor de edad,
        con cédula Nº <strong><?= esc($s['cedula'] ?? '—') ?></strong>, domiciliado(a) en <?= esc($dirCli) ?>,
        teléfono <?= esc($telCli) ?>, en adelante «EL DEUDOR».
    </p>

    <h3>Segundo — Objeto y monto</h3>
    <p class="body">
        EL ACREEDOR otorga a EL DEUDOR un crédito por <strong><?= esc($mon) ?> <?= number_format($plan['monto'], 2) ?></strong>,
        destinado a: <?= esc($s['destino'] ?? 'libre inversión') ?>.
    </p>

    <h3>Tercero — Condiciones</h3>
    <p class="body">
        Tasa de interés: <strong><?= number_format($plan['tasa'], 2) ?>% mensual</strong>.
        Plazo: <strong><?= (float) $plan['plazo'] ?> meses</strong>. Modalidad de pago: <strong><?= esc($freqTxt) ?></strong>,
        en <strong><?= $plan['pagos'] ?> cuotas</strong> de <strong><?= esc($mon) ?> <?= number_format($plan['cuota'], 2) ?></strong>,
        la primera con vencimiento el <strong><?= esc($plan['fecha_inicio']) ?></strong> y la última el
        <strong><?= esc($plan['fecha_fin']) ?></strong>, conforme al plan de pago adjunto.
    </p>

    <h3>Cuarto — Costo total del crédito</h3>
    <p class="body">
        Intereses del plan: <strong><?= esc($mon) ?> <?= number_format($plan['intereses'], 2) ?></strong><?php if ($_cargos > 0): ?>
        · Cargos administrativos y seguro: <strong><?= esc($mon) ?> <?= number_format($_cargos, 2) ?></strong><?php endif; ?>.
        Monto a pagar en total: <strong><?= esc($mon) ?> <?= number_format($plan['monto'] + $_costoFin, 2) ?></strong>.
        Costo Anual Total (CAT): <strong><?= number_format($_cat, 2) ?>%</strong>.
        EL DEUDOR declara conocer y aceptar el costo total de este crédito, conforme a la Ley No. 842
        de Protección de los Derechos de las Personas Consumidoras.
    </p>

    <h3>Quinto — Garantías</h3>
    <p class="body">
        EL DEUDOR respalda esta obligación con las garantías y referencias descritas en el anexo
        «Relación de garantías», que forma parte integrante de este contrato.
    </p>

    <h3>Sexto — Incumplimiento e interés moratorio</h3>
    <p class="body">
        El atraso en cualquier cuota faculta a EL ACREEDOR a dar por vencida la totalidad de la deuda
        y exigir el pago inmediato del saldo insoluto más los intereses devengados.<?php if ($_moraPct > 0): ?>
        Desde el día siguiente al vencimiento, el saldo pendiente de cada cuota devenga un
        <strong>interés moratorio del <?= number_format($_moraPct, 2) ?>% diario</strong> hasta su
        pago total.<?php endif; ?>
    </p>

    <h3>Séptimo — Protección de datos personales</h3>
    <p class="body">
        EL DEUDOR autoriza expresamente a EL ACREEDOR a recopilar, almacenar y tratar sus datos
        personales (identificación, contacto, expediente socioeconómico y comportamiento de pago)
        con la única finalidad de evaluar, administrar y cobrar este crédito, conforme a la
        Ley No. 787 de Protección de Datos Personales. Los datos no serán cedidos a terceros salvo
        obligación legal o autorización judicial.
    </p>

    <h3>Octavo — Origen de fondos (Ley 977)</h3>
    <p class="body">
        EL DEUDOR declara que los fondos con que atenderá esta obligación provienen de actividades
        lícitas y se obliga a informar a EL ACREEDOR cualquier cambio en su perfil de cliente,
        conforme a la Ley No. 977 contra el Lavado de Activos, el Financiamiento al Terrorismo y
        el Financiamiento a la Proliferación de Armas de Destrucción Masiva.
    </p>

    <p class="body">En común acuerdo, las partes firman.</p>

    <div class="firmas">
        <div><div class="linea">EL ACREEDOR<br><?= esc($tenant['nombre'] ?? '') ?></div></div>
        <div><div class="linea">EL DEUDOR<br><?= esc($cliente) ?> — Céd. <?= esc($s['cedula'] ?? '—') ?></div></div>
    </div>
</section>

<!-- ======================== 3. GARANTÍAS ======================== -->
<section class="doc">
    <div class="doc-meta">
        <span><strong><?= esc($tenant['nombre'] ?? '') ?></strong><?= !empty($tenant['ruc']) ? ' · RUC ' . esc($tenant['ruc']) : '' ?><?= !empty($tenant['conami_registro']) ? ' · Reg. CONAMI ' . esc($tenant['conami_registro']) : '' ?></span>
        <span>Crédito: <strong><?= esc($s['codigo_credito'] ?? 'Pendiente') ?></strong> · <?= $fechaDoc ?></span>
    </div>
    <h1>Relación de garantías</h1>
    <h2>Anexo del contrato <?= esc($s['codigo_credito'] ?? '') ?> — <?= esc($cliente) ?></h2>

    <h3>Bienes declarados</h3>
    <?php if (empty($secciones['activo'])): ?>
        <p class="body">El deudor no declaró bienes en garantía.</p>
    <?php else: ?>
        <ul class="gar">
            <?php $totAct = 0; foreach ($secciones['activo'] as $a): $totAct += (float) $a['valor']; ?>
                <li><span><?= esc($a['descripcion']) ?></span><strong><?= esc($mon) ?> <?= number_format((float) $a['valor'], 2) ?></strong></li>
            <?php endforeach; ?>
        </ul>
        <div class="tot">Valor total declarado: <strong><?= esc($mon) ?> <?= number_format($totAct, 2) ?></strong></div>
    <?php endif; ?>

    <h3>Referencias personales</h3>
    <?php if (empty($secciones['referencia'])): ?>
        <p class="body">Sin referencias registradas.</p>
    <?php else: ?>
        <ul class="gar">
            <?php foreach ($secciones['referencia'] as $r): ?>
                <li><span><?= esc($r['nombre']) ?> — <?= esc($r['parentesco'] ?? '') ?></span><strong><?= esc($r['telefono'] ?? '—') ?></strong></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3>Declaración</h3>
    <p class="body">
        EL DEUDOR declara bajo fe de juramento que los bienes y referencias descritos son de su
        propiedad o corresponden a personas que avalan su obligación, y se compromete a mantenerlos
        vigentes durante la vida del crédito.
    </p>

    <div class="firma2">
        <div class="linea">Firma del deudor — <?= esc($cliente) ?></div>
    </div>
</section>

</body>
</html>
