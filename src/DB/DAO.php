<?php

namespace JF\DB;

use JF\DB\SQL\SQL;

/**
 * Data Access Object - Classe para acesso aos dados de uma tabela.
 */
class DAO
{
    /**
     * Classe DTO de referência.
     */
    protected $dto;

    /**
     * Pesquisa simples por um registro na tabela.
     */
    public function __construct( $dto )
    {
        $this->dto = $dto;
    }

    /**
     * Pesquisa simples por um registro na tabela.
     */
    public function find( $value, $search = null, $opts = [] )
    {
        $dto            = $this->dto;
        $new_opts       = [
            'class'         => get_class( new $dto ),
            'class_start'   => 'start',
        ];
        $columns        = $dto::columns( $opts );
        $search         = $search ?? $dto::primaryKey();
        $operator       = is_array( $value )
            ? 'IN'
            : '=';
        $method         = is_array( $value )
            ? 'all'
            : 'one';

        $sql            = SQL::select( $columns )
            ->from( $dto::table() )
            ->where( $search, $operator, $value );

        if ( !is_array( $value ) )
        {
            $sql->limit( 1 );
        }

        $sql            = $sql->sql();
        $result         = DB::instance( $dto::schema() )
            ->execute( $sql->sql, $sql->data, $dto::isView() )
            ->$method( $dto::dbOptions() );

        return $result;
    }

    /**
     * Retorna o total de registros da tabela.
     */
    public function count( $opts = [] )
    {
        $dto    = $this->dto;
        $table  = $dto::table();
        $sql    = "SELECT COUNT(1) `total` FROM $table";
        $result = (object) DB::instance( $dto::schema() )
            ->execute( $sql, [], $dto::isView() )
            ->one();

        return $result->total;
    }

    /**
     * Pesquisa simples por um registro na tabela.
     */
    public function one( $opts = [] )
    {
        $dto            = $this->dto;
        $columns        = $opts[ 'columns' ] ?? null;
        $columns        = $dto::columns( $opts );
        $sql            = SQL::select( $columns )
            ->from( $dto::table() )
            ->limit( 1 )
            ->sql();
        $result         = DB::instance( $dto::schema() )
            ->execute( $sql->sql, $sql->data, $dto::isView() )
            ->one( $dto::dbOptions() );

        return $result;
    }

    /**
     * Pesquisa simples por um registro na tabela.
     */
    public function all( $opts = [] )
    {
        $dto            = $this->dto;
        $columns        = $opts[ 'columns' ] ?? null;
        $pk             = $dto::primaryKey();
        $columns        = $dto::columns( $opts );
        $sql            = SQL::select( $columns )
            ->from( $dto::table() )
            ->sql();
        $result         = DB::instance( $dto::schema() )
            ->execute( $sql->sql, $sql->data, $dto::isView() )
            ->indexBy( $pk )
            ->all( $dto::dbOptions() );

        return $result;
    }

    /**
     * Pesquisa simples por um registro na tabela.
     */
    public function insert( $values, $opts = [] )
    {
        $dto        = $this->dto;
        
        return SQL::insert( $dto )->values( (array) $values );
    }

    /**
     * Pesquisa simples por um registro na tabela.
     */
    public function multiInsert( $values, $opts = [] )
    {
        $dto        = $this->dto;
        
        return SQL::multiInsert( $dto )->values( (array) $values );
    }

    /**
     * Inicia uma consulta do tipo SELECT.
     */
    public function select( $columns = null, $opts = [] )
    {
        $dto            = $this->dto;
        
        return SQL::select( $columns, $dto );
    }

    /**
     * Inicia uma consulta do tipo UPDATE.
     */
    public function update( $value = null, $search = null, $values = [], $opts = [] )
    {
        $dto            = $this->dto;
        $sql            = SQL::update( $dto::table(), null, $dto );
        $search         = $search ?? $dto::primaryKey();

        if ( $value )
        {
            $operator   = is_array( $value )
                ? 'IN'
                : '=';
            $sql->where( $search, $operator, $value );
        }

        if ( $values )
            $sql->set( $values );
        
        return $sql;
    }

    /**
     * Inicia uma consulta do tipo DELETE.
     */
    public function delete( $value = null, $search = null, $opts = [] )
    {
        $dto            = $this->dto;
        $sql            = SQL::delete( $this->dto );
        $search         = $search ?? $dto::primaryKey();

        if ( $value )
        {
            $operator   = is_array( $value )
                ? 'IN'
                : '=';
            $sql->where( $search, $operator, $value );
        }
        
        return $sql;
    }

    /**
     * Limpa os dados da tabela.
     */
    public function truncate()
    {
        $dto    = $this->dto;
        $db     = $dto::db();

        return $db->truncate( $dto::table() );
    }
}
