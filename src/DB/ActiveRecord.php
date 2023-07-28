<?php

namespace JF\DB;

use JF\Config;

/**
 * Classe que gerencia acessos a dados das tabelas.
 */
class ActiveRecord extends \StdClass
{
    use ActiveRecord_Props;
    use ActiveRecord_Infos;
    use ActiveRecord_Read;
    use ActiveRecord_Write;
    use ActiveRecord_Handler;

    const PREFIX    = 'App\\Models\\ActiveRecords\\';
    const SUFIX     = '__ActiveRecord';

    /**
     * Cria uma instância do registro.
     */
    public function __construct( $data = [], $unsafe = false )
    {
        $props          = static::$props;
        $data           = (array) $data;

        array_walk(
            $props,
            function( &$prop )
            {
                $prop = null;
            }
        );

        $this->values   = $props;

        if ( $data )
        {
            $this->set( $data, $unsafe );
        }

        $this->status   = 'created';
    }

    /**
     * Inicializa uma instância do registro a partir de valores recuperados do banco-de-dados.
     */
    public static function init( SQL $sql_bilder )
    {
        $record         = new static();
        $record->values = $sql_bilder->values();
        $record->status = 'saved';
        
        return $record;
    }

    /**
     * Retorna o nome do esquema de conexão com o banco de dados.
     */
    public static function db()
    {
        return DB::instance( static::schemaName() );
    }


    /**
     * Retorna a última mensagem de erro ocorrida no banco-de-dados do esquema do ActiveRecord.
     */
    public static function lastError()
    {
        return DB::instance( static::schemaName() )->lastError();
    }

    /**
     * Prepara um SQL Obj para construir uma consulta à tabela do registro.
     */
    public static function prepare( array $opts = [] )
    {
        return SQL::create( get_called_class(), $opts );
    }
}
