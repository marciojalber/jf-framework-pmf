<?php

namespace JF;

use JF\Config;
use JF\DB\DB;
use JF\DB\DB_Backup;
use JF\Doc\DocParser;
use JF\Exceptions\ErrorException as Error;
use JF\FileSystem\Dir;
use JF\Log;
use JF\System;
use JF\Messager;

/**
 * Classe que cuida da execução de rotinas.
 */
class RoutineHandler extends \StdClass
{
    /**
     * Instância do esquema para registrar as etapas.
     */
    protected $registerDbInstance;

    /**
     * Data / hora do início da execução da rotina.
     */
    protected $registerStart;
    
    /**
     * Verifica se a rotina deve ser executada.
     */
    public function list( $only_expired = false )
    {
        $this->getExecution();
        $this->routines     = (object) [];
        $this->now          = new \DateTime();
        $this->onlyExpired  = $only_expired;

        $namespaces         = Config::get( 'namespaces' );
        $this->namespace    = array_search( 'App/Routines', (array) $namespaces );

        $this->explore( DIR_ROUTINES );

        return $this->routines;
    }

    /**
     * Aplica as regras de negócio da operação.
     */
    private function getExecution()
    {
        $schema             = Config::get( 'logs.executions.schema' );
        $table1             = Config::get( 'logs.executions.table' );
        $table2             = Config::get( 'logs.executions.force' );
        $sql                = "
            SELECT  `routine`,
                    `date`,
                    `time`
            FROM    `$table1` `a`
            JOIN    (
                SELECT      MAX( `id` ) `id`
                FROM        `$table1`
                WHERE       `routine` NOT IN (
                    SELECT  `routine`
                    FROM    `$table2`
                )
                GROUP BY    `routine`
            ) `b`
            USING( `id` )
        ";
        $items              = DB::instance( $schema )
            ->execute( $sql )
            ->indexBy( 'routine' )
            ->all();

        $this->executions   = json_decode( json_encode( $items ) );
    }

    /**
     * Aplica as regras de negócio da operação.
     */
    private function explore( $path )
    {
        $len_basepath   = strlen( DIR_BASE ) + 1;
        $len_routpath   = strlen( DIR_ROUTINES ) + 1;
        $obj_path       = new \FilesystemIterator( $path );

        foreach ( $obj_path as $item )
        {
            if ( $item->isDir() )
            {
                self::explore( $item->getPathname() );
                continue;
            }

            $pathname           = $item->getPathName();
            $pathname           = str_replace( '\\', '/', $pathname );
            $alias              = str_replace( '/', '.', substr( $pathname, $len_routpath, -13 ) );
            $routine_class      = substr( $pathname, $len_basepath, -4 );
            $routine_class      = $this->namespace
                ? $this->namespace . substr( $routine_class, 12 )
                : $namespace . substr( $routine_class, strlen( $local ) );
            $routine_class      = str_replace( '/', '\\', $routine_class );

            if ( !class_exists( $routine_class ) )
            {
                $msg            = Messager::get(
                    'routine',
                    'routine_not_found',
                    $routine_class
                );
                throw new Error( $msg );
            }

            $routine        = new $routine_class;
            $execution      = isset( $this->executions->$alias )
                ? $this->executions->$alias
                : null;

            if ( $execution )
                $execution  = new \DateTime( $execution->date . ' ' . $execution->time );

            $expired        = $routine->active()
                ? $routine->expired( $execution )
                : false;

            if ( $this->onlyExpired && !$expired )
                continue;

            $this->routines->$alias = (object) array_merge(
                ['class' => $routine_class , 'expired'  => $expired ],
                $routine->props()
            );
        }
    }
}
