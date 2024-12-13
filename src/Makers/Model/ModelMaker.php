<?php

namespace JF\Makers\Model;

use JF\Config;
use JF\DB\DB;
use JF\FileSystem\Dir;
use JF\Exceptions\ErrorException as Error;
use JF\Utils;

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
     * Nome da tabela.
     */
    protected $table;

    /**
     * Configurações de conexão.
     */
    protected $config;

    /**
     * Opções informadas para criação de models.
     */
    protected $opts;

    /**
     * Comentários da tabela.
     */
    protected $label;

    /**
     * Propriedades da tabela.
     */
    protected $tableColumns;

    /**
     * Checks da tabela.
     */
    protected $tableChecks;

    /**
     * Checks da tabela.
     */
    protected $tableLessGreater = [];

    /**
     * Patterns da tabela.
     */
    protected $tablePatterns    = [];

    /**
     * Maxlength da tabela.
     */
    protected $tableMinlength   = [];

    /**
     * Relacionamentos com outras tabelas.
     */
    protected $tableBinds       = [];

    /**
     * Estrutura das propriedades do DTO.
     */
    protected $props            = [];

    /**
     * Propriedades do DTO.
     */
    protected $cols             = '';

    /**
     * Chave primária.
     */
    protected $priKey           = 'id';

    /**
     * Resultado da criação.
     */
    protected $result           = [];

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
    public static function init( $schema, $table, $opts = [] )
    {
        return new self( $schema, $table, $opts );
    }

    /**
     * Método construtor.
     */
    public function __construct( $schema, $table, $opts = [] )
    {
        if ( !$schema )
            throw new Error( "Esquema de acesso ao banco-de-dados não informado." );
            
        if ( !$table && empty( $opts[ 'allTables' ] ) )
            throw new Error( "Tabela do banco-de-dados não informado." );
        
        $this->schema           = $schema;
        $this->table            = $table;
        $this->opts             = $opts;
        $this->config           = DB::instance( $this->schema )->config();
        
        $this->tableChecks      = (object) [];
        $this->tableLessGreater = (object) [];
        $this->tablePatterns    = (object) [];
        $this->tableMinlength   = (object) [];
    }

    /**
     * Executa a criação do DTO.
     */
    public function create( $dirname = null )
    {
        $tables = $this->getTables();

        foreach ( $tables as $table )
        {
            $this->tableBinds   = [];
            $this->table        = $table;

            $this->getTableComment();
            $this->getTableInfos();
            $this->getTableBinds();
            $this->getTableChecks();
            $this->fillProps();
            $this->convertProps();
            $this->makeFiles( $dirname );
        }

        return $this->result;
    }

    /**
     * Obtém as tabelas para analisar.
     */
    protected function getTables()
    {
        if ( empty( $this->opts[ 'allTables' ] ) )
            return [$this->table];

        $sql        = "
            SELECT  `TABLE_NAME` `table`
            FROM    `information_schema`.`TABLES`
            WHERE   `TABLE_SCHEMA`      = '{$this->config->dbname}'
        ";
        $tables     = DB::instance( $this->schema )
            ->execute( $sql )
            ->indexBy( 'table' )
            ->all();

        return array_keys( $tables );
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
        $table_comment  = DB::instance( $this->schema )
            ->execute( $sql )
            ->one();

        if ( !$table_comment )
            throw new \Exception( "Nenhuma informação encontrada para a tabela [{$this->table}] no banco [{$this->config->dbname}]." );

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
            WHERE   `TABLE_SCHEMA`      = '{$this->config->dbname}'
                    AND `TABLE_NAME`    = '{$this->table}'
        ";
        $this->tableColumns = DB::instance( $this->schema )
            ->execute( $sql )
            ->all();

        if ( $this->tableColumns )
            return;

        throw new \Exception( "Nenhuma informação encontrada para a tabela [$this->table] no banco [$this->config->dbname]." );
    }

    /**
     * Obtém os vínculos entre tabelas.
     */
    protected function getTableBinds()
    {
        $sql        = "
            SELECT  `c`.`FOR_COL_NAME`                              `fk`,
                    REGEXP_REPLACE( `t`.`REF_NAME`, '.*\/', '' )    `table`,
                    `c`.`REF_COL_NAME`                              `col`
            FROM    `information_schema`.`INNODB_SYS_FOREIGN`       `t`
            JOIN    `information_schema`.`INNODB_SYS_FOREIGN_COLS`  `c`
            USING   ( `ID` )
            WHERE   REGEXP_REPLACE( `t`.`FOR_NAME`, '\/.*', '' )        = '{$this->config->dbname}'
                    AND REGEXP_REPLACE( `t`.`FOR_NAME`, '\/.*', '' )    = REGEXP_REPLACE( `t`.`REF_NAME`, '\/.*', '' )
                    AND REGEXP_REPLACE( `t`.`FOR_NAME`, '.*\/', '' )    = '$this->table'
        ";
        $result = DB::instance( $this->schema )
            ->execute( $sql )
            ->all();

        foreach ( $result as $item )
        {
            $fk     = $item[ 'fk' ];
            unset( $item[ 'fk' ] );
            
            $this->tableBinds[ $fk ] = $item;
        }
    }

    /**
     * Obtém os checks da tabela.
     */
    protected function getTableChecks()
    {
        $sql                = "
            SELECT  `CHECK_CLAUSE`      `clause`
            FROM    `information_schema`.`CHECK_CONSTRAINTS`
            WHERE   `CONSTRAINT_SCHEMA` = '{$this->config->dbname}'
                    AND `TABLE_NAME`    = '{$this->table}'
        ";
        $result     = DB::instance( $this->schema )
            ->execute( $sql )
            ->all();

        if ( !$result )
            return;

        $in_pattern     = '@` in \(@i';
        $len_pattern    = '@^([A-Za-z]+_)?LENGTH\(.*?\) *>=@i';
        $lg_pattern     = '@` (>|>=|<|<=) `@';
        $regex_pattern  = '@`.*?` REGEXP@i';

        foreach ( $result as $item )
        {
            if ( preg_match( $in_pattern, $item[ 'clause' ] ) )
                $this->captureOpts( $item[ 'clause' ] );

            if ( preg_match( $len_pattern, $item[ 'clause' ] ) )
                $this->captureMinlength( $item[ 'clause' ] );

            if ( preg_match( $regex_pattern, $item[ 'clause' ] ) )
                $this->capturePattern( $item[ 'clause' ] );

            if ( preg_match( $lg_pattern, $item[ 'clause' ] ) )
                $this->captureLessGreater( $item[ 'clause' ] );
        }
    }

    /**
     * Captura as opções de entrada.
     */
    protected function captureOpts( $val )
    {
        $parts  = preg_split( '@ in @i', $val );
        $key    = preg_replace( '@`@i', '', $parts[0] );
        $opts   = preg_replace( '@\( *\'| *\'\)@i', '', $parts[1] );
        $opts   = preg_split( "@', *'@", $opts );
        
        $this->tableChecks->$key = $opts;
    }

    /**
     * Captura o comprimento mínimo de caracteres.
     */
    protected function captureMinlength( $val )
    {
        $val    = preg_replace(
            '@^([A-Za-z]+_)?LENGTH\(`(.*?)`\) *>= *`?(.*)`?@i',
            '$2|$3',
            $val
        );
        $parts  = explode( '|', $val );
        $key    = $parts[0];
        $val    = $parts[1];
        
        $this->tableMinlength->$key = $val;
    }

    /**
     * Captura o padrão de expressão regular.
     */
    protected function capturePattern( $val )
    {
        $val    = preg_replace(
            '@`(.*?)` REGEXP \'(.*)\'@i',
            '$1|$2',
            $val
        );
        $parts  = explode( '|', $val );
        $key    = $parts[0];
        $val    = $parts[1];
        
        $this->tablePatterns->$key = $val;
    }

    /**
     * Preenche as propriedades do DTO com os dados da tabela.
     */
    protected function captureLessGreater( $val )
    {
        $op     ??= preg_match( '@` > `@', $val )
            ? 'greaterThan'
            : null;
        $op     ??= preg_match( '@` >= `@', $val )
            ? 'greaterEqThan'
            : null;
        $op     ??= preg_match( '@` < `@', $val )
            ? 'lessThan'
            : null;
        $op     ??= preg_match( '@` <= `@', $val )
            ? 'lessEqThan'
            : null;
        $parts  = preg_split(
            '@` (>|>=|<|<=) `@',
            preg_replace( '@^`|`$@', '', $val )
        );
        $key    = $parts[0];
        $target = $parts[1];
        
        if ( empty( $this->tableLessGreater->$key ) )
            $this->tableLessGreater->$key = [];

        $this->tableLessGreater->$key[] = [ $op, $target ];
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
            'set'           => 'str',
            'enum'          => 'str',
        ];
        $trimamble      = [ 'char', 'varchar', 'tinytext', 'set', 'enum' ];
        $auto_inc       = null;
        $pri_keys       = [];
        $uni_keys       = [];
        $this->props    = [];
        $props          = &$this->props;

        foreach ( $this->tableColumns as $data )
        {
            $data   = (object) $data;
            $name   = $data->COLUMN_NAME;
            $prop   = (object) [
                'collection' => [],
            ];

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

            if ( in_array( $types[ $data->DATA_TYPE ], $trimamble ) )
                $prop->trim         = 1;

            if ( in_array( $data->DATA_TYPE, ['enum', 'set'] ) )
            {
                $prop->opts         = preg_replace(
                    "@^enum\( *\'|^set\( *\'|\' *\)$@i",
                    '',
                    $data->COLUMN_TYPE
                );
                $prop->opts         = preg_split( "@', *'@", $prop->opts );
            }

            $opts                   = isset( $this->tableChecks->$name )
                ? $this->tableChecks->$name
                : null;

            if ( isset( $opts ) && empty( $prop->opts ) )
                $prop->opts         = [];
            
            if ( isset( $opts ) )
            {
                $prop->opts         = array_merge( $opts, $prop->opts );
                $prop->opts         = array_unique( $prop->opts );
            }
            
            if ( !empty( $prop->opts ) )
                $prop->opts         = "['" . implode( "','", $prop->opts ) . "']";

            if ( $data->EXTRA == 'auto_increment' )
                $auto_inc           = $name;

            if ( $data->COLUMN_KEY == 'PRI' )
                $pri_keys[]         = $name;

            if ( $data->COLUMN_KEY == 'PRI' )
                $uni_keys[]         = $name;

            if ( !empty( $this->tableLessGreater->$name ) )
            {
                foreach ( $this->tableLessGreater->$name as $val )
                    $prop->collection[] = "{$val[0]}( '$val[1]' )";
            }

            if ( !empty( $this->tableMinlength->$name ) )
            {
                $val                = $this->tableMinlength->$name;
                $prop->collection[] = is_numeric( $val )
                    ? "minlength( $val )"
                    : "minlength( '$val' )";
            }

            if ( !empty( $this->tablePatterns->$name ) )
            {
                $val                = $this->tablePatterns->$name;
                $prop->collection[] = "pattern( '$val' )";
            }
            
            $props[ $name ]         = $prop;
        }

        if ( count( $pri_keys ) == 1 )
        {
            $key                    = $pri_keys[0];
            $props[ $key ]->priKey  = 1;

            if ( isset( $props[ $key ]->required ) )
                unset( $props[ $key ]->required );

            return;
        }
        
        if ( count( $uni_keys ) == 1 )
        {
            $key                    = $uni_keys[0];
            $props[ $key ]->priKey  = 1;

            if ( isset( $props[ $key ]->required ) )
                unset( $props[ $key ]->required );
            
            return;
        }

        if ( $auto_inc )
        {
            $key                    = $auto_inc;
            $props[ $key ]->priKey  = 1;
            
            if ( isset( $props[ $key ]->required ) )
                unset( $props[ $key ]->required );

            return;
        }

        if ( $pri_keys )
        {
            $key                    = $pri_keys[0];
            $props[ $key ]->priKey  = 1;

            if ( isset( $props[ $key ]->required ) )
                unset( $props[ $key ]->required );

            return;
        }
    }

    /**
     * Converte as propriedades em $cols para injetar no Model.
     */
    protected function convertProps()
    {
        $cols           = [];
        $void_props     = [ 'priKey', 'required', 'unsigned' ];
        
        foreach ( $this->props as $colname => $props )
        {
            $col        = [];
            
            foreach ( $props as $prop => $val )
            {
                if ( $prop == 'default' )
                    continue;
                
                if ( $prop == 'collection' )
                    foreach ( $val as $item )
                        $col[] = "    #[$item]";
                
                elseif ( in_array( $prop, $void_props ) )
                    $col[]  = "    #[$prop]";
                
                elseif ( is_numeric( $val ) || $prop == 'opts' )
                    $col[]  = '    #[' . $prop . "($val)]";

                else
                    $col[]  = '    #[' . $prop . "('$val')]";
            }

            $default        = 'null';

            if ( isset( $props->default ) )
            {
                $default    = is_numeric( $props->default )
                    ? $props->default
                    : "'" . $props->default . "'";
            }

            $col[]          = "    public $$colname = $default;";
            $cols[]         = implode( PHP_EOL, $col );
        }

        $cols               = implode( PHP_EOL . PHP_EOL, $cols );

        $this->props = $cols;
    }

    /**
     * Cria os arquivos de Model e Trait.
     */
    protected function makeFiles( $dirname )
    {
        $ns         = self::getNSFromSchema( $this->schema, $this->table );
        $name       = self::getNameFromTable( $this->table );
        $classname  = isset( $this->opts[ 'modelSufix' ] )
            ? $name . $this->opts[ 'modelSufix' ]
            : $name . '__Model';
        $traitname  = $name . '__Tasks';
        // $cols       = $this->prepareColumns();
        $hoje       = date( 'd/m/Y, à\s H:i:s' );
        $dirname    = !$dirname
            ? DIR_BASE . '/' . str_replace( '\\', '/', $ns )
            : $dirname;
        $tb_binds   = Utils::varExport( $this->tableBinds );
        $tb_binds   = preg_replace( '@' . PHP_EOL . '@', PHP_EOL .'    ', $tb_binds  );
        $classfile  = $dirname . '/' . $classname . '.php';
        $traitfile  = $dirname . '/' . $traitname . '.php';
        $class      = file_get_contents( __DIR__ . '/TemplateModel.php' );
        $class      = str_replace(
            ['$ns', '$_table', '$hoje', '$classname', '$traitname', '$_schema', '$_props','$_fks'],
            [$ns, $this->table, $hoje, $classname, $traitname, $this->schema, $this->props, $tb_binds],
            $class
        );
        $trait      = file_get_contents( __DIR__ . '/TemplateTasks.php' );
        $trait      = str_replace(
            ['$ns', '$dto', '$hoje', '$traitname'],
            [$ns, $classname, $hoje, $traitname],
            $trait
        ); //

        $makes      = [];

        if ( !file_exists( $dirname ) )
        {
            Dir::makeDir( $dirname );
            $makes[] = $dirname;
        }

        file_put_contents( $classfile, $class );
        $makes[]     = $classfile;

        if ( !file_exists( $traitfile ) )
        {
            file_put_contents( $traitfile, $trait );
            $makes[] = $traitfile;
        }

        $this->result[] = (object) [
            'Model'     => $classname,
            'makes'     => $makes
                ? $makes
                : null,
        ];
    }
}
