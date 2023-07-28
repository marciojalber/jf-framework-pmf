<?php

namespace JF\DB\SQL;

use JF\DB\DB;

/**
 * Classe para construir consultas SELECT.
 */
class SQLSelect extends SQLBuilder
{
    use SQL_MakeParam;

    use SQL_Select;
    use SQL_From;
    use SQL_Join;
    use SQL_Where;
    use SQL_GroupBy;
    use SQL_Having;
    use SQL_OrderBy;
    use SQL_Offset;
    use SQL_Limit;

    /**
     * Método construtor.
     */
    public function __construct( $columns = null, $dto = null )
    {
        $this->dto = $dto;
        $this->select( $columns );

        if ( $dto )
            $this->from( $dto::table() );
    }

    /**
     * Constrói a SQL.
     */
    public function indexBy( $column )
    {
        $this->opts[ 'index' ] = $column;

        return $this;
    }

    /**
     * Constrói a SQL.
     */
    public function sql( $opts = [] )
    {
        $columns                = $this->getColumns( $opts );
        $alias                  = $this->getAlias();
        $join                   = $this->getJoin();
        list( $where, $data1 )  = $this->getWhere();
        $group                  = $this->getGroupBy();
        list( $having, $data2 ) = $this->getHaving();
        $order                  = $this->getOrderBy();
        $offset                 = $this->getOffset();
        $limit                  = isset( $opts[ 'limit' ] )
            ? $opts[ 'limit' ]
            : $this->getLimit();
        $sql                    = $join
            ? "SELECT $columns FROM `$this->table` `$alias` $join WHERE $where"
            : "SELECT $columns FROM `$this->table` `$alias` WHERE $where";
        $data                   = $data1;

        if ( $group )
            $sql   .= ' GROUP BY ' . $group;

        if ( $having )
        {
            $data   = array_merge( $data1, $data2 );
            $sql   .= ' HAVING ' . $having;
        }

        if ( $order )
            $sql   .= ' ORDER BY ' . $order;

        if ( $limit )
            $sql   .= ' LIMIT ' . $limit;

        if ( $offset )
            $sql   .= ' OFFSET ' . $offset;

        return (object) [
            'action'    => 'select',
            'sql'       => $sql,
            'data'      => $data,
        ];
    }

    /**
     * Retorna o primeiro registro da operação.
     */
    public function one( $opts = [] )
    {
        $opts               = array_merge( $opts, $this->opts );
        $dto                = $this->dto;
        $opts[ 'limit' ]    = 1;
        $sql                = $this->sql( $opts );
        $result             = DB::instance( $dto::schema() )
            ->execute( $sql->sql, $sql->data, $dto::isView() )
            ->one( $dto::dbOptions( $opts ) );

        return $result;
    }

    /**
     * Retorna todos os registros da operação.
     */
    public function all( $opts = [] )
    {
        $opts   = array_merge( $opts, $this->opts );
        $dto    = $this->dto;
        $pk     = $dto::primaryKey();
        $sql    = $this->sql( $opts );
        $result = DB::instance( $dto::schema() )
            ->execute( $sql->sql, $sql->data, $dto::isView() )
            ->indexBy( $pk )
            ->all( $dto::dbOptions( $opts ) );

        return $result;
    }

    /**
     * Retorna todos os registros da operação.
     */
    public function count()
    {
        $this->select( 'COUNT(1) `total`' );

        $dto    = $this->dto;
        $sql    = $this->sql();
        $total  = DB::instance( $dto::schema() )
            ->execute( $sql->sql, $sql->data, $dto::isView() )
            ->one([ 'object' => true ])
            ->total;

        return $total;
    }

    /**
     * Retorna todos os registros da operação.
     */
    public function exists()
    {
        $dto    = $this->dto;
        
        $this->select( $dto::primaryKey() );
        
        $sql    = $this->sql();
        $exists = (bool) DB::instance( $dto::schema() )
            ->execute( $sql->sql, $sql->data )
            ->one();

        return $exists;
    }
}
