<?php

namespace JF\FileSystem;

class CSV_Model
{
    /**
     * Armazena o separador de campos.
     */
    protected static $separator = ';';

    /**
     * Armazena o delimitador de campos.
     */
    protected static $enclosure = '"';

    /**
     * Retorna as colunas do template.
     */
    public static function separator()
    {
        return static::$separator;
    }

    /**
     * Retorna as colunas do template.
     */
    public static function enclosure()
    {
        return static::$enclosure;
    }

    /**
     * Retorna o valor de uma coluna do template.
     */
    public static function values( $data )
    {
        $columns                = static::$columns;
        $response               = [];
        
        foreach ( $columns as $key => $params )
        {
            $value              = static::getColumnValue( $params, $data );
            $value              = static::getChangedValue( $params, $value );
            $response[ $key ]   = $value;
        }
        
        return $response;
    }

    /**
     * Retorna o valor da coluna.
     */
    protected static function getColumnValue( $params, $data )
    {
        if ( !isset( $params[ 'column' ] ) )
        {
            return null;
        }

        $param_column   = $params[ 'column' ];
        $value          = isset( $data[ $param_column ] )
            ? $data[ $param_column ]
            : null;

        return $value;
    }

    /**
     * Retorna o valor da coluna modificado pelo método que for indicado.
     */
    protected static function getChangedValue( $params, $value )
    {
        if ( empty( $params[ 'method' ] ) )
        {
            return $value;
        }

        $param_method   = $params[ 'method' ];
        $value          = static::$param_method( $value );

        return $value;
    }
}
