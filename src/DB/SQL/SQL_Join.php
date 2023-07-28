<?php

namespace JF\DB\SQL;

/**
 * Trait para informar as junções de tabela na consulta.
 */
trait SQL_Join
{
    /**
     * Junções da consulta.
     */
    protected $joins = [];

    /**
     * Adiciona uma junção.
     */
    public function join( $table, $alias, $conditions, $qualifier = '' )
    {
        $this->joins[] = (object) [
            'table'         => $table,
            'alias'         => $alias,
            'conditions'    => $conditions,
            'qualifier'     => $qualifier,
        ];

        return $this;
    }

    /**
     * Retorna as junções da consulta.
     */
    public function getJoin()
    {
        if ( !$this->joins )
            return '';

        $joins  = [];

        foreach ( $this->joins as $join )
        {
            $join_text  = $join->qualifier
                ? $join->qualifier . " JOIN `$join->table` `$join->alias`" 
                : "JOIN `$join->table` `$join->alias`";
            $join_text .= ' ON ' . $join->qualifier . ' ' . $join->conditions;
            $joins[]    = $join_text;
        }

        $joins  = implode( ' ', $joins );

        return $joins;
    }
}
