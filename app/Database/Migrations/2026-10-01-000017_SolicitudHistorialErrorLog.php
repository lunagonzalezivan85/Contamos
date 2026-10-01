<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * solicitud_historial — timeline de estados de la solicitud
 *   (quién la creó, movió, aprobó; nota al mandar a revisión).
 * solicitudes.nota_revision — observación visible al gestor en REVISION.
 * error_log — bitácora de errores de runtime (visor/purga en admin).
 */
class SolicitudHistorialErrorLog extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------
        // solicitud_historial
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'solicitud_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'accion'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ESTADO'],
            'estado'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'nota'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'user_id'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'empleado_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'actor'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('solicitud_id', false, false, 'idx_sol_hist_sol');
        $this->forge->addKey('tenant_id', false, false, 'idx_sol_hist_tenant');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_sol_hist_tenant');
        $this->forge->addForeignKey('solicitud_id', 'solicitudes', 'id', 'CASCADE', 'CASCADE', 'fk_sol_hist_sol');
        $this->forge->createTable('solicitud_historial', true);

        // Observación que ve el gestor cuando la solicitud pasa a REVISION
        $this->forge->addColumn('solicitudes', [
            'nota_revision' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'paso_dias'],
        ]);

        // ---------------------------------------------------------------
        // error_log — errores de runtime (nunca se muestran crudos al usuario)
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'user_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'origen'     => ['type' => 'VARCHAR', 'constraint' => 120],
            'mensaje'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'traza'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id', false, false, 'idx_error_log_tenant');
        $this->forge->addKey('created_at', false, false, 'idx_error_log_fecha');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'SET NULL', 'fk_error_log_tenant');
        $this->forge->createTable('error_log', true);
    }

    public function down()
    {
        $this->forge->dropTable('error_log', true);
        $this->forge->dropColumn('solicitudes', 'nota_revision');
        $this->forge->dropTable('solicitud_historial', true);
    }
}
