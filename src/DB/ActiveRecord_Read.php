<?php

namespace JF\DB;

/**
 * Classe que representa um registro no banco-de-dados.
 */
trait ActiveRecord_Read
{
    /**
     * Retorna a quantidade de registros da tabela.
     */
    public static function count( array $opts = [] )
    {
        return static::prepare( $opts )->count();
    }

    /**
     * Retorna todos os registros da tabela.
     */
    public static function all( $get_values = false, array $opts = [] )
    {
        // Captura o registro do banco
        $columns    = static::columns();
        $result     = static::prepare( $opts )
            ->select( $columns )
            ->all( $get_values );

        return $result;
    }

    /**
     * Pesquisa um registro pelo 'id'.
     */
    public static function find( $id, $get_values = false, array $opts = [] )
    {
        $id_name    = static::primaryKey();
        $columns    = static::columns();
        $result     = static::prepare( $opts )
            ->select( $columns )
            ->is( $id_name, $id )
            ->one( $get_values );

        return $result;
    }

    /**
     * Recarrega o registro.
     */
    public function refresh()
    {
        $id_name    = static::primaryKey();
        $id         = $this->$id_name;
        
        return static::find( $id );
    }

    /**
     * Retorna o primeiro registro da tabela.
     */
    public static function one( $get_values = false, array $opts = [] )
    {
        // Captura o registro do banco
        $columns    = static::columns();
        $result     = static::prepare( $opts )
            ->select( $columns )
            ->one( $get_values );
            
        return $result;
    }

    /**
     * Retorna o registro de maior valor em determinada coluna.
     */
    public static function max( $column = null, $get_values = false, array $opts = [] )
    {
        // Captura o registro do banco
        $columns    = static::columns();
        $result     = static::prepare( $opts )
            ->select( $columns )
            ->max( $column, $get_values );

        return $result;
    }

    /**
     * Retorna o registro de menor valor em determinada coluna.
     */
    public static function min( $column = null, $get_values = false, array $opts = [] )
    {
        // Captura o registro do banco
        $columns    = static::columns();
        $result     = static::prepare( $opts )
            ->select( $columns )
            ->min( $column, $get_values );
        return $result;
    }
    
    /**
     * Retorna os registros filtros.
     */
    protected function relatedMany( $column, $source, $index_by )
    {
        $this_schema    = static::schemaName();
        $source_class   = str_replace( '.', '\\', $source );
        $source_class   = self::PREFIX . $source_class . self::SUFIX;
        $search_props   = $source_class::props();
        $fk_search      = null;

        foreach ( $search_props as $key => $prop )
        {
            if ( empty( $prop[ 'type' ] ) || $prop[ 'type' ] !== 'reference' )
            {
                continue;
            }

            $source                 = $this_schema . '\\' . static::tableName();
            $search_reference       = strtolower( str_replace( '.', '\\', $source ) );
            
            if ( !strpos( $search_reference, '\\' ) )
            {
                $search_reference   = $this_schema . '\\' . $search_reference;
            }
            
            if ( $search_reference === $source )
            {
                $fk_search = $key;
            }

            break;
        }

        if ( !$fk_search )
        {
            return null;
        }

        $columns        = $source_class::columns();
        $this_id        = $this->get( static::primaryKey() );
        $records        = $source_class::prepare()
            ->select( $columns )
            ->is( $fk_search, $this_id );

        if ( $index_by )
        {
            $records->indexBy( $index_by );
        }
        
        return $records->all();
    }
}
