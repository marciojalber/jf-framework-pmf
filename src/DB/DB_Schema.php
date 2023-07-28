<?php

namespace JF\DB;

use JF\Config;

/**
 * Classe que representa um banco-de-dados.
 */
class DB_Schema
{
    /**
     * Retorna a lista de tabelas de um esquema de conexão.
     */
    public static function listTables( $schema_name = 'main', $env = 'all' )
    {
        $config_path    = "db/$env.schemas.$schema_name";
        $config         = Config::get( $config_path );

        if ( !$config )
        {
            return [];
        }

        $sql            = "
            SELECT  `t`.`TABLE_NAME` `table`
            FROM    `information_schema`.`TABLES` `t`
            WHERE   `t`.`TABLE_SCHEMA` = '{$config->dbname}'
        ";

        $tables         = DB::instance( $schema_name )
            ->execute( $sql )
            ->indexBy( 'table' )
            ->all();

        return array_keys( $tables );
    }

    /**
     * Retorna a descrição de uma tabela.
     */
    public static function descTable( $table_name, $schema_name = 'main', $env = 'all' )
    {
        $config_path    = "db/$env.schemas.$schema_name";
        $config         = Config::get( $config_path );

        if ( !$config )
        {
            return [];
        }

        $sql            = "
            SELECT  `t`.`TABLE_NAME`    `table`,
                    `t`.`TABLE_COMMENT` `coment`
            FROM    `information_schema`.`TABLES` `t`
            WHERE   `t`.`TABLE_SCHEMA`  = '{$config->dbname}' AND
                    `t`.`TABLE_NAME`    = '$table_name'
        ";

        $table          = DB::instance( $schema_name )
            ->execute( $sql )
            ->indexBy( 'table' )
            ->one();
        
        if ( !$table )
        {
            return null;
        }

        $sql            = "
            SELECT  `c`.`COLUMN_NAME`       `name`,
                    `c`.`COLUMN_TYPE`       `type`,
                    `c`.`COLUMN_DEFAULT`    `default`,
                    IF( `c`.`IS_NULLABLE` = 'YES', 'OK', '' )       `required`,
                    CASE `c`.`COLUMN_KEY`
                        WHEN 'PRI' THEN 'Primary Key'
                        WHEN 'UNI' THEN 'Unique'
                        WHEN 'MUL' THEN 'Multiple'
                    END `index`,
                    `c`.`COLUMN_COMMENT`    `comment`
            FROM    `information_schema`.`COLUMNS` `c`
            WHERE   `c`.`TABLE_SCHEMA`  = '{$config->dbname}' AND
                    `c`.`TABLE_NAME`    = '$table_name'
        ";

        $table          = (object) $table;
        $table->columns = DB::instance( $schema_name )
            ->execute( $sql )
            ->indexBy( 'name' )
            ->all();

        return $table;
    }
}
