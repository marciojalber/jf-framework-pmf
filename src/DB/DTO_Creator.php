<?php

namespace JF\DB;

use JF\Config;
use JF\DB\DB;
use JF\Exceptions\ErrorException as Error;
use JF\FileSystem;

/**
 * Classe para manipulação de registros de tabelas.
 */
class DTO_Creator
{




















    /**
     * Schema de acesso.
     */
    public $schemas;

    /**
     * Schema de acesso.
     */
    public $schema;

    /**
     * Nome das tabelas.
     */
    public $tables;

    /**
     * Nome das classes.
     */
    public $classnames;

    /**
     * Conteúdo dos DTOs.
     */
    public $dtos;

    /**
     * Inicializa a instância do objeto.
     */
    public static function init( $schema )
    {
        if ( !$schema )
            die( "Esquema de conexão não informado." );

        if ( !Config::get( 'db.schemas.' . $schema ) )
            die( "Esquema de conexão não encontrado." );

        $instance               = new self();
        $instance->schema       = $schema;

        return $instance;
    }

    /**
     * Cria um DTO.
     */
    public function create()
    {
        $this->db           = DB::instance( $this->schema );
        $this->dbname       = $this->db->config( 'dbname' );
        $this->columns      = (object) [];
        $this->dtos         = (object) [];
        $this->dtoParent    = '\\Core\\DB\\DTO';

        if ( class_exists( 'App\\DTO' ) && ( new \App\DTO() ) instanceof \Core\DB\DTO )
            $this->dtoParent    = '\\App\\DTO';

        $this->setSavePath();
        $this->getTables();
        $this->setDTONames();
        $this->captureSchemasDB();
        $this->getRelations();

        foreach ( $this->tables as $name => $table )
            $this->getColumns( $name );

        foreach ( $this->tables as $table )
            $this->setContent( $table );

        $this->createFolder();
        $this->removeOlderDTOs();
        $this->saveClasses();
    }

    /**
     * Obtém o comentário da tabela.
     */
    public function setSavePath()
    {
        $dtopath        = str_replace( '\\', '/', DIR_APP ) . '/DTO';
        $this->savepath = $dtopath . '/' . ucfirst( $this->schema );
    }

    /**
     * Obtém as tabelas.
     */
    public function getTables()
    {
        $sql            = "
            SELECT      `TABLE_NAME`    `table`,
                        `TABLE_COMMENT` `comment`
            FROM        `INFORMATION_SCHEMA`.`TABLES`
            WHERE       `TABLE_SCHEMA`    = '{$this->dbname}'
        ";
        $this->tables   = (object) $this
            ->db
            ->execute( $sql )
            ->all( null, 'table' );
    }

    /**
     * Define o nome do DTO das tabelas.
     */
    public function setDTONames()
    {
        foreach ( $this->tables as $name => $table )
        {
            $name           = self::entityName( $name );
            $table->entity  = $name;
            $table->dto     = $name . '__DTO';
            $table->pk      = null;
        }
    }

    /**
     * Obtém as colunas da tabela.
     */
    public function getColumns( $table )
    {
        $sql            = "
            SELECT      *
            FROM        `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE       `TABLE_SCHEMA`    = '{$this->dbname}'
                        AND `TABLE_NAME`  = '{$table}'
        ";
        $columns        = $this->db->execute( $sql )->all();

        foreach ( $columns as $column )
        {
            $name       = $column->COLUMN_NAME;
            $data       = (object) [
                'desc'  => $column->COLUMN_COMMENT,
                'type'  => $column->DATA_TYPE,
            ];

            if ( $column->COLUMN_KEY == 'PRI' )
                $this->tables->$table->pk   = $name;

            if ( $column->COLUMN_DEFAULT && $column->COLUMN_DEFAULT != 'NULL' )
                $data->default              = preg_replace( '@(^\'|\'$)@', '', $column->COLUMN_DEFAULT );

            if ( $column->CHARACTER_MAXIMUM_LENGTH && $column->DATA_TYPE != 'enum' )
                $data->maxlength            = $column->CHARACTER_MAXIMUM_LENGTH;

            if ( in_array( $column->DATA_TYPE, [ 'enum', 'set' ] ) )
            {
                $data->options              = '[' . preg_replace(
                    "@^(enum|set)\(|\)$@",
                    '',
                    $column->COLUMN_TYPE
                ) . ']';
            }

            if ( $column->IS_NULLABLE == 'NO' && $column->COLUMN_KEY != 'PRI' )
                $data->required             = true;

            if ( substr( $column->COLUMN_TYPE, -8 ) == 'unsigned' )
                $data->unsigned             = true;

            if ( !isset( $this->columns->$table ) )
                $this->columns->$table      = (object) [];

            $this->columns->$table->$name   = $data;
        }
    }

    /**
     * Captura as relaçõe do DTO.
     */
    public function getRelations()
    {
        $sql            = "
            SELECT      `ID` `id`,
                        null                                        `schemaChild`,
                        REGEXP_REPLACE( `FOR_NAME`, '\/.*', '' )    `dbChild`,
                        REGEXP_REPLACE( `FOR_NAME`, '.*\/', '' )    `tbChild`,
                        null                                        `fk`,
                        null                                        `schemaParent`,
                        REGEXP_REPLACE( `REF_NAME`, '\/.*', '' )    `dbParent`,
                        REGEXP_REPLACE( `REF_NAME`, '.*\/', '' )    `tbParent`
            FROM        `INFORMATION_SCHEMA`.`INNODB_SYS_FOREIGN`
            WHERE       REGEXP_REPLACE( ID, '\/.*', '' ) IN (:databases)
        ";
        $databases      = array_keys( (array) $this->schemas );
        $data           = [ 'databases' => $databases ];
        $this->rels     = (object) $this
            ->db
            ->execute( $sql, $data )
            ->all( null, 'id' );
        
        $sql            = "
            SELECT      `ID` `id`,
                        `FOR_COL_NAME` `fk`,
                        `REF_COL_NAME` `pk`
            FROM        `INFORMATION_SCHEMA`.`INNODB_SYS_FOREIGN_COLS`
            WHERE       REGEXP_REPLACE( ID, '\/.*', '' ) IN (:databases)
        ";
        $refs           = $this
            ->db
            ->execute( $sql, $data )
            ->all( null, 'id' );

        foreach ( $refs as $ref )
        {
            $id                             = $ref->id;
            $db_child                       = $this->rels->$id->dbChild;
            $db_parent                      = $this->rels->$id->dbParent;
            $this->rels->$id->fk            = $ref->fk;
            $this->rels->$id->pk            = $ref->pk;
            $this->rels->$id->schemaChild   = $this->schemas->$db_child;
            $this->rels->$id->schemaParent  = $this->schemas->$db_parent;
            
            unset( $this->rels->$id->REF_NAME );
        }
    }

    /**
     * Define o conteúdo dos DTOs.
     */
    public function setContent( $table )
    {
        $__         = '        ';
        $___        = '            ';
        $namespace  = 'App\\DTO\\' . ucfirst( $this->schema );
        $columns    = [''];
        $uses       = [];

        $uses       = $uses
            ? "\n\n" . implode( PHP_EOL, $uses )
            : '';

        foreach ( $this->columns->{$table->table} as $name => $data )
        {
            $columns[] = "$__'" . str_pad( $name . "'", 22, ' ' ) . " => [";
            
            foreach ( $data as $key => $value )
            {
                $val_str    = "$___'" . str_pad( $key . "'", 18, ' ' ) . ' => ';
                $val_str   .= !is_string( $value ) || $key == 'options'
                    ? $value . ','
                    : "'$value',";
                $columns[]  = $val_str;
            }

            $columns[] = "$__],";
        }
        $columns    = implode( PHP_EOL, $columns ) . PHP_EOL . str_repeat( ' ', 4 );
        $trait_path = DIR_BASE . '/' . $namespace . '\\' . $table->entity . '__Trait.php';
        $open_class = file_exists( $trait_path )
            ? '{' . PHP_EOL . '    use ' . $table->entity . '__Trait;' . PHP_EOL
            : '{';
        $now        = date( 'Y-m-d H:i:s' );
        $content    = <<<CONTENT
<?php

namespace $namespace;{$uses}

/**
 * DTO of the table '{$table->table}': {$table->comment}.
 * 
 * @created_at {$now}
 */
class {$table->dto} extends {$this->dtoParent}
{$open_class}
    /**
     * Connection schema.
     */
    protected static \$schema    = '{$this->schema}';

    /**
     * Table name.
     */
    protected static \$table     = '{$table->table}';

    /**
     * Columns of the table.
     */
    protected static \$columns   = [$columns];

CONTENT;

        if ( $table->pk )
        {
            $content .= <<<CONTENT

    /**
     * Returns the primary key of the table.
     */
    public static function primaryKey()
    {
        return '{$table->pk}';
    }

CONTENT;
        }

        $len_basepath   = strlen( DIR_BASE );
        
        $getters        = [];

        foreach ( $this->rels as $rel )
        {
            if ( $rel->tbChild != $table->table )
                continue;

            if ( empty( $rel->schemaParent ) )
                continue;

            $name       = self::entityName( $rel->tbParent );
            $name2      = $name;

            if ( in_array( $name, $getters ) )
                $name2  .= '_' . $rel->fk;

            $getters[]   = $name2;
            $dto_ref    = $rel->schemaParent != $this->dbname
                ? '\\App\\DTO\\' . ucfirst( $rel->schemaParent ) . '\\' . $name . '__DTO'
                : $name . '__DTO';
            $fn_name    = 'get' . $name2;
            $content   .= <<<CONTENT

    /**
     * Returns the parent record from the '{$name}' by '$rel->fk'.
     */
    protected function $fn_name()
    {
        return $dto_ref::find( \$this->{$rel->fk} );
    }

CONTENT;
        }
        
        $rels = [];

        foreach ( $this->rels as $rel )
        {
            if ( $rel->dbParent != $this->dbname || $rel->tbParent != $table->table )
                continue;

            $name       = self::entityName( $rel->tbChild );
            $name2      = $name;

            if ( in_array( $name, $rels ) )
                $name2  .= '_' . $rel->fk;

            $rels[]     = $name2;
            $dto_ref    = $rel->dbChild != $this->dbname
                ? '\\App\\DTO\\' . ucfirst( $rel->schemaChild ) . '\\' . $name . '__DTO'
                : $name . '__DTO';
            $fn_name    = 'collect' . $name2;
            $content   .= <<<CONTENT

    /**
     * Returns the children from the '{$name}' by '{$rel->fk}'.
     */
    protected function $fn_name()
    {
        return $dto_ref::findAll( \$this->{$rel->pk}, '{$rel->fk}' );
    }

CONTENT;
        }

        $content .= '}' . PHP_EOL;

        $this->dtos->{$table->table} = $content;
    }

    /**
     * Cria a pasta do esquema.
     */
    public function createFolder()
    {
        if ( !FileSystem::md( $this->savepath ) )
            die( "Não foi possível criar a pasta do DTO." );
    }

    /**
     * Salva as classes DTO.
     */
    public function removeOlderDTOs()
    {
        $dir = new \FilesystemIterator( $this->savepath );
        
        foreach ( $dir as $item )
        {
            $filename = $item->getFilename();

            if ( substr( $filename, -9, -4 ) == '__DTO' )
                unlink( $item->getPathname() );
        }
    }

    /**
     * Salva as classes DTO.
     */
    public function saveClasses()
    {
        foreach ( $this->tables as $table )
        {
            $filename   = "{$this->savepath}/{$table->dto}.php";

            file_put_contents( $filename, $this->dtos->{$table->table} );
        }
    }

    /**
     * Captura os esquemas dos bancos-de-dados.
     */
    public function captureSchemasDB()
    {
        $schemas        = Config::get( 'db.schemas' );
        $this->schemas  = (object) [];

        foreach ( $schemas as $name => $schema )
            $this->schemas->{$schema->dbname} = $name;
    }

    /**
     * Retorna o nome formatado da entidade.
     */
    public function entityName( $name )
    {
        $name    = ucfirst( $name );
        $name    = preg_replace_callback( '@_(.)@', function( $matches ) {
            return '_' . ucfirst( $matches[ 1 ] );
        }, $name );
        
        return $name;
    }
}

DTO_Creator::init( $args->s )->create();
