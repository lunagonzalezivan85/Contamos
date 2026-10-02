<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Contrato') ?> — Contamos</title>
    <link rel="stylesheet" href="<?= v_asset('css/admin-contrato.css') ?>">
</head>
<body>

<div class="toolbar-print">
    <a class="btn" href="<?= base_url('admin/leads') ?>">← Volver a leads</a>
    <?php if (session()->getFlashdata('success')): ?>
        <span class="ok"><?= esc(session()->getFlashdata('success')) ?></span>
    <?php endif; ?>
    <button type="button" class="btn btn-primary" id="btn-print">🖨 Imprimir / Guardar PDF</button>
</div>

<main class="hoja">
    <header class="ct-head">
        <div>
            <h1>CONTAMOS</h1>
            <p>Sistema de gestión de cartera de créditos — SaaS</p>
        </div>
        <div class="ct-meta">
            <b>Contrato Nº <?= str_pad((string) $c['id'], 5, '0', STR_PAD_LEFT) ?></b><br>
            Emitido: <?= esc(date('d/m/Y', strtotime($c['created_at'] ?? 'now'))) ?><br>
            Estado: <b><?= esc($c['estado']) ?></b>
        </div>
    </header>

    <h2 class="ct-titulo">Contrato de prestación de servicio SaaS</h2>

    <section class="ct-clausula">
        <h3>PRIMERA — Partes</h3>
        <p>
            Intervienen: <b>Contamos</b> («el Proveedor»), titular de la plataforma de gestión de cartera;
            y <b><?= esc($c['nombre']) ?><?= $c['negocio'] && $c['negocio'] !== $c['nombre'] ? ' (' . esc($c['negocio']) . ')' : '' ?></b>
            («el Cliente»), contactable al teléfono <b><?= esc($c['telefono']) ?></b><?= $c['correo'] ? ' y correo <b>' . esc($c['correo']) . '</b>' : '' ?>.
            <?php if ($c['codigo']): ?>Código de solicitud: <b><?= esc($c['codigo']) ?></b>.<?php endif; ?>
        </p>
    </section>

    <section class="ct-clausula">
        <h3>SEGUNDA — Objeto y precio</h3>
        <p>
            El Proveedor licencia al Cliente el uso de la plataforma bajo el plan
            <b><?= esc($c['plan'] ?? 'a medida') ?></b>, con una mensualidad de
            <b>USD <?= number_format((float) $c['monto_mensual'], 2) ?></b>. Los consumos que excedan los
            incluidos del plan se facturan como sobreconsumo según las tarifas vigentes del catálogo.
        </p>
    </section>

    <section class="ct-clausula">
        <h3>TERCERA — Vigencia</h3>
        <p>
            El contrato entra en vigencia el <b><?= esc(date('d/m/Y', strtotime($c['fecha_inicio']))) ?></b>,
            con renovación mensual automática mientras ninguna parte lo termine.
        </p>
    </section>

    <section class="ct-clausula">
        <h3>CUARTA — Forma de pago</h3>
        <p>
            El Cliente pagará la mensualidad <b>el día <?= (int) $c['dia_pago'] ?> de cada mes</b>,
            mediante transferencia o depósito a la cuenta bancaria:
            <b><?= esc($c['cuenta_bancaria'] ?: 'a confirmar por el Proveedor') ?></b>.
            El pago se acredita con la referencia bancaria correspondiente.
        </p>
    </section>

    <section class="ct-clausula">
        <h3>QUINTA — Periodo de gracia</h3>
        <p>
            Vencido el día de pago, el Cliente dispone de <b><?= (int) $c['gracia_dias'] ?> días de gracia</b>
            para regularizar sin interrupción del servicio.
        </p>
    </section>

    <section class="ct-clausula">
        <h3>SEXTA — Suspensión por falta de pago</h3>
        <p>
            Si el pago no se acredita dentro del periodo de gracia, el sistema <b>suspenderá
            automáticamente el acceso del Cliente</b> — tanto el panel administrativo del tenant
            como el portal y la aplicación de gestores — hasta que la deuda quede saldada.
            La suspensión no elimina los datos del Cliente.
        </p>
    </section>

    <section class="ct-clausula">
        <h3>SÉPTIMA — Reactivación y datos del Cliente</h3>
        <p>
            El acceso se reactiva una vez saldados los períodos pendientes. Si el Cliente requiere
            su información (cartera de créditos, clientes, pagos y abonos), se le entregará un
            archivo Excel con la totalidad de sus datos <b>previo pago del saldo pendiente</b>.
        </p>
    </section>

    <section class="ct-clausula">
        <h3>OCTAVA — Terminación</h3>
        <p>
            Cualquiera de las partes puede terminar el contrato notificando a la otra.
            La terminación no exime al Cliente del pago de los períodos ya generados.
        </p>
    </section>

    <footer class="ct-firmas">
        <div class="firma">
            <div class="linea"></div>
            <b>El Proveedor — Contamos</b>
            <small>Representante autorizado</small>
        </div>
        <div class="firma">
            <div class="linea"></div>
            <b>El Cliente — <?= esc($c['nombre']) ?></b>
            <small>Firma y fecha</small>
        </div>
    </footer>
</main>

<script src="<?= v_asset('js/admin-contrato.js') ?>"></script>
</body>
</html>
