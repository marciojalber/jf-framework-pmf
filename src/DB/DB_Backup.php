<?php

namespace JF\DB;

use JF\Config;
use JF\Exceptions\ErrorException as Error;
use JF\Messager;

/**
 * Classe que representa um banco-de-dados.
 */
class DB_Backup
{
    /**
     * Executa o backup de um banco-de-dados.
     */
    public static function backup( $schema_name, $path, $options = [] )
    {
        $msg = Messager::get( 'db', 'target_path_not_informed', $schema_name );;

        if ( !$path )
            throw new Error( $msg );

        $target         = $path . '/' . date( 'Ymd_His' ) . '_' . $schema_name . '.sql';
        $db             = DB::instance( $schema_name, [ 'disableException' => true ] );

        if ( !$db )
            return;

        $config         = $db->config();
        $username       = $config->username;
        $password       = !empty( $config->password )
            ? '-p' . $config->password
            : '';
        $args           = [];

        if ( !empty( $options[ 'noData' ] ) )
            $args[]     = ' --no-data';

        $args           = implode( ' ', $args );
        $dbname         = $config->dbname;
        $dump_cmd       = "mysqldump {$args} -u{$username} {$password} {$dbname} > {$target}";

        return shell_exec( $dump_cmd );
    }

    /**
     * Limpa uma tabela no banco-de-dados.
     */
    public function restore()
    {
        $db             = DB::instance( $this->schemaName, [ 'disableException' => true ] );

        if ( !$db )
            return;

        $config         = $db->config();
        $username       = $config->username;
        $password       = !empty( $config->password )
            ? ' -p' . $config->password
            : '';
        $dbname         = $config->dbname;
        $source         = DIR_STORAGE . '/bkpdb_' . $dbname . '.sql';
        $restore_cmd    = "mysql -u{$username}{$password} < {$source}";

        return shell_exec( $dump_cmd );
    }

    /**
     * Verifica se a rotina deve ser executada.
    public static function processBackupDB()
    {
        $env                = ENV;
        $config             = Config::get( [
            "db/$env.backups",
            'db/all.backups',
        ]);

        if ( !$config || empty( $config->frequency ) )
            return;

        $frequency          = $config->frequency;
        $frequency_measure  = $frequency[ 0 ];
        $frequency_value    = $frequency[ 1 ];
        $execution_filename = self::dbBackupLog();

        if ( !file_exists( $execution_filename ) )
            file_put_contents( $execution_filename, null );

        $last_execution     = file_get_contents( $execution_filename );
        
        if ( !$last_execution )
            return self::executeBackupDB( $config );
        
        $last_execution     = new \DateTime( $last_execution );
        $hours              = $frequency_measure !== 'd'
            ? (int) $last_execution->format( 'H' )
            : '0';
        $mins               = '0';
        $last_execution->setTime( $hours, $mins );

        $now                = new \DateTime();
        $diff               = $now->diff( $last_execution );

        if ( $diff->$frequency_measure >= $frequency_value )
            return self::executeBackupDB( $config );
    }
     */

    /**
     * Executa a rotina de Backups.
    private static function executeBackupDB( $config )
    {
        $execution_filename = self::dbBackupLog();
        $now                = new \DateTime();
        $env                = ENV;
        $schemas            = Config::get( [
            "db/$env.schemas",
            'db/all.schemas',
        ]);

        file_put_contents( $execution_filename, $now->format( 'Y-m-d H:i:s' ) );
        
        $destination        = !empty( $config->path )
            ? $config->path
            : DIR_BACKUPS;

        Dir::makeDir( $destination );

        $start              = $now->format( 'U.u' );
        $localhost          = [ 'localhost', '127.0.0.1' ];
        $hosts              = isset( $config->hosts )
            ? array_merge( $config->hosts, $localhost )
            : $localhost;

        set_time_limit( 0 );
        ini_set( 'memory_limit', -1 );

        foreach ( $schemas as $schema_name => $schema )
        {
            if ( empty( $schema->hostname ) )
                continue;

            if ( !in_array( $schema->hostname, $hosts ) )
                continue;

            $options        = in_array( $schema_name, $config->schemas )
                ? []
                : [ 'noData' => true ];
            DB_Backup::backup( $schema_name, $destination, $options );
        }
        
        $env                = ENV;
        $keep               = Config::get( [
            "db/$env.backups.keep",
            'db/all.backups.keep',
        ]);

        if ( !$keep )
            return;

        $keep_measure   = strtoupper( $keep[ 0 ] );
        $time_separator = in_array( $keep_measure, array( 'H', 'M', 'S' ) )
            ? 'T'
            : '';
        $keep_value     = strtoupper( $keep[ 1 ] );
        $interval_exp   = "P{$time_separator}{$keep_value}{$keep_measure}";
        $interval       = new \DateInterval( $interval_exp );
        $now            = new \DateTime();
        $now->sub( $interval );
        $date_limit     = $now->format( 'Ymd_His' );

        $path_obj       = new \FilesystemIterator( $destination );

        foreach ( $path_obj as $backup )
        {
            $filename = str_replace( '\\', '/', $backup->getFileName() );
            $pathname = str_replace( '\\', '/', $backup->getPathName() );

            if ( $filename <= $date_limit )
                unlink( $pathname );
        }
    }
     */

    /**
     * Retorna o nome do arquivo de log de backups.
    public static function dbBackupLog()
    {
        return DIR_PRODUCTS . '/executions/db-backup.log';
    }
     */
}
