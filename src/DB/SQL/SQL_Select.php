<?php

namespace JF\DB\SQL;

/**
 * Trait para informar as colunas de retorno da consulta.
 */
trait SQL_Select
{
    /**
     * Armazena os campos da consulta.
     */
    protected $columns = [];

    /**
     * Informa as conlunas da consulta.
     */
    public function select( $columns )
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Informa para retornar campos protegidos / sensíveis.
     */
    public function unsafe( $unsafe = true )
    {
        $this->opts[ 'unsafe' ] = $unsafe;

        return $this;
    }

    /**
     * Informa as conlunas da consulta.
     */
    public function getColumns()
    {
        $response       = [];
        $columns        = $this->columns;
        
        if ( !$columns && $this->dto )
        {
            $dto        = $this->dto;
            $columns    = $dto::columns( $this->opts );
        }
        
        if ( !$columns )
            $columns    = '*';

        if ( is_string( $columns ) || is_numeric( $columns ) )
            return $columns;

        foreach ( $columns as $column )
        {
            if ( preg_match( '/[\(\)!=<>.`]/', $column ) )
            {
                $response[]  = $column;
                continue;
            }

            $response[]  = $this->alias
                ? '`' . $this->alias . '`.`' . $column . '`'
                : '`' . $column . '`';
        }

        $response    = implode( ', ', $response );
        
        return $response;
    }
}
