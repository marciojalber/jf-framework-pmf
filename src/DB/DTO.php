<?php

namespace JF\DB;

use JF\DB\DB;
use JF\Exceptions\InfoException as Info;
use JF\Exceptions\ErrorException as Error;
use JF\Exceptions\WarningException as Warning;
use JF\Types\DateTime__Type;

/**
 * Data Transfer Object - Classe representativa de um registro da tabela.
 */
class DTO extends \StdClass
{
    /**
     * Esquema de conexão de todos os DTOs.
     */
    private static $schemas         = [];
    
    /**
     * Tabelas de todos os DTOs.
     */
    private static $tables          = [];
    
    /**
     * Chave-primária da tabela.
     */
    protected static $priKeys       = [];
    
    /**
     * Dados sensíveis / privados do registro.
     */
    protected static $hides         = [];
    
    /**
     * Colunas de todos os DTOs.
     */
    protected static $dtoColumns    = [];
    
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
     * Mensagem de erro em caso de nenhum dados novo informado antes de salvar.
     */
    protected $msgOnUnchanged;
    
    /**
     * Mensagem de erro em caso de falha na execução da operação.
     */
    protected $msgOnFail;
    
    /**
     * Dados sensíveis do registro.
     */
    protected $_changed         = [];

    /**
     * Retorna o nome do esquema de conexão.
     */
    public static function schema()
    {
        $class =  get_called_class();

        if ( !static::$columns && !isset( self::$dtoColumns[ $class ] ) )
            static::captureColumns();

        return self::$schemas[ $class ] ?? static::$schema;
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
        $class =  get_called_class();

        if ( !static::$columns && !isset( self::$dtoColumns[ $class ] ) )
            static::captureColumns();
        
        return self::$tables[ $class ] ?? static::$table;
    }

    /**
     * Retorna a estrutura de colunas do model.
     */
    public static function structure()
    {
        $class =  get_called_class();

        if ( !static::$columns && !isset( self::$dtoColumns[ $class ] ) )
            static::captureColumns();
        
        return self::$dtoColumns[ $class ] ?? static::$columns;
    }

    /**
     * Retorna a estrutura de colunas do model.
     */
    public static function isView()
    {
        return static::$isView;
    }

    /**
     * Valida os dados do objeto.
     */
    private static function captureColumns()
    {
        $class      = get_called_class();
        $ref_class  = new \ReflectionClass( $class );
        $attrs      = $ref_class->getAttributes();

        foreach ( $attrs as $attr )
        {
            $name       = preg_replace( '@.*\\\@', '', $attr->getName() );
            $val        = $attr->getArguments()[0] ?? null;

            if ( $name == 'schema' && $val )
                static::$schemas[ $class ]  = $val;

            if ( $name == 'table' && $val )
                static::$tables[ $class ]   = $val;
        }

        $types                  = [
            'str',  'email',
            'bit',  'int',      'float',
            'date', 'datetime', 'time',
        ];
        $cols                   = $ref_class->getProperties();
        $columns                = [];
        self::$hides[ $class ]  = [];

        foreach ( $cols as $col )
        {
            if ( !$col->isPublic() || $col->isStatic() )
                continue;
            
            $attrs                  = $col->getAttributes();
            $columns[ $col->name ]  = (object) [
                'desc'              => $col->name,
            ];
            $column                 = &$columns[ $col->name ];
            
            if ( $attrs )
            {
                foreach ( $attrs as $attr )
                {
                    $name       = preg_replace( '@.*\\\@', '', $attr->getName() );
                    $val        = $attr->getArguments()[0] ?? null;
                    $attr_void  = ['priKey', 'hide', 'required', 'trim'];
                    $attr_arg   = [
                        'type',         'desc',
                        'min',          'max',
                        'minlength',    'maxlength',
                        'lessThan',     'lessEqThan',
                        'greaterThan',  'greaterEqThan',
                    ];

                    if ( in_array( $name, $attr_void ) )
                        $column->$name = 1;

                    if ( in_array( $name, $attr_arg ) )
                        $column->$name = $val;
                }

                if ( !empty( $column->priKey ) )
                    self::$priKeys[ $class ]    = $col->name;

                if ( !empty( $column->hide ) )
                    self::$hides[ $class ][]    = $col->name;
            }

            $label = $class . '.' . $col->name;

            if ( empty( $column->type ) )
                throw new Warning( "Nenhum tipo de dado definido para [$label]." );

            if ( !in_array( $column->type, $types ) )
                throw new Warning( "Tipo de dado definido para [$label] é inválido." );
        }
        
        static::$dtoColumns[ $class ] = $columns;
    }

    /**
     * Valida os dados do objeto.
     */
    public function sanitize()
    {
        $props          = static::structure();

        foreach ( $props as $key => $prop )
        {
            $val = $this->$key ?? null;

            if ( $val === null || $val === '' )
                continue;

            if ( $prop->type == 'str' && !empty( $prop->trim ) )
                $this->$key = preg_replace( '@[\s\t]+@', ' ', trim( $this->$key ) );
        }

        return $this;
    }

    /**
     * Valida os dados do objeto.
     */
    public function validate()
    {
        $props          = static::structure();
        $date_pattern   = '/^\d{4}-\d{2}-\d{2}$/';
        $dt_pattern     = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        $time_pattern   = '/^\d{2}:\d{2}:\d{2}$/';
        // print_r( $props );
        
        foreach ( $props as $key => $prop )
        {
            $val        = $this->$key ?? null;
            $label      = $prop->desc;

            if ( !empty( $prop->required ) && ( $val === null || $val === '' ) )
                throw new Warning( "É obrigatório um valor para [{$prop->desc}]." );

            if ( $val === null || $val === '' )
                continue;

            $strlen     = isset( $prop->minlength ) || isset( $prop->maxlength )
                ? mb_strlen( $val )
                : null;

            if ( !empty( $prop->unsigned ) && $prop->min < 0 )
                throw new Warning( "O valor de [$label] deve ser maior ou igual a 0." );

            if ( !empty( $prop->min ) && $prop->min > $val )
                throw new Warning( "O valor de [$label] deve ser maior ou igual a [{$prop->min}]." );

            if ( !empty( $prop->max ) && $prop->max < $val )
                throw new Warning( "O valor de [$label] deve ser menor ou igual a [{$prop->max}]." );
            
            if ( !empty( $prop->minlength ) && $strlen < $prop->minlength )
                throw new Warning( "O valor de [$label] deve ter pelo menos [{$prop->minlength}] caracteres." );

            if ( !empty( $prop->maxlength ) && isset( $val[ $prop->maxlength ] ) )
                throw new Warning( "O valor de [$label] deve ter até [{$prop->maxlength}] caracteres." );

            if ( !empty( $prop->opts ) && !in_array( $val, $prop->opts ) )
                throw new Warning( "Valor inválido informado para [$label]." );

            if ( !empty( $prop->lessThan ) )
            {
                $comp = $prop->lessThan;

                if ( !isset( $props[ $comp ] ))
                    throw new Warning( "Definido um campo inexistente para MENOR QUE em [$label]." );

                $vlr    = $this->$comp;
                $vlr_ok = $this->$comp === null || $this->$comp === '';

                if ( $this->$comp !== null && $this->$comp !== '' && $vlr <= $val )
                    throw new Warning( "[$label] deve ser menor que [{$props[ $comp ]->desc}]." );
            }

            if ( !empty( $prop->lessEqThan ) )
            {
                $comp = $prop->lessEqThan;

                if ( !isset( $props[ $comp ] ))
                    throw new Warning( "Definido um campo inexistente para MENOR OU IGUAL A em [$label]." );

                $vlr    = $this->$comp;
                $vlr_ok = $this->$comp === null || $this->$comp === '';

                if ( $this->$comp !== null && $this->$comp !== '' && $vlr < $val )
                    throw new Warning( "[$label] deve ser menor ou igual a [{$props[ $comp ]->desc}]." );
            }

            if ( !empty( $prop->greaterThan ) )
            {
                $comp = $prop->greaterThan;

                if ( !isset( $props[ $comp ] ))
                    throw new Warning( "Definido um campo inexistente para MAIOR QUE em [$label]." );

                $vlr    = $this->$comp;
                $vlr_ok = $this->$comp === null || $this->$comp === '';

                if ( $this->$comp !== null && $this->$comp !== '' && $vlr >= $val )
                    throw new Warning( "[$label] deve ser maior que [{$props[ $comp ]->desc}]." );
            }

            if ( !empty( $prop->greaterEqThan ) )
            {
                $comp = $prop->greaterEqThan;

                if ( !isset( $props[ $comp ] ))
                    throw new Warning( "Definido um campo inexistente para MAIOR OU IGUAL A em [$label]." );

                $vlr    = $this->$comp;
                $vlr_ok = $this->$comp === null || $this->$comp === '';

                if ( $this->$comp !== null && $this->$comp !== '' && $vlr > $val )
                    throw new Warning( "[$label] deve ser maior ou igual a [{$props[ $comp ]->desc}]." );
            }

            if ( $prop->type == 'str' && !is_string( $val ) )
                throw new Warning( "O valor informado para [$label] não é um texto." );

            if ( $prop->type == 'name' && !is_string( $val ) )
                throw new Warning( "O valor informado para [$label] não é um nome." );

            if ( $prop->type == 'email' && ( !is_string( $val ) || !filter_var( $val, FILTER_VALIDATE_EMAIL ) ) )
                throw new Warning( "O valor informado para [$label] não é um e-mail." );

            if ( $prop->type == 'bit' && !in_array( $val, [0, 1] ) )
                throw new Warning( "Valor inválido informado para [$label]." );

            if ( $prop->type == 'int' )
                if ( !( is_int( $val ) || is_string( $val ) && preg_match( '@^-?\d+$@', $val ) ) )
                    throw new Warning( "O valor informado para [$label] não é um número." );

            if ( $prop->type == 'float' && !is_numeric( $val ) )
                throw new Warning( "O valor informado para $label não é um número ou decimal." );

            if ( $prop->type == 'date' )
            {
                if ( !preg_match( $date_pattern, $val ) )
                    throw new Warning( "O valor informado para [$label] não é uma data válida." );

                $year   = substr( $val, 0, 4 );
                $month  = substr( $val, 5, 2 );
                $date   = substr( $val, -2 );
                
                if ( !checkdate( $month, $date, $year ) )
                    throw new Warning( "O valor informado para [$label] não é uma data válida." );
            }

            if ( $prop->type == 'datetime' )
            {
                if ( !preg_match( $dt_pattern, $val ) )
                    throw new Warning( "O valor informado para [$label] não é uma data/hora válida." );

                $year   = substr( $val, 0, 4 );
                $month  = substr( $val, 5, 2 );
                $date   = substr( $val, 8, 2 );
                $hour   = substr( $val, 11, 2 );
                $min    = substr( $val, 14, 2 );
                $seg    = substr( $val, -2 );
                
                if ( !checkdate( $month, $date, $year ) )
                    throw new Warning( "O valor informado para [$label] não é uma data/hora válida." );
                
                if ( $hour < 0 || $hour > 23 )
                    throw new Warning( "O valor informado para [$label] não é uma data/hora válida." );
                
                if ( $min < 0 || $min > 59 )
                    throw new Warning( "O valor informado para [$label] não é uma data/hora válida." );
                
                if ( $seg < 0 || $seg > 59 )
                    throw new Warning( "O valor informado para [$label] não é uma data/hora válida." );
            }

            if ( $prop->type == 'time' )
            {
                if ( !preg_match( $time_pattern, $val ) )
                    throw new Warning( "O valor informado para [$label] não é uma hora válida." );

                $hour   = substr( $val, 11, 2 );
                $min    = substr( $val, 14, 2 );
                $seg    = substr( $val, -2 );
                
                if ( $hour < 0 || $hour > 23 )
                    throw new Warning( "O valor informado para [$label] não é uma hora." );
                
                if ( $min < 0 || $min > 59 )
                    throw new Warning( "O valor informado para [$label] não é uma hora." );
                
                if ( $seg < 0 || $seg > 59 )
                    throw new Warning( "O valor informado para [$label] não é uma hora." );
            }
        }

        return $this;
    }

    /**
     * Retorna os campos de dado sensíveis do DTO ou se um campo específico é sensível.
     */
    public static function hide( $column = null )
    {
        $class =  get_called_class();

        if ( !static::$columns && !isset( self::$dtoColumns[ $class ] ) )
            static::captureColumns();

        $hides = self::$hides[ $class ] ?? static::$hide;

        return $column
            ? in_array( $column, $hides )
            : $hides;
    }

    /**
     * Chave primária da tabela.
     */
    public static function primaryKey()
    {
        $class =  get_called_class();

        if ( !static::$columns && !isset( self::$dtoColumns[ $class ] ) )
            static::captureColumns();

        return self::$priKeys[ $class ] ?? 'id';
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

        $_columns   = static::structure();

        foreach ( $_columns as $column => $prop )
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
     * Se o valor passado estiver vazio, lança uma exceção de erro.
     */
    public function onUnchanged( $msg )
    {
        $this->msgOnUnchanged = $msg;
        return $this;
    }

    /**
     * Se o valor passado estiver vazio, lança uma exceção de erro.
     */
    public function onFail( $msg )
    {
        $this->msgOnFail = $msg;
        return $this;
    }

    /**
     * Soma 1 ao valor da coluna.
     */
    public function add( $key, $value )
    {
        $this->set( $key, $this->$key + 1 );

        return $this;
    }

    /**
     * Subtrai 1 do valor da coluna.
     */
    public function sub( $key, $value )
    {
        $this->set( $key, $this->$key - 1 );

        return $this;
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

        $class                      =  get_called_class();

        if ( !static::$columns && !isset( self::$dtoColumns[ $class ] ) )
            static::captureColumns();

        $record_is_saved            = $this->_status == 'saved';
        $class                      = get_called_class();
        $column_exists              = array_key_exists( $key, $class::structure() );
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

        $class      =  get_called_class();

        if ( !static::$columns && !isset( self::$dtoColumns[ $class ] ) )
            static::captureColumns();

        $data   = array_intersect_key( $data, $class::structure() );

        return (object) $data;
    }

    /**
     * Retorna um novo objeto com os campos sensíveis removidos.
     */
    public function filter()
    {
        $data       = (array) $this;
        $record     = new static();
        $class      =  get_called_class();

        if ( !static::$columns && !isset( self::$dtoColumns[ $class ] ) )
            static::captureColumns();

        $columns    = $class::structure();
        
        array_walk( $data, function( $value, $key ) use ( $record )
        {
            if ( in_array( $key, static::$hide ) )
                return;

            if ( !array_key_exists( $key, $columns ) )
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
        if ( !$this->changed() && $this->msgOnUnchanged )
            throw new Info( $this->msgOnUnchanged );

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

        if ( !$count && $this->msgOnFail )
            throw new Error( $this->msgOnFail );

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

        if ( !$count && $this->msgOnFail )
            throw new Error( $this->msgOnFail );

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
