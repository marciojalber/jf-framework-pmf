<?php

namespace JF\DB\SQL;

/**
 * Trait para informar a tabela de origem da consulta.
 */
trait SQL_From
{
    /**
     * Armazena a tabela de origem da consulta.
     */
    protected $table = null;

    /**
     * Armazena o alias da tabela.
     */
    protected $alias = null;

    /**
     * Define a tabela de origem e o alias.
     */
    public function from( $table, $alias = null )
    {
        $this->table = $table;
        $this->alias = $alias;

        return $this;
    }

    /**
     * Define a tabela de origem e o alias.
     */
    public function alias( $alias = null )
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Retorna o alias da tabela.
     */
    public function getAlias()
    {
        return $this->alias
            ? $this->alias
            : $this->table;
    }
}
