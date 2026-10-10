<?php

namespace App\Services\Admin;

use Config\Database;

/**
 * Gestión/backup de la BD del sistema (panel admin).
 * Lista tablas con su tamaño y genera el .sql: solo estructura
 * (SHOW CREATE TABLE) o backup completo con INSERT por lotes.
 */
class BackupService
{
    /** Tablas de la BD activa con filas y tamaño aproximado. */
    public function tablas(): array
    {
        $db = Database::connect();
        return $db->table('information_schema.TABLES')
            ->select('TABLE_NAME AS nombre, ENGINE AS motor, TABLE_ROWS AS filas,'
                . ' ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 1) AS kb,'
                . ' UPDATE_TIME AS actualizada, CREATE_TIME AS creada')
            ->where('TABLE_SCHEMA', $db->getDatabase())
            ->orderBy('TABLE_NAME')
            ->get()->getResultArray();
    }

    /**
     * Genera el .sql en $ruta. $solo vacío = todas las tablas.
     * @return array{tablas: int, bytes: int}
     */
    public function generar(array $solo, bool $conDatos, string $ruta): array
    {
        set_time_limit(300);
        $db     = Database::connect();
        $todas  = $db->listTables();
        $tablas = $solo ? array_values(array_intersect($todas, $solo)) : $todas;

        $fh = fopen($ruta, 'wb');
        fwrite($fh, "-- Contamos — " . ($conDatos ? 'backup completo' : 'solo estructura') . "\n"
            . "-- Generado: " . date('Y-m-d H:i:s') . " · BD: " . $db->getDatabase() . "\n\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        foreach ($tablas as $t) {
            $ddl = $db->query("SHOW CREATE TABLE `$t`")->getRowArray();
            fwrite($fh, "-- $t\nDROP TABLE IF EXISTS `$t`;\n"
                . ($ddl['Create Table'] ?? $ddl['Create View'] ?? '') . ";\n\n");
            if ($conDatos) {
                $this->volcarDatos($db, $t, $fh);
            }
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fh);
        return ['tablas' => count($tablas), 'bytes' => filesize($ruta) ?: 0];
    }

    /** INSERT por lotes de 200 filas — escapado con $db->escape(). */
    private function volcarDatos($db, string $tabla, $fh): void
    {
        $offset = 0;
        while (true) {
            $rows = $db->query("SELECT * FROM `$tabla` LIMIT 200 OFFSET $offset")
                ->getResultArray();
            if (!$rows) {
                break;
            }
            $lotes = [];
            foreach ($rows as $r) {
                $cols = array_map(
                    static fn ($v) => $v === null ? 'NULL' : $db->escape($v),
                    array_values($r)
                );
                $lotes[] = '(' . implode(',', $cols) . ')';
            }
            fwrite($fh, "INSERT INTO `$tabla` VALUES\n" . implode(",\n", $lotes) . ";\n");
            $offset += 200;
            if (count($rows) < 200) {
                break;
            }
        }
    }
}
