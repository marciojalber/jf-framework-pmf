<?php

namespace JF\DB\SQL;

/**
 * Trait para informar o agrupamento na consulta.
 */
trait SQL_GroupBy
{
    /**
     * Agrupamentos da consulta.
     */
    protected $groups = [];

    /**
     * Adiciona um novo agrupamento na consulta.
     */
    public function groupBy( $column )
    {
        $this->groups[] = $column;

        return $this;
    }

    /**
     * Retorna os agrupamentos da consulta.
     */
    public function getGroupBy()
    {
        if ( !$this->groups )
            return '';

        $groups         = [];
        $alias          = $this->getAlias();

        foreach ( $this->groups as $group )
        {
            $groups[]  = strpos( $group, '.' ) === false
                ? '`' . $alias . '`.`' . $group . '`'
                : $group;
        }

        $groups         = implode( ', ', $groups );

        return $groups;
    }
}
