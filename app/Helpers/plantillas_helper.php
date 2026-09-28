<?php

/**
 * Plantillas propuestas del sistema — contenido base que el tenant
 * puede cargar en el editor y personalizar. Clave = slug de la plantilla.
 * Variables dinámicas: {empresa} {razon_social} {lema} {direccion} {telefono}
 * {cliente} {cedula} {credito} {monto} {cuotas} {tasa} {cuota} {fecha}
 * {voucher_footer} {empleado}
 */
if (!function_exists('plantillas_sistema')) {

    function plantillas_sistema(): array
    {
        return [
            'voucher_recibo' => [
                'nombre'    => 'Voucher de recibo',
                'contenido' =>
                    '<div style="text-align:center">'
                    . '<p style="margin:0"><strong style="font-size:18px">{empresa}</strong></p>'
                    . '<p style="margin:0"><em>{lema}</em></p>'
                    . '<p style="margin:0">{direccion}</p>'
                    . '<p style="margin:0">Tel: {telefono}</p>'
                    . '</div><hr>'
                    . '<p style="text-align:center"><strong>RECIBO DE PAGO</strong></p>'
                    . '<p><strong>No. Recibo:</strong> {recibo}</p>'
                    . '<p><strong>Fecha:</strong> {fecha}</p>'
                    . '<p><strong>Cliente:</strong> {cliente}</p>'
                    . '<p><strong>Cédula:</strong> {cedula}</p>'
                    . '<p><strong>Crédito No.:</strong> {credito}</p>'
                    . '<hr>'
                    . '<p><strong>Cuota No.:</strong> {cuota_num}</p>'
                    . '<p><strong>Monto pagado:</strong> RD$ {monto}</p>'
                    . '<p><strong>Capital:</strong> RD$ {capital}</p>'
                    . '<p><strong>Interés:</strong> RD$ {interes}</p>'
                    . '<p><strong>Mora:</strong> RD$ {mora}</p>'
                    . '<hr>'
                    . '<p><strong>Balance pendiente:</strong> RD$ {balance}</p>'
                    . '<p style="text-align:center">Atendido por: {empleado}</p>'
                    . '<p style="text-align:center"><em>{voucher_footer}</em></p>',
            ],
            'contrato_prestamo' => [
                'nombre'    => 'Contrato de préstamo',
                'contenido' =>
                    '<h2 style="text-align:center">CONTRATO DE PRÉSTAMO DE DINERO</h2>'
                    . '<p style="text-align:center"><strong>{empresa}</strong> — {razon_social}<br>{direccion} | Tel: {telefono}</p>'
                    . '<p><strong>ENTRE:</strong></p>'
                    . '<p style="text-align:justify">{empresa}, en adelante <strong>EL ACREEDOR</strong>, representada por {empleado}, y {cliente}, con cédula {cedula}, en adelante <strong>EL DEUDOR</strong>,</p>'
                    . '<p><strong>SE ACUERDA LO SIGUIENTE:</strong></p>'
                    . '<p style="text-align:justify"><strong>PRIMERO:</strong> EL ACREEDOR entrega a EL DEUDOR la suma de <strong>RD$ {monto}</strong>.</p>'
                    . '<p style="text-align:justify"><strong>SEGUNDO:</strong> EL DEUDOR pagará <strong>{cuotas}</strong> cuotas de <strong>RD$ {cuota}</strong> cada una, a una tasa de interés del <strong>{tasa}%</strong>.</p>'
                    . '<p style="text-align:justify"><strong>TERCERO:</strong> En caso de mora se aplicará el cargo correspondiente según las políticas de EL ACREEDOR.</p>'
                    . '<p style="text-align:justify">En fe de lo cual, las partes firman el presente contrato.</p>'
                    . '<p><strong>Fecha:</strong> {fecha}</p>'
                    . '<br><br>'
                    . '<table style="width:100%;text-align:center"><tr>'
                    . '<td>_______________________<br><strong>EL ACREEDOR</strong><br>{empresa}</td>'
                    . '<td>_______________________<br><strong>EL DEUDOR</strong><br>{cliente}</td>'
                    . '</tr></table>',
            ],
            'pagare' => [
                'nombre'    => 'Pagaré',
                'contenido' =>
                    '<h2 style="text-align:center">PAGARÉ</h2>'
                    . '<p style="text-align:justify">Por valor recibido, yo, <strong>{cliente}</strong>, con cédula <strong>{cedula}</strong>, prometo pagar a la orden de <strong>{empresa}</strong> ({razon_social}), con domicilio en {direccion}, la suma de:</p>'
                    . '<p style="text-align:center"><strong style="font-size:20px">RD$ {monto}</strong></p>'
                    . '<p style="text-align:justify">en <strong>{cuotas}</strong> cuotas de <strong>RD$ {cuota}</strong>, con interés del <strong>{tasa}%</strong>.</p>'
                    . '<p style="text-align:justify">En caso de incumplimiento, el saldo total vencerá de inmediato y será exigible en su totalidad.</p>'
                    . '<p>Hecho en {direccion}, el <strong>{fecha}</strong>.</p>'
                    . '<br><br>'
                    . '<p style="text-align:center">_______________________<br><strong>EL DEUDOR</strong><br>{cliente}<br>Cédula: {cedula}</p>',
            ],
        ];
    }
}
