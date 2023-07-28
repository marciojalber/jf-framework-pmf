<?php

namespace JF\DB;

use JF\DB\DB;
use JF\Exceptions\ErrorException as Error;
use JF\Types\DateTime__Type;

/**
 * Data Object Transfer - Classe representativa de um registro da tabela.
 */
class DTO extends \StdClass
{
    /**
     * Esquema de conexão.
     */
    protected static $schema;
    
    /**
     * Nome da tabela.
     */
    protected static $table;
    
    /**
     * Dados sensíveis / privados do registro.
     */
    protected static $hide      = [];
    
    /**
     * Colunas da tabela.
     */
    protected static $columns   = [];
    
    /**
     * Trata-se de uma VIEW.
     */
    protected static $isView    = false;
    
    /**
     * Status do registro.
     */
    protected $_status          = 'created';
    
    /**
     * Dados sensíveis do registro.
     */
    protected $_changed         = [];

    /**
     * Retorna o nome do esquema de conexão.
     */
    public static function schema()
    {
        return static::$schema;
    }

    /**
     * Retorna o nome do banco-de-dados.
     */
    public static function dbname()
    {
        return static::db()->config( 'dbname' );
    }

    /**
     * Retorna o nome da tabela do banco.
     */
    public static function table()
    {
        return static::$table;
    }

    /**
     * Retorna a estrutura de colunas do model.
     */
    public static function structure()
    {
        return static::$columns;
    }

    /**
     * Retorna a estrutura de colunas do model.
     */
    public static function isView()
    {
        return static::$isView;
    }

    /**
     * Retorna a estrutura de colunas do model.
     */
    public static function validateData( $label, $colname, $value, $params = [] )
    {
        if ( !isset( static::$columns[ $colname ] ) )
        {
            $msg = Messager::get( 'db', 'column_not_exists_to_validate', $column, get_called_class() );
            throw new Error( $msg );
        }

        $column     = static::$columns[ $colname ];
        $required   = $column[ 'required' ]     ?? false;
        $type       = $column[ 'type' ]         ?? false;
        $decimals   = $column[ 'decimals' ]     ?? 0;
        $min        = $column[ 'min' ]          ?? null;
        $max        = $column[ 'max' ]          ?? null;
        $minlength  = $column[ 'minlength' ]    ?? null;
        $maxlength  = $column[ 'maxlength' ]    ?? null;
        $currency   = $column[ 'currency' ]     ?? null;
        $options    = $column[ 'options' ]      ?? null;
        $range      = $column[ 'range' ]        ?? null;

        if ( !$required && ( $value === null || $value === '' || $value === [] ) )
            return;

        if ( $required && ( $value === null || $value === '' || $value === [] ) )
        {
            if ( isset( $params[ 'required' ] ) )
                throw new Error( $params[ 'required' ] );
            
            throw new Error( App\App::textInvalidatedRequired( $label ) );
        }

        if ( !is_null( $type ) && $type == 'date' && !DateTime__Type::validateDate( $value ) )
            throw new Error( App\App::textInvalidatedDate( $label ) );

        if ( !is_null( $type ) && $type == 'time' && !DateTime__Type::validateTime( $value ) )
            throw new Error( App\App::textInvalidatedTime( $label ) );

        if ( !is_null( $type ) && $type == 'email' && !filter_var( $value, FILTER_VALIDATE_EMAIL ) )
            throw new Error( App\App::textInvalidatedEmail( $label ) );

        if ( !is_null( $min ) && $value < $min )
        {
            $value_formated     = number_format( $value, $decimals, ',', '.' );
            $min_formated       = number_format( $min, $decimals, ',', '.' );
            throw new Error( App\App::textInvalidatedMin( $label, $value_formated, $min_formated, $currency ) );
        }

        if ( !is_null( $max ) && $value > $max )
        {
            $value_formated     = number_format( $value, $decimals, ',', '.' );
            $max_formated       = number_format( $max, $decimals, ',', '.' );
            throw new Error( App\App::textInvalidatedMax( $label, $value_formated, $max_formated, $currency ) );
        }

        if ( !is_null( $minlength ) && !isset( $value[ $minlength ] ) )
        {
            $len_formated       = number_format( strlen( $value ), 0, ',', '.' );
            $minlength_formated = number_format( $minlength, 0, ',', '.' );
            throw new Error( App\App::textInvalidatedMinlength( $label, strlen( $value ), $minlength_formated ) );
        }

        if ( !is_null( $maxlength ) && isset( $value[ $maxlength ] ) )
        {
            $len_formated       = number_format( strlen( $value ), 0, ',', '.' );
            $maxlength_formated = number_format( $maxlength, 0, ',', '.' );
            throw new Error( App\App::textInvalidatedMaxlength( $label, strlen( $value ), $maxlength_formated ) );
        }

        if ( !is_null( $options ) && !isset( $options[ $value ] ) )
        {
            if ( isset( $params[ 'option' ] ) )
                throw new Error( $params[ 'option' ] );
            
            throw new Error( App\App::textInvalidatedOption( $label ) );
        }

        if ( !is_null( $range ) && ( $value < $range[0] || $value > $range[1] ) )
            throw new Error( App\App::textInvalidatedRange( $label, $range[0], $range[1] ) );
    }

    /**
     * Retorna os campos de dado sensíveis do DTO ou se um campo específico é sensível.
     */
    public static function hide( $column = null )
    {
        return $column
            ? in_array( $column, static::$hide )
            : static::$hide;
    }

    /**
     * Chave primária da tabela.
     */
    public static function primaryKey()
    {
        return 'id';
    }

    /**
     * Pesquisa simples por um registro na tabela.
     */
    public static function columns( $opts = [] )
    {
        $response   = [];
        $unsafe     = !empty( $opts[ 'unsafe' ] );
        $columns    = isset( $opts[ 'columns' ] )
            ? $opts[ 'columns' ]
            : null;

        foreach ( static::$columns as $column => $prop )
        {
            $col_hide   = in_array( $column, static::$hide );

            if ( !$unsafe && $col_hide )
                continue;

            if ( $columns && !in_array( $column, $columns ) )
                continue;

            $response[]  = $column;
        }

        return $response;
    }

    /**
     * Retorna um DAO para realizar consultas ao banco.
     */
    public static function dao()
    {
        return new DAO( get_called_class() );
    }

    /**
     * Retorna um DAO para realizar consultas ao banco.
     */
    public static function db()
    {
        return DB::instance( static::schema() );
    }

    /**
     * Pesquisa simples por um registro na tabela.
     */
    public static function dbOptions( $opts = [] )
    {
        return array_merge( $opts, [
            'class'         => get_class( new static ),
            'class_start'   => 'start',
        ]);
    }

    /**
     * Indica que o objeto foi iniciado.
     */
    public function start()
    {
        $this->_status = 'saved';
    }

    /**
     * Define um novo valor para uma propriedade do objeto.
     */
    public function set( $key, $value )
    {
        if ( !isset( $this->$key ) )
            $this->$key             = null;

        $old_value                  = $this->$key;
        $this->$key                 = $value;

        if ( $old_value === $value )
            return $this;

        $prop_changed               = array_key_exists( $key, $this->_changed );

        if ( $prop_changed && $this->_changed[ $key ] === $value )
        {
            unset( $this->_changed[ $key ] );
            return $this;
        }

        $record_is_saved            = $this->_status == 'saved';
        $column_exists              = array_key_exists( $key, static::$columns );
        $value_changed              = array_key_exists( $key, $this->_changed );

        if ( $record_is_saved && $column_exists && !$value_changed )
            $this->_changed[ $key ] = $old_value;

        return $this;
    }

    /**
     * Retorna um novo objeto com os campos sensíveis removidos.
     */
    public function values( $unsafe = false )
    {
        $data       = (array) $this;
        unset( $data[ '_status' ] );
        unset( $data[ '_changed' ] );
        
        if ( $unsafe )
            return $data;

        $data   = array_intersect_key( $data, static::$columns );

        return (object) $data;
    }

    /**
     * Retorna um novo objeto com os campos sensíveis removidos.
     */
    public function filter()
    {
        $data   = (array) $this;
        $record = new static();
        
        array_walk( $data, function( $value, $key ) use ( $record )
        {
            if ( in_array( $key, static::$hide ) )
                return;

            if ( !array_key_exists( $key, static::$columns ) )
                return;

            $record->$key = $value;
        });

        return $record;
    }

    /**
     * Retorna as alterações não salvas de um registro.
     */
    public function changes()
    {
        return array_intersect_key( (array) $this->values(), $this->_changed );
    }

    /**
     * Identifica se um registro sofreu alterações não salvas.
     */
    public function changed()
    {
        return (bool) $this->_changed;
    }

    /**
     * Identifica se houve alteração em um registro.
     */
    public function restore()
    {
        foreach ( $this->_changed as $key => $value )
        {
            $this->$key = $value;
            unset( $this->_changed[ $key ] );
        }

        $this->_status = 'saved';
    }

    /**
     * Retorna uma nova instância do próprio objeto, com os valores originais na tabela.
     */
    public function reload()
    {
        $pk = static::primaryKey();
        return static::dao()->find( $this->$pk );
    }

    /**
     * Salva o registro na tabela (cria um novo ou atualiza o registro existente).
     */
    public function save()
    {
        if ( !$this->changed() )
            return false;

        if ( $this->_status == 'created' )
            $values         = $this->values();

        if ( $this->_status != 'created' )
            $values         = $this->changes();

        $key                = static::primaryKey();
        $count              = static::dao()
            ->update( $this->$key, $key, $values )
            ->count();

        if ( $count )
            $this->_status  = 'saved';

        return $count;
    }

    /**
     * Identifica se houve alteração em um registro.
     */
    public function delete()
    {
        $key                = static::primaryKey();
        $count              = static::dao()
            ->delete( $this->$key, $key )
            ->count();

        if ( $count )
            $this->_status  = 'deleted';

        return $count;
    }

    /**
     * Atualiza os dados do registro atual com os dados do registro na tabela.
     */
    public function refresh()
    {
        $key    = static::primaryKey();
        $record = static::dao()->find( $this->$key, $key );

        array_walk( (array) $record, function( $value, $key ) {
            $this->$key = $value;
        });
    }
}
