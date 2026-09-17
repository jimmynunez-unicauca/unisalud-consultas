<?php
if (!defined('ABSPATH')) {
    exit;
}

$config_dir = WP_PLUGIN_DIR . '/config-unicauca';

if (file_exists($config_dir . '/conexion.php')) {
    require_once $config_dir . '/conexion.php';
}
if (file_exists($config_dir . '/conexion-postgresql.php')) {
    require_once $config_dir . '/conexion-postgresql.php';
}

use Plugin\ConfigUnicauca\Conexion;

class UsuariosSaludDatabase
{
    private static $mysql  = null;
    private static $pgsql  = null;

    /**
     * Conexión MySQL a test_gestor (usuarios_otp)
     * @return mysqli|null
     */
    public static function mysql()
    {
        if (self::$mysql === null) {
            try {
                if (class_exists('Plugin\\ConfigUnicauca\\Conexion')) {
                    self::$mysql = Conexion::conectar('test_gestor');
                } else {
                    error_log('UsuariosSaludDatabase: Clase Conexion no encontrada');
                }
            } catch (Exception $e) {
                error_log('UsuariosSaludDatabase mysql error: ' . $e->getMessage());
            }
        }
        return self::$mysql;
    }

    /**
     * Conexión PostgreSQL a unisaludb
     * @return PDO|null
     */
    public static function pgsql()
    {
        if (self::$pgsql === null) {
            try {
                if (class_exists('ConexionPostgresql')) {
                    self::$pgsql = ConexionPostgresql::conectar();
                } else {
                    error_log('UsuariosSaludDatabase: Clase ConexionPostgresql no encontrada');
                }
            } catch (Exception $e) {
                error_log('UsuariosSaludDatabase pgsql error: ' . $e->getMessage());
            }
        }
        return self::$pgsql;
    }
}