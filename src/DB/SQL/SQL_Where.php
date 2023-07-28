<?php

namespace JF\DB\SQL;

/**
 * Trait para informar as condições da consulta.
 */
trait SQL_Where
{
    /**
     * Armazena as condições da consulta.
     */
    protected $where    = [];

    /**
     * Armazena as condições da consulta.
     */
    protected $data     = [];

    /**
     * Indica as condições da consulta.
     */
    public function where( $condition, $operator = null, $data = [] )
    {
        $this->where[]  = (object) [
            'condition' => $condition,
            'operator'  => $operator,
            'data'      => $data,
            'not'       => false,
        ];

        return $this;
    }

    /**
     * Indica as condições da consulta.
     */
    public function whereNot( $condition, $operator = null, $data = [] )
    {
        $this->where[]  = (object) [
            'condition' => $condition,
            'operator'  => $operator,
            'data'      => $data,
            'not'       => true,
        ];

        return $this;
    }

    /**
     * Indica as condições da consulta.
     */
    public function getWhere()
    {
        if ( !$this->where )
            return [1, []];

        $where  = [];
        $data   = [];

        foreach ( $this->where as $item )
        {
            $method = !preg_match( '/[\(\)!=<>,]/', $item->condition )
                ? 'parseSimpleCondition'
                : 'parseFunctionalCondition';

            $this->$method( $item, $where, $data );
        }

        return [implode( ' AND ', $where ), $data];
    }

    /**
     * Indica as condições da consulta.
     */
    public function parseSimpleCondition( $item, &$where, &$data )
    {
        $item->operator = strtoupper( $item->operator );
        $condition      = $this->alias && strpos( $item->condition, '.' ) === false
            ? '`' . $this->getAlias() . '`.`' . $item->condition . '`'
            : '`' . $item->condition . '`';
        $condition     .= ' ' . $item->operator;

        switch ( $item->operator )
        {
            // Sem dados
            case 'IS NULL':
            case 'IS NOT NULL':
                $where[]            = $item->not
                    ? 'NOT ( ' . $condition . ' )'
                    : $condition;
                break;
            
            // 2 dados
            case 'NOT BETWEEN':
            case 'BETWEEN':
                $param1             = $this->makeParam();
                $param2             = $this->makeParam();
                $data[ $param1 ]    = $item->data[ 0 ];
                $data[ $param2 ]    = $item->data[ 1 ];
                $where[]            = $item->not
                    ? "NOT( $condition $param1 AND $param2 )"
                    : "( $condition $param1 AND $param2 )";
                break;
            
            // Múltiplos dados
            case 'IN':
            case 'NOT IN':
                $params             = [];
                
                foreach ( $item->data as $row )
                {
                    $param          = $this->makeParam();
                    $data[ $param ] = $row;
                    $params[]       = $param;
                }
                
                $params             = implode( ', ', $params );
                $where[]            = $item->not
                    ? 'NOT( ' . $condition . "( $params ) )"
                    : $condition . "( $params )";
                break;

            default:
                $param              = $this->makeParam();
                $data[ $param ]     = $item->data;
                $where[]            = $item->not
                    ? "NOT( $condition $param )"
                    : $condition . ' ' . $param;
                break;
        }
    }

    /**
     * Indica as condições da consulta.
     */
    public function parseFunctionalCondition( $item, &$where, &$data )
    {
        $condition          = $item->condition;

        foreach ( $item->data as $name => $row )
        {
            $name           = substr( $name, 0, 1 ) != ':'
                ? ':' . $name
                : $name;
            $param          = $this->makeParam();
            $data[ $param ] = $row;
            $condition      = $item->not
                ? 'NOT( ' . str_replace( $name, $param, $condition ) . ' )'
                : str_replace( $name, $param, $condition );
        }
        
        $where[]            = $condition;
    }
}
