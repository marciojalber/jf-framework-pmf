<?php

namespace JF\DB\SQL;

/**
 * Trait para informar as ordenações da consulta.
 */
trait SQL_OrderBy
{
    /**
     * Ordenações da consulta.
     */
    protected $orders = [];

    /**
     * Adiciona uma nova ordenação na consulta.
     */
    public function orderBy( $column, $direction = 'ASC' )
    {
        if ( $direction == 1 )
            $direction = 'ASC';

        if ( $direction == -1 )
            $direction = 'DESC';
        
        $this->orders[] = (object) [
            'order'     => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Retorna os agrupamentos da consulta.
     */
    public function getOrderBy()
    {
        if ( !$this->orders )
            return '';

        $orders         = [];
        $alias          = $this->getAlias();

        foreach ( $this->orders as $order )
        {
            $orders[]  = strpos( $order->order, '.' ) === false
                ? $alias . '.`' . $order->order . '` ' . $order->direction
                : $order->order . ' ' . $order->direction;
        }

        $orders         = implode( ', ', $orders );

        return $orders;
    }
}
