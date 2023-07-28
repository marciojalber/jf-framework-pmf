<?php

namespace JF\DB;

use JF\Exceptions\ErrorException;

/**
 * Classe construtora de expressões SQL.
 */
class SQL
{
    /**
     * ActiveRecord da tabela alvo da SQL.
     */
    public $source;

    /**
     * Define parâmetros de retorno.
     */
    public $opts        = [
        'get_sql'       => false,
        'index_by'      => null,
    ];

    /**
     * Dados a serem inseridos / atualizados.
     */
    public $data        = [];

    /**
     * Campos a serem consultados.
     */
    public $select      = [];

    /**
     * Condições da consulta.
     */
    public $conditions  = [];

    /**
     * Limite de registros.
     */
    public $limit       = 0;

    /**
     * Salto inicial de registros.
     */
    public $offset      = 0;

    /**
     * Ordenação da consulta.
     */
    public $order       = [];

    /**
     * Agrupamento da consulta.
     */
    public $group       = [];

    /**
     * Condições de agrupamento.
     */
    public $having      = [];

    /**
     * Valores para atualizar um registro.
     */
    public $values      = [];

    /**
     * Armazena os parâmetros de paginação.
     */
    public $pagination  = null;


    /****************************************************************************************************
     *
     * Métodos de criação.
     *
     ****************************************************************************************************/
    
    /**
     * Cria uma nova instância do construtor de expressões.
     */
    public static function create( $source, array $opts = [] )
    {
        if ( !class_exists( $source ) )
        {
            throw new ErrorException( "A classe '$instance' não existe!" );
        }
        
        $jf_active_record   = 'JF\\DB\\ActiveRecord';

        if ( !is_subclass_of( $source, $jf_active_record) )
        {
            throw new ErrorException( "A classe '$source' não é um modelo de dados - não estende à classe '$jf_active_record'!" );
        }

        $instance           = new self();
        $instance->source   = $source;
        $instance->opts     = $opts;
        return $instance;
    }

    /**
     * Cria uma nova instância do construtor de expressões,
     * o nome da tabela já informado aproveitando
     */
    public function proto()
    {
        return static::create( $this->source );
    }


    /****************************************************************************************************
     *
     * DEFINE OS PARÂMETROS PARA CONSTRUAÇÃO DAS EXPRESSÕES SQL.
     *
     ****************************************************************************************************/

    /**
     * Define os campos a serem alterados.
     */
    public function set( $data )
    {
        $this->data = $data;
        return $this;
    }
    
    /**
     * Define os campos a serem consultados.
     */
    public function select( $columns = '*' )
    {
        // Define a seleção de todos os campos
        if ( !$columns || $columns === '*' )
        {
            $this->select = [];
            return $this;
        }

        // Define a seleção de um campo pré-formatado
        if ( is_string( $columns ) )
        {
            $this->select[] = $columns;
            return $this;
        }

        // Verifica o tipo de dado para os campos
        if ( !( is_array( $columns ) || is_object( $columns ) ) )
        {
            throw new Exception( 'Tipo de dado não aceito para informar campos da tabela!' );
        }

        // Define a seleção de vários campos
        foreach ( $columns as $alias => $column )
        {
            if ( is_string( $alias ) )
            {
                $this->select[ $alias ] = $column;
                continue;
            }

            $this->select[] = $column;
        }

        return $this;
    }


    /****************************************************************************************************
     *
     * MANIPULA AS CONDIÇÕES DA CONSULTA
     *
     ****************************************************************************************************/

    /**
     * Define a condição de uma coluna do registro ser igual a um valor.
     */
    public function is( $column, $value, $unsafe = false )
    {
        return $this->where( $column, '=', $value, $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro ser diferente de um valor.
     */
    public function notIs( $column, $value, $unsafe = false )
    {
        return $this->where( $column, '!=', $value, $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro ser menor que um valor.
     */
    public function less( $column, $value, $unsafe = false )
    {
        return $this->where( $column, '<', $value, $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro ser menor ou igual que um valor.
     */
    public function lessEqual( $column, $value, $unsafe = false )
    {
        return $this->where( $column, '<=', $value, $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro ser maior que um valor.
     */
    public function more( $column, $value, $unsafe = false )
    {
        return $this->where( $column, '>', $value, $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro ser maior ou igual que um valor.
     */
    public function moreEqual( $column, $value, $unsafe = false )
    {
        return $this->where( $column, '>=', $value, $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro ser semelhante a um valor.
     */
    public function like( $column, $value, $unsafe = false )
    {
        return $this->where(  $column, 'LIKE', $value, $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro NÃO ser semelhante a um valor.
     */
    public function notLike( $column, $value, $unsafe = false )
    {
        return $this->where( $column, 'NOT LIKE', $value, $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro ter valor definido em uma lista de valores.
     */
    public function in( $column, $values, $unsafe = false )
    {
        if ( !$values )
        {
            $msg = "Lista de valores vazia para consultar o campo \"$column\" do model \"{$this->source}\".";
            throw new ErrorException( $msg );
        }

        return $this->where( $column, 'IN', $values, $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro NÃO ter valor definido em uma lista de valores.
     */
    public function notIn( $column, $values, $unsafe = false )
    {
        if ( !$values )
        {
            $msg = "Lista de valores vazia para consultar o campo \"$column\" do model \"{$this->source}\".";
            throw new ErrorException( $msg );
        }

        return $this->where( $column, 'NOT IN', $values, $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro ter valor definido em um intervalo de valores.
     */
    public function between( $column, $value1, $value2, $unsafe = false )
    {
        return $this->where( $column, 'BETWEEN', [ $value1, $value2 ], $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro NÃO ter valor definido em um intervalo de valores.
     */
    public function notBetween( $column, $value1, $value2, $unsafe = false )
    {
        return $this->where( $column, 'NOT BETWEEN', [ $value1, $value2 ], $unsafe );
    }

    /**
     * Define a condição de uma coluna do registro ter valor nulo.
     */
    public function null( $column )
    {
        return $this->where( $column, 'IS NULL' );
    }

    /**
     * Define a condição de uma coluna do registro NÃO ter valor nulo.
     */
    public function notNull( $column )
    {
        return $this->where( $column, 'IS NOT NULL' );
    }

    /**
     * Define qualquer condição.
     */
    public function where( $column, $operator = null, $value = null, $unsafe = false )
    {
        $this->conditions[] = array( $column, $operator, $value, $unsafe );
        return $this;
    }

    /**
     * Insere um separador da sequência de condições AND com um OR.
     */
    public function _or_()
    {
        if ( !$this->conditions )
        {
            return $this;
        }
        
        $count_conds = count( $this->conditions );
        
        if ( $this->conditions[ $count_conds - 1 ] === 'or' )
        {
            return $this;
        }
        
        $this->conditions[] = 'or';
        return $this;
    }


    /****************************************************************************************************
     *
     * MANIPULA O AGRUPAMENTO E LIMITE DE DADOS
     *
     ****************************************************************************************************/
    
    /**
     * Define a orderação dos campos.
     */
    public function orderBy( $order )
    {
        // Verifica o tipo de dado para a ordenação
        if ( !is_array( $order ) && !is_object( $order ) )
        {
            $text = 'Tipo de dado não aceito para informar a ordenação da tabela!';
            throw new ErrorException( $text );
        }

        // Define a ordenação
        $this->order = $order;
        return $this;
    }

    /**
     * Define o agrupamento da consulta.
     */
    public function group( $group )
    {
        // Verifica o tipo de dado para agrupamento da consulta
        if ( !is_array( $group ) && !is_object( $group ) )
        {
            $text = 'Tipo de dado não aceito para informar o agrupamento da consulta!';
            throw new Exception( $text );
        }

        // Define a ordenação
        $this->group = $group;
        return $this;
    }

    /**
     * Define condições para o agrupamento da consulta.
     */
    public function having( $having )
    {
        // Verifica o tipo de dado para as condições de agrupamento da consulta
        if ( !is_array( $having ) && !is_object( $having ) )
        {
            $text = 'Tipo de dado não aceito para informar o agrupamento da consulta!';
            throw new Exception( $text );
        }

        // Define a ordenação
        $this->having = $having;
        return $this;
    }

    /**
     * Define o salto inicial de registros.
     */
    public function offset( $offset )
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Limita a quantidade de registros a serem retornados.
     */
    public function limit( $limit )
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Define a paginação simples dos resultados - baixa performance.
     */
    public function paginate( $page = 0, $pageRows = 20 )
    {
        $this->pagination = [
            'type'      => 'offset',
            'offset'    => $page * $pageRows,
            'limit'     => $pageRows,
        ];
        return $this;
    }

    /**
     * Define a paginação dos resultados por ID - alta performance.
     */
    public function paginateById( $last_id, $dir = 'next', $pageRows = 20 )
    {
        // Podemos utilizar outros ordenadores em col_ref além do ID,
        // tais como preço e categoria
        $this->pagination = [
            'type'      => 'by_id',
            'last_id'   => $last_id,
            'dir'       => $dir === 'prev' ? 'DESC' : 'ASC',
            'limit'     => $pageRows,
        ];
        return $this;
    }


    /****************************************************************************************************
     *
     * DEFINE OPÇÕES DE RETORNO DAS CONSULTAS
     *
     ****************************************************************************************************/

    /**
     * Define a coluna que deve indexar a coleção de dados retornados.
     */
    public function indexBy( $index = null )
    {
        $this->opts[ 'index_by' ] = $index;
        return $this;
    }

    /**
     * Define se as operações devem retornar a SQL preparada e os dados.
     */
    public function getSql( $get_sql = true )
    {
        $this->opts[ 'get_sql' ] = $get_sql;
        return $this;
    }


    /****************************************************************************************************
     *
     * RETORNA O RESULTADOS DAS CONSULTAS
     *
     ****************************************************************************************************/

    /**
     * Retorna os valores resultantes da última consulta.
     */
    public function values()
    {
        return array_shift( $this->values );
    }

    /**
     * Obtém a quantidade de registros.
     */
    public function count()
    {
        return SQLQuery::count( $this );
    }

    /**
     * Verifica se existe algum registro nas condições informadas.
     */
    public function exists()
    {
        return SQLQuery::exists( $this );
    }

    /**
     * Retorna todos os registros que coincidirem com a expressão SQL.
     */
    public function all( $get_values = false )
    {
        return SQLQuery::all( $this, $get_values );
    }

    /**
     * Retorna o primeiro registro que coincidir com a expressão SQL.
     */
    public function one( $get_values = false )
    {
        return SQLQuery::one( $this, $get_values );
    }

    /**
     * Retorna o maior registro apontado em determinada coluna.
     */
    public function max( $column = null, $get_values = false )
    {
        return SQLQuery::max( $this, $column, $get_values );
    }

    /**
     * Retorna o menor registro apontado em determinada coluna.
     */
    public function min( $column = null, $get_values = false )
    {
        return SQLQuery::min( $this, $column, $get_values );
    }

    /**
     * Insere um registro.
     * 
     * @return int | string
     */
    public function insert( array $data = [], $unsafe = false )
    {
        return SQLQuery::insert( $this, $data, $unsafe );
    }

    /**
     * Atualiza um registro.
     */
    public function update( $unsafe = false )
    {
        return SQLQuery::update( $this, $unsafe );
    }

    /**
     * Incrementa uma coluna de um registro.
     */
    public function increment( $column, $value = 1, $unsafe = false )
    {
        return SQLQuery::increment( $this, $column, $value, $unsafe );
    }

    /**
     * Decrementa uma coluna de um registro.
     */
    public function decrement( $column, $value = 1, $unsafe = false )
    {
        return SQLQuery::decrement( $this, $column, $value, $unsafe );
    }

    /**
     * Exclui um registro.
     */
    public function delete()
    {
        return SQLQuery::delete( $this );
    }

    /**
     * Cria um nome para um parâmetro de consulta.
     */
    public static function makeParam()
    {
        return str_replace( '.', '_', uniqid( ':param_', true ) );
    }
}
