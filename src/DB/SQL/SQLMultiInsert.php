<?php

namespace JF\DB\SQL;

use JF\DB\DB;

/**
 * Trait para executar a inserção de registro.
 */
class SQLMultiInsert extends SQLBuilder
{
    use SQL_MakeParam;

    use SQL_Into;
    use SQL_Values;

    /**
     * Método construtor.
     */
    public function __construct( $dto = null )
    {
        $this->dto = $dto;

        if ( $dto )
            $this->into( $dto::table() );
    }

    /**
     * Constrói a SQL.
     */
    public function sql()
    {
        $keys           = array_keys( current( $this->values ) );
        $columns        = [];

        foreach ( $keys as $key )
            $columns[]  = '`' . $key . '`';

        $data           = [];
        $values         = [];

        foreach ( $this->values as $row )
        {
            $values_tmp = [];

            foreach ( $keys as $key )
            {
                $param          = str_replace( '.', '_', uniqid( ':param_', true ) );
                $values_tmp[]   = $param;
                $data[ $param ] = $row[ $key ];
            }
            
            $values_tmp = implode( ', ', $values_tmp );
            $values[]   = "($values_tmp)";
        }

        $columns        = implode( ', ', $columns );
        $values         = implode( ', ' . N, $values );
        $sql            = "INSERT INTO `$this->table` ( $columns ) VALUES $values";

        return (object) [
            'action'    => 'insert',
            'sql'       => $sql,
            'data'      => $data,
        ];
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
