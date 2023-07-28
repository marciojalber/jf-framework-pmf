<?php

namespace JF\DB\SQL;

use JF\DB\DB;
use JF\Exceptions\ErrorException as Error;

/**
 * Trait para executar a inserção de registro.
 */
class SQLInsert extends SQLBuilder
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
        $keys       = array_keys( $this->values );
        $columns    = [];
        $data       = [];
        $values     = [];

        foreach ( $keys as $key )
        {
            $param          = ':' . $key;
            $columns[]      = '`' . $key . '`';
            $values[]       = $param;
            $data[ $param ] = $this->values[ $key ];
        }

        $columns    = implode( ', ', $columns );
        $values     = implode( ', ', $values );
        $sql        = "INSERT INTO `$this->table` ( $columns ) VALUES ( $values )";

        return (object) [
            'action'    => 'insert',
            'sql'       => $sql,
            'data'      => $data,
        ];
    }

    /**
     * Retorna todos os registros da operação.
     */
    public function id()
    {
        $dto    = $this->dto;
        $pk     = $dto::primaryKey();
        $sql    = $this->sql();
        $data   = $sql->data;

        foreach ( $data as $item )
            if ( !is_null( $item ) && !is_scalar( $item ) )
                throw new Error( "Não é possível executar INSERT informando dado não scalar." );

        $db     = DB::instance( $dto::schema() )->execute( $sql->sql, $data );
        $count  = $db->count();

        if ( !$count )
            return null;

        $id     = $db->insertId();
        
        return $id
            ? $id
            : $this->values[ $pk ];
    }

    /**
     * Retorna todos os registros da operação.
     */
    public function one( $opts = [] )
    {
        $dto    = $this->dto;
        $id     = $this->id();

        if ( !$id )
            return null;

        return $dto::dao()->find( $id, null, $opts );
    }
}
