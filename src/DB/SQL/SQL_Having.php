<?php

namespace JF\DB\SQL;

/**
 * Trait para informar as condições de agrupamento na consulta.
 */
trait SQL_Having
{
    /**
     * Condições de agrupamento da consulta.
     */
    protected $havings = [];

    /**
     * Adiciona uma nova condição de agrupamento na consulta.
     */
    public function having( $condition, $data = [] )
    {
        $this->havings[]    = (object) [
            'condition'     => $condition,
            'data'          => $data,
        ];

        return $this;
    }

    /**
     * Indica as condições da consulta.
     */
    public function getHaving()
    {
        if ( !$this->havings )
            return [null, []];

        $havings        = [];
        $data           = [];

        foreach ( $this->havings as $item )
        {
            $condition          = $item->condition;

            foreach ( $item->data as $name => $row )
            {
                $name           = substr( $name, 0, 1 ) != ':'
                    ? ':' . $name
                    : $name;
                $param          = $this->makeParam();
                $data[ $param ] = $row;
                $condition      = str_replace( $name, $param, $condition );
            }
            
            $havings[]          = $condition;
        }


        return [implode( ' AND ', $havings ), $data];
    }
}
