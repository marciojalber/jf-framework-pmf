<?php

namespace JF\DB;

/**
 * Classe que retorna informações estruturais de um ActiveRecord.
 */
trait ActiveRecord_Infos
{
    /**
     * Retorna o nome do esquema de conexão com o banco de dados.
     */
    public static function schemaName()
    {
        if ( static::$schema )
        {
            return static::$schema;
        }

        $parts_record       = explode( '\\', get_called_class() );
        return strtolower( $parts_record[ 3 ] );
    }

    /**
     * Retorna o nome da tabela de origem do registro.
     */
    public static function dbName()
    {
        return static::db()->config( 'dbname' );
    }

    /**
     * Retorna o nome da tabela de origem do registro.
     */
    public static function tableName()
    {
        if ( static::$table )
        {
            return static::$table;
        }

        $source_class   = get_called_class();
        $source_parts   = explode( '\\', $source_class );
        $source_name    = strtolower( $source_parts[ 4 ] );
        $len_sufix      = strlen( self::SUFIX );
        $table_name     = substr( $source_name, 0, -$len_sufix );
        
        return $table_name;
    }

    /**
     * Retorna a chave primária da tabela.
     */
    public static function primaryKey()
    {
        return 'id';
    }

    /**
     * Retorna as informações de colunas do ActiveRecord.
     */
    public static function props( $column = null, $prop = null )
    {
        $columns    = static::$props;

        // Retorna todas as colunas da tabela
        if ( !$column )
        {
            return $columns;
        }

        // A propriedade não foi encontrada no ActiveRecord
        if ( !isset( $columns[ $column ] ) )
        {
            return null;
        }

        // Captura as definições de propriedade do ActiveRecord
        $props = $columns[ $column ];
        
        // Tenta retornar uma definição da propriedade do ActiveRecord
        if ( $prop )
        {
            return isset( $props[ $prop ] )
                ? $props[ $prop ]
                : null;
        }

        return $props;
    }

    /**
     * Retorna os relacionamentos da tabela.
     */
    public static function relations()
    {
        return (object) static::$relations;
    }

    /**
     * Retorna as colunas do ActiveRecord para consulta SQL.
     */
    public static function columns()
    {
        $props      = static::$props;
        $select     = [];
        $table_name = static::tableName();

        foreach ( $props as $column => $prop )
        {
            $column     = !empty( $prop[ 'column' ] )
                ? $prop[ 'column' ]
                : $column;
            $select[] = "`$table_name`.`$column`";
        }

        return $select;
    }
}
