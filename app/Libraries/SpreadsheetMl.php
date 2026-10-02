<?php

namespace App\Libraries;

/**
 * Excel SpreadsheetML 2003 — XML que Excel abre nativo como .xls.
 * Suficiente para el exporte de cartera del tenant suspendido sin
 * depender de PhpSpreadsheet/composer.
 */
class SpreadsheetMl
{
    private array $sheets = [];

    /** Agrega una hoja: nombre + filas (primera fila = encabezados). */
    public function hoja(string $nombre, array $filas): self
    {
        $this->sheets[$nombre] = $filas;
        return $this;
    }

    /** XML completo del libro. */
    public function xml(): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<?mso-application progid="Excel.Sheet"?>' . "\n"
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n"
            . '<Styles><Style ss:ID="h"><Font ss:Bold="1"/></Style>'
            . '<Style ss:ID="n"><NumberFormat ss:Format="#,##0.00"/></Style></Styles>' . "\n";

        foreach ($this->sheets as $nombre => $filas) {
            $out .= '<Worksheet ss:Name="' . $this->esc(mb_substr($nombre, 0, 31)) . '"><Table>' . "\n";
            $primera = true;
            foreach ($filas as $fila) {
                $out .= '<Row>';
                foreach ($fila as $celda) {
                    $out .= $this->celda($celda, $primera);
                }
                $out .= "</Row>\n";
                $primera = false;
            }
            $out .= "</Table></Worksheet>\n";
        }

        return $out . "</Workbook>\n";
    }

    private function celda($v, bool $encabezado): string
    {
        $style = $encabezado ? ' ss:StyleID="h"' : '';
        if (is_int($v) || is_float($v)) {
            return '<Cell' . ($encabezado ? $style : ' ss:StyleID="n"') . '><Data ss:Type="Number">' . $v . '</Data></Cell>';
        }
        return '<Cell' . $style . '><Data ss:Type="String">' . $this->esc((string) $v) . '</Data></Cell>';
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
