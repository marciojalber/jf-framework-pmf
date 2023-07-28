<?php

namespace JF\DB\SQL;

use JF\DB\DB;

/**
 * Trait para executar uma atualização na tabela.
 */
class SQLUpdate extends SQLBuilder
{
    use SQL_MakeParam;

    use SQL_Join;
    use SQL_Set;
    use SQL_Where;
    use SQL_Offset;
    use SQL_Limit;

    /**
     * Armazena o nome da tabela.
     */
    protected $table = null;

    /**
     * Armazena o alias da tabela.
     */
    protected $alias = null;

    /**
     * Inicia a SQL.
     */
    public function __construct( $table, $alias = null, $dto = null )
    {
        $this->dto      = $dto;
        $this->table    = $table;
        $this->alias    = $alias;
    }

    /**
     * Constrói a SQL.
     */
    public function sql()
    {
        $values                 = [];
        $alias                  = $this->getAlias();
        $data1                  = [];

        foreach ( $this->values as $key => $value )
        {
            $param              = $this->makeParam();
            $values[]           = "`$alias`.`$key` = $param";
            $data1[ $param ]    = $value;
        }

        $values                 = implode( ', ', $values );
        $join                   = $this->getJoin();
        list( $where, $data2 )  = $this->getWhere();
        $offset                 = $this->getOffset();
        $limit                  = $this->getLimit();
        $sql                    = "UPDATE `$this->table` `$alias` SET $values WHERE $where";

        if ( $offset )
            $sql   .= ' OFFSET ' . $offset;

        if ( $limit )
            $sql   .= ' LIMIT ' . $limit;

        return (object) [
            'action'    => 'update',
            'sql'       => $sql,
            'data'      => array_merge( $data1, $data2 ),
        ];
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

    /**
     * Retorna o total de registros da operação.
     */
    public function count()
    {
        $dto    = $this->dto;
        $sql    = $this->sql();
        $total  = DB::instance( $dto::schema() )
            ->execute( $sql->sql, $sql->data )
            ->count();

        return $total;
    }
}
