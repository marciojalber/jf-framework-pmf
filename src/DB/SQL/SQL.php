<?php

namespace JF\DB\SQL;

/**
 * Classe para montar consultas SQL.
 */
class SQL
{
    /**
     * Cria uma nova instância de uma SQL de inserção.
     */
    public static function insert( $dto = null )
    {
        return new SQLInsert( $dto );
    }

    /**
     * Cria uma nova instância de uma SQL de inserção múltipla.
     */
    public static function multiInsert( $dto = null )
    {
        return new SQLMultiInsert( $dto );
    }

    /**
     * Cria uma nova instância de uma SQL de atualização.
     */
    public static function update( $table, $alias = null, $dto = null )
    {
        return new SQLUpdate( $table, $alias, $dto );
    }

    /**
     * Cria uma nova instância de uma SQL de exclusão.
     */
    public static function delete( $dto = null )
    {
        return new SQLDelete( $dto );
    }

    /**
     * Cria uma nova instância de uma SQL de consulta.
     */
    public static function select( $columns = '*', $dto = null )
    {
        return new SQLSelect( $columns, $dto );
    }
}
