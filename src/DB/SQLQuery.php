<?php

namespace JF\DB;

use JF\Exceptions\ErrorException;
use JF\Messager;

/**
 * Classe que executa uma SQL e retorna instâncias da classe ActiveRecord,
 * um objeto ou um array de objetos.
 */
class SQLQuery
{
    /**
     * Retorna todos os registros que coincidirem com a expressão SQL.
     * 
     * @return Collection | array
     */
    public static function all( SQL $sql, $get_values = false )
    {
        $source         = $sql->source;
        $sql_data       = SQLBuilder::build( $sql, 'all' );
        
        if ( !empty( $sql->opts[ 'get_sql' ] ) )
        {
            return $sql_data;
        }

        $multi_values   = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->all();

        // Se NÃO encontrou o registro retorna vazio
        if ( !$multi_values )
        {
            return array();
        }

        $sql->values    = $multi_values;
        $count          = count( $multi_values );
        $records        = array();
        $id_name        = !empty( $sql->opts[ 'index_by' ] )
            ? $sql->opts[ 'index_by' ]
            : $source::primaryKey();

        // Instancia os registros
        foreach ( $multi_values as $values )
        {
            if ( !array_key_exists( $id_name, $values ) )
            {
                $msg = Messager::get( 'db', 'key_not_exists', $id_name, $sql->source );
                throw new ErrorException( $msg );
            }

            $id             = $values[ $id_name ];
            $records[ $id ] = $source::init( $sql );
        }

        // Instancia uma coleção com os registros instanciados
        $collection = new Collection( $records );
        
        if ( $get_values )
        {
            return $collection->values();
        }
        
        return $collection;
    }

    /**
     * Retorna o primeiro registro que coincidir com a expressão SQL.
     */
    public static function one( SQL $sql, $get_values = false )
    {
        $sql_data   = SQLBuilder::build( $sql, 'one' );
        
        if ( !empty( $sql->opts[ 'get_sql' ] ) )
        {
            return $sql_data;
        }

        $source     = $sql->source;
        $values     = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->one();

        // Se não encontrou o registro retorna vazio
        if ( !$values )
            return null;

        $sql->values = [ $values ];

        // Instancia o registro e o retorna
        $record = $source::init( $sql );

        if ( $get_values )
        {
            return $record->values();
        }

        return $record;
    }

    /**
     * Retorna o maior registro apontado em determinada coluna.
     */
    public static function max( SQL $sql, $column = null )
    {
        $source      = $sql->source;

        // Define a coluna como ID se nenhuma for informada
        if ( !$column )
        {
            $column = $source::primaryKey();
        }
        
        $table          = $source::tableName();
        $sql->orderBy( [$column => -1] );
        $sql_data       = SQLBuilder::build( $sql, 'one' );

        if ( !empty( $sql->opts[ 'get_sql' ] ) )
        {
            return $sql_data;
        }

        $result         = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->one();

        if ( $result )
        {
            return $result[ $column ];
        }
    }

    /**
     * Retorna o menor registro apontado em determinada coluna.
     */
    public static function min( SQL $sql, $column = null )
    {
        $source         = $sql->source;

        // Define a coluna como ID se nenhuma for informada
        if ( !$column )
        {
            $column     = $source::primaryKey();
        }
        
        $table          = $source::tableName();
        $sql->orderBy( [$column => 1] );
        $sql_data       = SQLBuilder::build( $sql, 'one' );
        
        if ( !empty( $sql->opts[ 'get_sql' ] ) )
        {
            return $sql_data;
        }

        $result         = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->one();

        if ( $result )
        {
            return $result[ $column ];
        }
    }

    /**
     * Obtém a quantidade de registros.
     */
    public static function count( SQL $sql )
    {
        $source     = $sql->source;
        $sql_data   = SQLBuilder::build( $sql, 'count' );
        if ( !empty( $sql->opts[ 'get_sql' ] ) )
        {
            return $sql_data;
        }

        $result     = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->one();
        $total      = $result[ 'total' ];

        return $total;
    }

    /**
     * Verifica se existe algum registro nas condições informadas.
     */
    public static function exists( SQL $sql )
    {
        $source     = $sql->source;
        $sql_data   = SQLBuilder::build( $sql, 'exists' );
        
        if ( !empty( $sql->opts[ 'get_sql' ] ) )
        {
            return $sql_data;
        }

        $result     = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->one();

        return (bool) $result;
    }

    /**
     * Insere um registro.
     * 
     * @return int | string
     */
    public static function insert( SQL $sql, $data = array(), $unsafe = false )
    {
        if ( $data )
            $sql->set( $data );
        
        $source     = $sql->source;
        $sql_data   = SQLBuilder::build( $sql, 'insert', $unsafe );

        if ( !empty( $sql->opts[ 'get_sql' ] ) )
            return $sql_data;

        $id_record  = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->insertId();

        // Se inseriu o registro já retorna o objeto instanciado
        return $id_record;
    }

    /**
     * Atualiza um registro.
     */
    public static function update( SQL $sql, $unsafe = false )
    {
        $source     = $sql->source;
        $sql_data   = SQLBuilder::build( $sql, 'update', $unsafe );
        
        if ( !empty( $sql->opts[ 'get_sql' ] ) )
        {
            return $sql_data;
        }

        $count      = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->count();

        return $count;
    }

    /**
     * Incrementa uma coluna de um registro.
     */
    public static function increment( SQL $sql, $column, $value = 1, $unsafe = false )
    {
        $source      = $sql->source;
        $sql->set( [$column => $value] );
        $sql_data   = SQLBuilder::build( $sql, 'increment', $unsafe );
        
        if ( !empty( $sql->opts[ 'get_sql' ] ) )
        {
            return $sql_data;
        }

        $count      = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->count();

        return $count;
    }

    /**
     * Decrementa uma coluna de um registro.
     */
    public static function decrement( SQL $sql, $column, $value = 1, $unsafe = false )
    {
        $source     = $sql->source;
        $sql->set([ $column => -$value ]);
        $sql_data   = SQLBuilder::build( $sql, 'decrement', $unsafe );
        
        if ( !empty( $sql->opts[ 'get_sql' ] ) )
            return $sql_data;

        $count      = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->count();

        return $count;
    }

    /**
     * Exclui um registro.
     */
    public static function delete( SQL $sql )
    {
        $source     = $sql->source;
        $sql_data   = SQLBuilder::build( $sql, 'delete' );

        if ( !empty( $sql->opts[ 'get_sql' ] ) )
        {
            return $sql_data;
        }

        $count      = DB::instance( $source::schemaName() )
            ->execute( $sql_data[ 0 ], $sql_data[ 1 ] )
            ->count();

        return $count;
    }
}
