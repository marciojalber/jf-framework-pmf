<?php

namespace JF\Makers\Model;

use JF\Config;
use JF\DB\DB;
use JF\System\Dir;

/**
 * Criador de DTOs.
 */
class ModelMaker
{
    /**
     * Nome do esquema de acesso ao banco-de-dados.
     */
    protected $schema;

    /**
     * Configurações de conexão.
     */
    protected $config;

    /**
     * Nome da tabela.
     */
    protected $table;

    /**
     * Comentários da tabela.
     */
    protected $label;

    /**
     * Propriedades da tabela.
     */
    protected $tableColumns;

    /**
     * Estrutura das propriedades do DTO.
     */
    protected $props    = [];

    /**
     * Propriedades do DTO.
     */
    protected $cols     = '';

    /**
     * Chave primária.
     */
    protected $priKey   = 'id';

    /**
     * Retorna o nome do DTO a partir do nome da tabela.
     */
    public static function getNSFromSchema( $schema, $table )
    {
        $ns         = 'App\DTO\\' . preg_replace_callback( '@(^.|_.)@', function( $matches ) {
            return strtoupper( str_replace( '_', '', $matches[ 0] ) );
        }, $schema );
        $ns         .= '\\' . self::getNameFromTable( $table );

        return $ns;
    }

    /**
     * Retorna o nome do DTO a partir do nome da tabela.
     */
    public static function getNameFromTable( $table )
    {
        $name       = preg_replace_callback( '@(^.|_.)@', function( $matches ) {
            return strtoupper( str_replace( '_', '', $matches[ 0] ) );
        }, $table );

        return $name;
    }

    /**
     * Instancia a classe.
     */
    public static function init( $schema, $table, $config = [] )
    {
        return new self( $schema, $table, $config );
    }

    /**
     * Método construtor.
     */
    public function __construct( $schema, $table, $config = [] )
    {
        if ( !$config && !$schema )
            throw new \Exception( "Esquema de acesso ao banco-de-dados não informado." );
        
        if ( !$config )
            $config = Config::get( 'db.schemas.' . $schema );

        if ( !$config )
            throw new \Exception( "Esquema de acesso ao banco-de-dados [$schema] inválido." );
            
        if ( !$table )
            throw new \Exception( "Tabela do banco-de-dados não informado." );
            
        $this->schema   = $schema;
        $this->config   = $config;
        $this->table    = $table;
    }

    /**
     * Executa a criação do DTO.
     */
    public function create( $dirname = null )
    {
        $this->getTableComment();
        $this->getTableInfos();
        $this->fillProps();
        // $this->fillColumns();

        $ns         = self::getNSFromSchema( $this->schema, $this->table );
        $name       = self::getNameFromTable( $this->table );
        $classname  = $name . '__Model';
        $traitname  = $name . '__Tasks';
        $props      = $this->prepareProps();
        // $cols       = $this->prepareColumns();
        $hoje       = date( 'd/m/Y, à\s H:i:s' );
        $dirname    = !$dirname
            ? DIR_BASE . '/' . str_replace( '\\', '/', $ns )
            : $dirname;
        $classfile  = $dirname . '/' . $classname . '.php';
        $traitfile  = $dirname . '/' . $traitname . '.php';
        $class      = file_get_contents( __DIR__ . '/TemplateModel.php' );
        $class      = str_replace(
            ['$ns', '$_table', '$hoje', '$classname', '$traitname', '$_schema', '$_props'],
            [$ns, $this->table, $hoje, $classname, $traitname, $this->schema, $props, $this->priKey, $this->label],
            $class
        );
        $trait      = file_get_contents( __DIR__ . '/TemplateTasks.php' );
        $trait      = str_replace(
            ['$ns', '$dto', '$hoje', '$traitname'],
            [$ns, $classname, $hoje, $traitname],
            $trait
        );

        $paths      = !file_exists( $dirname )
            ? (int) !!Dir::makeDir( $dirname )
            : 0;

        $files      = (int) !!file_put_contents( $classfile, $class );
        $files     += !file_exists( $traitfile )
            ? (int) !!file_put_contents( $traitfile, $trait )
            : 0;

        return (object) [
            'filetime'  => filemtime( $classfile ),
            'paths'     => $paths,
            'files'     => $files,
        ];
    }

    /**
     * Obtém as informações da tabela.
     */
    protected function getTableComment()
    {
        $sql            = "
            SELECT  `TABLE_COMMENT`     `comment`
            FROM    `information_schema`.`TABLES`
            WHERE   `TABLE_SCHEMA`      = '{$this->config->dbname}'
                    AND `TABLE_NAME`    = '{$this->table}'
        ";
        $table_comment  = DB::instance( $this->schema, $this->config )
            ->execute( $sql )
            ->one();

        if ( !$table_comment )
            throw new \Exception( "Nenhuma informação encontrada para a tabela [$this->table] no banco [$this->config->dbname]." );

        $table_comment  = (object) $table_comment;
        $this->label    = lcfirst( $table_comment->comment );
    }

    /**
     * Obtém as informações da tabela.
     */
    protected function getTableInfos()
    {
        $sql                = "
            SELECT  *
            FROM    `information_schema`.`COLUMNS`
            WHERE   `TABLE_SCHEMA`   = '{$this->config->dbname}'
                    AND `TABLE_NAME` = '{$this->table}'
        ";
        $this->tableColumns = DB::instance( $this->schema )
            ->execute( $sql )
            ->all();

        if ( $this->tableColumns )
            return;

        throw new \Exception( "Nenhuma informação encontrada para a tabela [$this->table] no banco [$this->config->dbname]." );
    }

    /**
     * Preenche as propriedades do DTO com os dados da tabela.
     */
    protected function fillProps()
    {
        $pri_key    = null;
        $types      = [
            'date'          => 'date',
            'time'          => 'time',
            'datetime'      => 'datetime',

            'tinyint'       => 'int',
            'smallint'      => 'int',
            'mediumint'     => 'int',
            'int'           => 'int',
            'bigint'        => 'int',
            'bit'           => 'int',
            'bolean'        => 'int',
            'year'          => 'int',
            'timestamp'     => 'int',
            
            'decimal'       => 'float',
            'float'         => 'float',
            'double'        => 'float',
            'real'          => 'float',
            
            'char'          => 'str',
            'varchar'       => 'str',
            'tinytext'      => 'str',
            'text'          => 'str',
            'mediumtext'    => 'str',
            'longtext'      => 'str',
        ];

        foreach ( $this->tableColumns as $data )
        {
            $data   = (object) $data;
            $name   = $data->COLUMN_NAME;
            $prop   = (object) [];

            if ( $data->COLUMN_COMMENT )
                $prop->desc         = $data->COLUMN_COMMENT;

            if ( isset( $types[ $data->DATA_TYPE ] ) )
                $prop->type         = $types[ $data->DATA_TYPE ];

            if ( isset( $data->COLUMN_DEFAULT ) && $data->COLUMN_DEFAULT != 'NULL' )
                $prop->default      = preg_replace( '@(^[\'\"]|[\'\"]$)@', '', $data->COLUMN_DEFAULT );

            if ( $data->IS_NULLABLE == 'NO' )
                $prop->required     = 1;

            if ( $data->CHARACTER_MAXIMUM_LENGTH )
                $prop->maxlength    = $data->CHARACTER_MAXIMUM_LENGTH;

            if ( strpos( $data->COLUMN_TYPE, 'unsigned' ) )
                $prop->unsigned     = 1;

            if ( $data->COLUMN_KEY == 'PRI' )
                $prop->priKey       = 1;
            
            $this->props[ $name ] = $prop;
        }
    }

    /**
     * Prepara a propriedades $cols para injetar na trait.
     */
    protected function fillColumns()
    {
        foreach ( $this->props as $prop => $data )
        {
            $default        = 'null';

            if ( isset( $data->default ) )
            {
                $default    = is_numeric( $data->default )
                    ? $data->default
                    : "'" . $data->default . "'";
            }

            $col            = [];
            $col[]          = "    ";
            $col[]          = "    /**";
            $col[]          = "     * {$data->desc}.";
            $col[]          = "     */";
            $col[]          = "    public $$prop = $default;";
            $col[]          = "    ";
            $this->cols    .= implode( PHP_EOL, $col );
        }
    }

    /**
     * Prepara a propriedades $cols para injetar na trait.
     */
    protected function prepareProps()
    {
        $cols       = [];
        $void_props = [ 'priKey', 'required', 'unsigned' ];

        foreach ( $this->props as $colname => $props )
        {
            $col    = [];
            
            foreach ( $props as $prop => $val )
            {
                if ( in_array( $prop, $void_props ) )
                    $col[] = "    #[$prop]";
                
                elseif ( is_numeric( $val ) )
                    $col[] = '    #[' . $prop . "( $val )]";

                else
                    $col[] = '    #[' . $prop . "( '$val' )]";
            }

            $col[]  = "    public $$colname;";
            $cols[] = implode( PHP_EOL, $col );
        }

        $cols       = implode( PHP_EOL . PHP_EOL, $cols );

        return $cols;
    }
}
