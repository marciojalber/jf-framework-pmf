<?php

namespace JF\DB;

/**
 * Classe que representa uma coleção de registros capturados do banco de dados.
 */
class Collection
{
    /**
     * Armazena os registros capturados.
     */
    protected $records;

    /**
     * Armazena os ids dos registros filtrados.
     */
    protected $hidden = [];

    /**
     * Método construtor.
     */
    public function __construct( $records = array() )
    {
        $this->records = $records;
    }


    /****************************************************************************************************
     *
     * MÉTODOS DE MANIPULAÇÃO DE REGISTROS
     *
     ****************************************************************************************************/

    /**
     * Aplica um método definido no ActiveRecord para todos os registros.
     */
    public function apply( $method )
    {
        // Captura os argumentos e desconsidera o argumento $method
        $args   = func_get_args();
        array_shift( $args );

        // Aplica o método nos registros
        foreach ( $this->records as $record )
        {
            call_user_func_array( [$record, $method], $args );
        }

        return $this;
    }


    /****************************************************************************************************
     *
     * MÉTODOS DE CONSULTA DE VALORES
     *
     ****************************************************************************************************/

    /**
     * Retorna os registros.
     */
    public function records()
    {
        return $this->records;
    }

    /**
     * Retorna o primeiro registro da coleção.
     */
    public function one()
    {
        return current( $this->records );
    }

    /**
     * Retorna os valores dos registros.
     */
    public function values( $unsafe = false, array $columns = array() )
    {
        $values = [];

        foreach ( $this->records as $id => $record )
        {
            if ( !in_array( $id, $this->hidden ) )
            {
                $values[ $id ] = $record->values( $unsafe );
            }
        }

        return $values;
    }

    /**
     * Retorna os valores dos registros.
     */
    public function get( $column, $get_values = false, $index_by = null )
    {
        $values = array();
        
        foreach ( $this->records as $id => $record )
        {
            $value = $record->get( $column, $get_values, $index_by );
            
            if ( !in_array( $id, $this->hidden ) )
            {
                $values[ $id ] = $value;
            }
        }
        
        return $values;
    }

    /**
     * Retorna a quantidade de registros da coleção.
     */
    public function count( $total = false )
    {
        return count( $this->records ) - ( $total ? 0 : count( $this->hidden ) );
    }

    /**
     * Retorna se tem um registro com o ID informado.
     */
    public function has( $id )
    {
        $keys = array_keys( $this->records );
        return array_search( $id, $keys ) !== false;
    }

    /**
     * Aplica filtros nos registros a serem exibidos.
     */
    public function filter( $filters )
    {
        // Se o filtro é uma função ou método
        if ( is_callable( $filters ) )
        {
            $records = $this->records;
            foreach ( $records as $id => $record )
            {
                if ( in_array( $id, $this->hidden ) )
                {
                    continue;
                }
                
                if ( $filters( $record ) )
                {
                    $this->hidden[] = $id;
                }
            }
            return $this;
        }

        // Converte o filtro plano em um array de filtros 
        if ( is_scalar( $filters ) )
        {
            $filters = [$filters];
        }

        // Se NÃO informou um array / objeto não aplica o filtro
        if ( !is_array( $filters ) && !is_object( $filters ) )
        {
            return $this;
        }

        // Aplica o filtro para cada um dos índices informados
        foreach ( $filters as $filter )
        {
            if ( !is_scalar( $filter ) )
            {
                continue;
            }
            if ( array_key_exists( $filter, $this->records ) && !in_array( $filter, $this->hidden ) )
            {
                $this->hidden[] = $filter;
                return $this;
            }
        }
    }

    /**
     * Limpa os filtros dos registros.
     */
    public function reset()
    {
        $this->hidden = array();
        return $this;
    }


    /****************************************************************************************************
     *
     * MÉTODOS DE PERSISTÊNCIA DO REGISTRO INSTANCIADO
     *
     ****************************************************************************************************/
    
    /**
     * Salva os registros no banco.
     */
    public function update()
    {
        $result = array();
        
        foreach ( $this->records as $id => $record )
        {
            $result[ $id ] = $record->update();
        }
        
        return $result;
    }
    
    /**
     * Exclui os registros do banco.
     */
    public function delete()
    {
        $result = array();
        
        foreach ( $this->records as $id => $record )
        {
            $result[ $id ] = $record->delete();
        }
        
        return $result;
    }


    /****************************************************************************************************
     *
     * MÉTODOS DE ITERAÇÃO COM A COLEÇÃO
     *
     ****************************************************************************************************/

    /**
     * Formata a exibição a inspeção do registro.
     */
    public function __debugInfo()
    {
        $records = $this->records;
        
        foreach ( $this->hidden as $id )
        {
            $records[ $id ] = '(FILTERED)';
        }
        
        return [
            'total'     => count( $this->records ),
            'hidden'    => implode( ', ', $this->hidden ),
            'records'   => $records,
        ];
    }

}
