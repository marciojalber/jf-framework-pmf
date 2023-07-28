<?php

namespace JF\DB;

use JF\Config;
use JF\Exceptions\ErrorException;
use JF\Messager;

/**
 * Classe que representa um banco-de-dados.
 */
class DB_Migrator
{
    const MAX_ROWS_PER_SQL = 5000;

    /**
     * Prepara uma SQL de inserção.
     */
    public static function migrate(
        $sql_read,
        $schema_source,
        $schema_target,
        $table_target
    ) {
        // Captura os dados do Sisref
        $sqls               = array();
        $active_row         = 0;
        $max_rows_insert    = 5000;
        $pdo_source         = DB::instance( $schema_source )->pdo();
        $pdo_target         = DB::instance( $schema_target )->pdo();
        $stmt               = $pdo_source->prepare( $sql_read );
        $success            = $stmt->execute();

        if ( !$success )
        {
            $msg    = Messager::get( 'db', 'migration_unexecuted', $schema_source );;
            throw new ErrorException( $msg );
        }
        
        // $pdo_target->setAttribute( \PDO::ATTR_EMULATE_PREPARES, true );

        while ( $row = $stmt->fetchObject() )
        {
            
            // Prepara os dados
            $sql            = self::makeInsert( $table_target, $row, !$active_row );

            $sqls[]         = $sql;
        
            // Incrementa uma linha lida
            ++$active_row;
            
            // Grava os dados no arquivo e reinicia a contagem
            if ( $active_row === self::MAX_ROWS_PER_SQL )
                {
                $active_row = 0;

                $sql        = implode( ",\n", $sqls ) . ';';
                $pdo_target->query( $sql );
                $sqls       = array();
            }
        }
        
        if ( $sqls )
        {
            $sql            = implode( ",\n", $sqls ) . ';';
            $pdo_target->query( $sql );
        }
        
        // $pdo_target->setAttribute( \PDO::ATTR_EMULATE_PREPARES, false );
    }    
    
    /**
     * Prepara uma SQL de inserção.
     */
    public static function makeInsert( $table, $data, $include_operation = false )
    {
        return self::makeInsertReplace( $table, $data, $include_operation );
    }
    
    /**
     * Prepara uma SQL de substituição.
     */
    public static function makeReplace( $table, $data, $include_operation = false )
    {
        return self::makeInsertReplace( $table, $data, $include_operation, true );
    }
    
    /**
     * Prepara uma SQL de substituição.
     */
    private static function makeInsertReplace(
        $table,
        $data,
        $include_operation = false,
        $replace = false
    ) {
        $sql_insert = array();
        $values     = array();

        foreach ( $data as $key => $value )
        {
            $columns[]  = '`' . $key . '`';
            $values[]   = is_null( $value )
                ? 'NULL'
                : '"' . addslashes( $value ) . '"';
        }

        $sql_values = '( ' . implode( ', ', $values ) . ')';

        if ( !$include_operation )
            return $sql_values;

        $columns    = implode( ', ', $columns );
        $operation  = $replace
            ? 'REPLACE'
            : 'INSERT';
        $sql_insert = "{$operation} INTO `{$table}` ( {$columns} ) VALUES ";

        return $sql_insert . $sql_values;
    }
}
