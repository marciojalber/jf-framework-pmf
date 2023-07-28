<?php

namespace JF\DB;

use JF\Exceptions\ErrorException;
use JF\Messager;

/**
 * Classe que monta uma SQL.
 */
class SQLBuilder
{
    /**
     * Armazena a instância do objeto SQL.
     */
    protected $sql      = null;
    
    /**
     * Armazena o tipo de consulta solicitada.
     */
    protected $type     = null;
    
    /**
     * Indica se a consulta será realizada no modo inseguro.
     */
    protected $unsafe   = false;
    
    /**
     * Armazena as colunas da consulta.
     */
    protected $columns  = null;
    
    /**
     * Armazena os dados para atualizar o registro.
     */
    protected $sets     = null;
    
    /**
     * Armazena as condições da consulta.
     */
    protected $where    = null;
    
    /**
     * Armazena o agrupamento de dados da consulta.
     */
    protected $group    = null;
    
    /**
     * Armazena as condições do agrupamento de dados da consulta.
     */
    protected $having   = null;
    
    /**
     * Armazena o ordenamento dos dados da consulta.
     */
    protected $order    = null;
    
    /**
     * Armazena o limite de registros da consulta.
     */
    protected $limit    = null;
    
    /**
     * Armazena o salto inicial de registros da consulta.
     */
    protected $offset   = null;
    
    /**
     * Indica se a consulta será insegura.
     */
    protected $reorder = false;
    
    /**
     * Indica se a consulta será insegura.
     */
    protected $values;

    /**
     * Desabilita o método construtor.
     */
    protected function __construct( SQL $sql, $type, $unsafe )
    {
        // Checa se o ActiveRecord foi informado
        if ( !$sql->source )
        {
            $msg = Messager::get( 'db', 'model_not_informed' );
            throw new ErrorException( $msg );
        }

        // Define as propriedades do Builder
        $this->sql      = $sql;
        $this->type     = $type;
        $this->unsafe   = $unsafe;
        $this->reorder  = $sql->pagination && $sql->pagination[ 'by_id' ];
    }

    /**
     * Monta a SQL solicitada.
     * 
     * @return array
     */
    public static function build( SQL $sql, $type, $unsafe = null )
    {
        // Instancia a própria classe
        $builder            = new static( $sql, $type, $unsafe );
        
        // Se a operação for de inserção
        if ( $type === 'insert' )
        {
            return $builder->getInsertSqlAndValues();
        }

        // Captura as condições da consulta
        $builder->setColumns();
        $builder->setGroup();
        $builder->setHaving();
        $builder->setOrder();
        $builder->setLimit();
        $builder->setOffset();
        $builder->setConditions();

        $sql        = $builder->getSqlAndValues();
        $responde   = array( $sql, $builder->values );
        
        return $responde;
    }

    /**
     * Obtém as colunas da consulta.
     * 
     * @return string
     */
    protected function getInsertSqlAndValues()
    {
        $columns                = array();
        $binds                  = array();
        $values                 = array();
        $source                 = $this->sql->source;
        $table                  = $source::tableName();
        $data                   = $this->sql->data;

        if ( $this->unsafe )
        {
            $data               = $this->sql->data;
        }

        if ( !$this->unsafe )
        {
            $instance           = new $source( $this->sql->data );
            $data               = $instance->values();
        }

        // Captura a declaração das colunas e os valores a inserir
        foreach ( $data as $column => $value )
        {
            $columns[]          = "`$column`";
            $bind               = self::makeParam();
            $binds[]            = $bind;
            $values[ $bind ]    = $value;
        }

        $columns                = implode( ', ', $columns );
        $binds                  = implode( ', ', $binds );
        $sql                    = "INSERT INTO `{$table}` ( {$columns} ) VALUES ( {$binds} )";
        return array( $sql, $values );
    }

    /**
     * Obtém as colunas da consulta.
     * 
     * @return null
     */
    protected function setColumns()
    {
        if ( $this->type === 'exists' )
        {
            return $this->columns = '1';
        }

        if ( $this->type === 'count' )
        {
            return $this->columns = 'COUNT(1) `total`';
        }

        if ( is_string( $this->sql->select ) )
        {
            return $this->columns = $this->sql->select;
        }
        
        if ( !$this->sql->select )
        {
            $source             = $this->sql->source;
            $this->sql->select  = $source::columns( true );
        }

        // Se trata-se de um array ou objeto
        $columns = array();
        foreach ( $this->sql->select as $alias => $column )
        {
            $column .= is_string( $alias )
                ? " `$alias`"
                : '';
            $columns[] = $column;
        }
        $columns = implode( ', ', $columns );
        $this->columns = $columns;
    }

    /**
     * Captura o agrupamento da consulta.
     * 
     * @return null
     */
    protected function setGroup()
    {
        $group = array();
        foreach ( $this->sql->group as $column )
        {
            $group[] = "`$table`.`$column`";
        }

        $group = implode( ', ', $group );
        if ( $group )
        {
            $group = 'GROUP BY ' . $group;
        }
        $this->group = $group;
    }

    /**
     * Captura as condições de agrupamento da consulta
     * 
     * @return string
     */
    protected function setHaving()
    {
        $having = array();
        foreach ( $this->sql->having as $column )
        {
            $having[] = "`$table`.`$column`";
        }

        $having = implode( ', ', $having );
        if ( $having )
        {
            $having = 'HAVING ' . $having;
        }
        $this->having = $having;
    }

    /**
     * Captura a ordenação da consulta.
     * 
     * @return string
     */
    protected function setOrder()
    {
        $order  = array();
        $source = $this->sql->source;
        $table  = $source::tableName();
        
        foreach ( $this->sql->order as $column => $ascend )
        {
            $column = "`$table`.`$column`";
            if ( in_array( $ascend, [ '1', 'ASC' ] ) )
            {
                $column .= ' ASC';
            }
            elseif ( in_array( $ascend, [ '-1', 'DESC' ] ) )
            {
                $column .= ' DESC';
            }
            $order[] = $column;
        }

        $order = implode( ', ', $order );
        if ( $order )
        {
            $order = 'ORDER BY ' . $order;
        }

        // Se a paginação for por ID, então guarda a ordenação atual
        // para ser aplicada depois da ordenação por ID
        if ( $this->reorder )
        {
            $pagination             = &$this->sql->pagination;
            $pagination[ 'order' ]  = $order;
            
            $source                 = $this->sql->source;
            
            $id_name                = $source::primaryKey();
            $dir                    = $pagination[ 'dir' ];
            
            $order                  = " ORDER BY $id_name $dir";
        }
        $this->order = $order;
    }

    /**
     * Captura o limite de registros da consulta.
     * 
     * @return string
     */
    protected function setLimit()
    {
        $limit      = 'LIMIT ';
        $one_types  = array( 'one', 'exists' );
        
        if ( in_array( $this->type, $one_types ) )
        {
            return $this->limit = $limit . '1';
        }

        if ( $this->sql->pagination )
        {
            return $this->limit = $limit . $this->sql->pagination[ 'limit' ];
        }

        if ( $this->sql->limit )
        {
            return $this->limit = $limit . $this->sql->limit;
        }

        return $this->limit = '';
    }

    /**
     * Captura o salto inicial de registros da consulta.
     * 
     * @return string
     */
    protected function setOffset()
    {
        $offset      = 'OFFSET ';
        $pagination  = $this->sql->pagination;

        if ( $pagination && $pagination[ 'type' ] === 'offset' )
        {
            return $this->offset = $offset . $pagination[ 'offset' ];
        }
        if ( $this->sql->offset )
        {
            return $this->offset = $offset . $this->sql->offset;
        }
        return $this->offset = '';
    }

    /**
     * Recupera as condições da consulta.
     * 
     * @return array
     */
    protected function setConditions()
    {
        // Captura as condições da consulta
        $conditions = $this->sql->conditions;
        $pagination = $this->sql->pagination;
        $source     = $this->sql->source;
        $table      = $source::tableName();
        $values     = array();

        // PAGINACAO
        // Utilizamos uma coluna indexada como identificador da posição do registro
        // para ganho de performance
        if ( $pagination && $pagination[ 'type' ] === 'by_id' )
        {
            $operator   = $pagination[ 'dir' ] === 'DESC' ? '<' : '>';
            $condition  = [
                $pagination[ 'col_ref' ],
                $operator,
                $pagination[ 'last_val' ],
            ];
            array_unshift( $conditions, $condition );
        }

        // Inicia a construção das condições
        if ( !$conditions )
        {
            $this->where    = '';
            $this->values   = array();
            return;
        }
        $start_block    = true;
        $where          = array( '(' );

        foreach ( $conditions as $i => $condition )
        {

            // Define o conector das condições
            if ( $condition === 'or' )
            {
                $where[]        = ') OR (';
                $start_block    = true;
                continue;
            }

            $connector          = ' AND ';
            
            if ( $start_block )
            {
                $connector      = ''; // Já adicionou ') OR ('
                $start_block    = false;
            }

            // Se passou apenas uma string sem operador (possivelmente uma where já tratada)
            // então acrescenta e pula
            if ( empty( $condition[ 1 ] ) )
            {
                $expression     = $condition[ 0 ];
                $where[]        = "$connector({$expression})";
                continue;
            }

            // Constrói as condições no processo normal
            $column             = $condition[ 0 ];
            $operator           = $condition[ 1 ];
            $value              = $condition[ 2 ];
            $unsafe             = $condition[ 3 ];

            if ( substr( $operator, -4 ) === 'NULL' )
            {
                $where[]            = "$connector`$table`.`$column` {$operator}";
                continue;
            }
            
            if ( substr( $operator, -2 ) === 'IN' )
            {
                if ( $unsafe )
                {
                    $where[]            = "$connector`$table`.`$column` {$operator} ($value)";
                    continue;
                }

                $bind               = self::makeParam();
                $where[]            = "$connector`$table`.`$column` {$operator} ($bind)";
                $values[ $bind ]    = $value;
                continue;
            }

            if ( substr( $operator, -7 ) === 'BETWEEN' )
            {
                if ( $unsafe )
                {
                    $value1         = $value[0];
                    $value2         = $value[1];
                    $where[]        = "$connector`$table`.`$column` $operator $value1 AND $value2";
                    continue;
                }

                $bind1              = self::makeParam();
                $bind2              = self::makeParam();
                $where[]            = "$connector`$table`.`$column` $operator $bind1 AND $bind2";
                $values[ $bind1 ]   = $value[ 0 ];
                $values[ $bind2 ]   = $value[ 1 ];
                continue;
            }

            if ( $unsafe )
            {
                $where[]            = "$connector`$table`.`$column` $operator $value";
                continue;
            }

            $bind                   = self::makeParam();
            $where[]                = "$connector`$table`.`$column` $operator $bind";
            $values[ $bind ]        = $value;
        }

        $where = implode( $where ) . ')';
        $this->where    = 'WHERE ' . $where;
        $this->values   = $values;
    }
    
    /**
     * Cria um nome para um parâmetro de consulta.
     * 
     * @return string
     */
    protected function getSqlAndValues()
    {
        $source         = $this->sql->source;
        $table          = $source::tableName();

        switch ( $this->type )
        {
            case 'exists':
            case 'count':
            case 'one':
            case 'all':
                $sql = $this->getSqlSelect( $table );
                
                // Aplica a ordem original antes da paginação
                if ( $this->reorder && $this->type === 'all' )
                {
                    $order  = $this->sql->pagination[ 'order' ];
                    $sql    = "SELECT * FROM ($sql) `$table`{$order}";
                }
                return $sql;

            case 'update':
                return $this->getSqlUpdate( $table );

            case 'increment':
            case 'decrement':
                return $this->getSqlInDecrement( $table );

            case 'delete':
                return $this->getSqlDelete( $table );
        }
    }
    
    /**
     * Cria um nome para um parâmetro de consulta.
     * 
     * @return string
     */
    protected function getSqlSelect( $table )
    {
        $sql = "
            SELECT
                {$this->columns}
            FROM
                `{$table}`
            {$this->where}
            {$this->group}
            {$this->having}
            {$this->order}
            {$this->offset}
            {$this->limit}
        ";
        return $sql;
    }
    
    /**
     * Cria um nome para um parâmetro de consulta.
     * 
     * @return string
     */
    protected function getSqlUpdate( $table )
    {
        $sets               = array();
        
        foreach ( $this->sql->data as $key => $val )
        {
            $bind                   = self::makeParam();
            $sets[]                 = "`$table`.`$key` = $bind";
            $this->values[ $bind ]  = $val;
        }
        $this->sets                 = implode( ', ', $sets );
        $sql                        = "
            UPDATE
                `{$table}`
            SET
                {$this->sets}
            {$this->where}
            {$this->group}
            {$this->having}
            {$this->offset}
            {$this->limit}
        ";
        return $sql;
    }
    
    /**
     * Cria um nome para um parâmetro de consulta.
     * 
     * @return string
     */
    protected function getSqlInDecrement( $table )
    {
        $bind                   = self::makeParam();
        $key                    = key( $this->sql->data );
        $this->values[ $bind ]  = current( $this->sql->data );
        $sets                   = "`$table`.`$key` = `$table`.`$key` + $bind";
        $sql                    = "
            UPDATE
                `{$table}`
            SET
                {$this->sets}
            {$this->where}
            {$this->group}
            {$this->having}
            {$this->offset}
            {$this->limit}
        ";
        return $sql;
    }
    
    /**
     * Cria um nome para um parâmetro de consulta.
     * 
     * @return string
     */
    protected function getSqlDelete( $table )
    {
        $sql = "
            DELETE FROM
                `{$table}`
            {$this->where}
            {$this->group}
            {$this->having}
            {$this->offset}
            {$this->limit}
        ";
        return $sql;
    }
    
    /**
     * Cria um nome para um parâmetro de consulta.
     * 
     * @return string
     */
    protected static function makeParam()
    {
        return str_replace( '.', '_', uniqid( ':param_', true ) );
    }
}
