<?php

namespace JF;

use JF\DB\DB;
use JF\DB\SQL;
use JF\Config;
use JF\HTTP\Request;
use JF\HTTP\ControllerParser;

/**
 * Classe que salva e recupera logs.
 *
 * @updated 05/07/2023 16:34
 * @author  Márcio Jalber <marciojalber@gmail.com>
 * @since   01/04/2015
 */
class Log
{
    /**
     * Armazena o erro ocorrido.
     */
    private $error  = '';

    /**
     * Salva o texto do log.
     */
    private $text   = '';

    /**
     * Indica se o log já foi salvo.
     */
    private $saved  = false;

    /**
     * Indica se o log já foi salvo.
     */
    private $context  = null;

    /**
     * .
     */
    private $dbTarget  = null;

    /**
     * .
     */
    private $schema  = null;

    /**
     * .
     */
    private $table  = null;

    /**
     * .
     */
    private $isRoutine  = null;

    /**
     * .
     */
    private $dataLog  = null;

    /**
     * Método para registrar um log.
     * 
     * @return null
     */
    public static function register( $error, $context, Array $options = array() )
    {
        $log_instance               = new self();
        $log_instance->error        = $error;
        $log_instance->context      = $context;
        $log_instance->dbTarget     = Config::get( 'logs.error.target' ) == 'db';
        $log_instance->schema       = Config::get( 'logs.error.schema' );
        $log_instance->table        = Config::get( 'logs.error.table' );
        $log_instance->isRoutine    = $context === 'routine';

        $log_instance->canSaveLog();

        if ( $context === 'routine' )
            return $log_instance->saveRoutineLog( $options );

        $log_instance->makeLogRecord();
        $log_instance->makeLogText();
        $log_instance->saveLogFeature();
        $log_instance->saveLogDate();
        $log_instance->saveLogDB();
    }

    /**
     * Método para escrever o log.
     *
     * @return  null
     */
    protected function canSaveLog()
    {
        if ( !$this->isRoutine && $this->dbTarget && ( !$this->schema || !$this->table ) )
            exit( "Esquema e/ou tabela para salvar log de erros não informado." );

        $dir_logs   = $this->logPath();
        $text_error = 'Estamos sem permissão para escrever na pasta "%s"';
        
        if ( !file_exists( $dir_logs ) )
            return mkdir( $dir_logs, 0777, true );

        if ( !is_writable( $dir_logs ) )
            exit( sprintf( $text_error, $dir_logs ) );
    }

    /**
     * Método para escrever o log.
     *
     * @return  null
     */
    protected function makeLogRecord()
    {
        // Prepara as variáveis básicas
        $filename       = isset( $this->error[ 'file' ] )
            ? str_replace( '\\', '/', $this->error[ 'file' ] )
            : null;
        $base_path      = substr( $filename, strlen( DIR_BASE ) );
        $ip             = Request::ipClient();
        $request        = isset( $_SERVER[ 'REQUEST_URI' ] )
            ? $_SERVER[ 'REQUEST_URI' ]
            : $_SERVER[ 'SCRIPT_FILENAME' ];
        $http_referer   = isset( $_SERVER[ 'HTTP_REFERER' ] )
            ? $_SERVER[ 'HTTP_REFERER' ]
            : '';
        $line           = $this->error[ 'line' ];
        $extra          = method_exists( '\\App\\App', 'addExceptionData' )
            ? (array) \App\App::addExceptionData()
            : [];

        // Prepara o texto do log
        $agora          = new \DateTime();
        $this->dataLog  = [
            'date'      => $agora->format( 'Y-m-d' ),
            'time'      => $agora->format( 'H:i:s' ),
            'type'      => $this->error[ 'type' ],
            'message'   => $this->error[ 'message' ],
            'file'      => str_replace( DIR_BASE, '..', $filename ),
            'line'      => $this->error[ 'line' ],
            'ip'        => $ip,
            'request'   => $request,
            'referer'   => $http_referer,
            'trace'     => isset( $this->error[ 'stack' ] )
                ? $this->error[ 'stack' ]
                : null,
            'env'       => ENV,
            'extra'     => $extra,
        ];
    }

    /**
     * Método para escrever o log.
     *
     * @return  null
     */
    protected function makeLogText()
    {
        if ( $this->dbTarget )
            return;

        // Prepara o texto do log
        $log            = new IniMaker();
        $log->addSection( uniqid( '', true )                  );
        $log->addLine( 'date',      $this->dataLog[ 'date' ]    );
        $log->addLine( 'time',      $this->dataLog[ 'time' ]    );
        $log->addLine( 'type',      $this->dataLog[ 'type' ]    );
        $log->addLine( 'message',   $this->dataLog[ 'message' ] );
        $log->addLine( 'basepath',  DIR_BASE                    );
        $log->addLine( 'file',      $this->dataLog[ 'file' ]    );
        $log->addLine( 'line',      $this->dataLog[ 'line' ]    );
        $log->addLine( 'ip',        $this->dataLog[ 'ip' ]      );
        $log->addLine( 'request',   $this->dataLog[ 'request' ] );
        $log->addLine( 'referer',   $this->dataLog[ 'referer' ] );

        foreach ( $this->dataLog[ 'extra' ] as $key => $value )
            $log->addLine( $key, $value );

        if ( !empty( $this->error[ 'stack' ] ) )
        {
            $trace  = PHP_EOL . $this->error[ 'stack' ];
            $trace  = preg_replace( '@^#@m', '    #', $trace );
            $trace  = str_replace( DIR_CORE, '[DIR_CORE]', $trace );
            $trace  = str_replace( DIR_BASE, '[DIR_BASE]', $trace );
            $log->addLine( 'trace', $trace );
        }

        $this->text = $log->content();
    }

    /**
     * Método para escrever o log.
     */
    protected function saveLogFeature()
    {
        if ( $this->dbTarget )
            return;

        // Se já salvou o log, não executa novo salvamento
        if ( $this->saved || !( defined( 'ROUTE' ) && !ROUTE ) )
            return;
     
        $controller         = ControllerParser::controller();
        $controller_parts   = explode( '\\', ControllerParser::controller() );

        if ( array_pop( $controller_parts ) != 'Controller' )
            return;

        $namespaces         = Config::get( 'namespaces' );
        $new_classname      = $controller;

        foreach ( $namespaces as $namespace => $path )
        {
            if ( strpos( $controller, $namespace ) === 0 )
            {
                $new_classname = $path . substr( $controller, strlen( $namespace ) );
                break;
            }
        }

        $path               = str_replace( '\\', '/', $new_classname );
        $logfile            = DIR_BASE . '/' . substr( $path, 0, -10 );
        $this->saved        = $this->write( $logfile );
    }

    /**
     * Método para escrever o log.
     */
    protected function saveLogDate()
    {
        if ( $this->dbTarget )
            return;

        // Se já salvou o log, não executa novo salvamento
        if ( $this->saved )
            return;

        // Prepara os possíveis caminhos do arquivo de log
        $year_path      = $this->logPath()
            . '/' . date( 'Y' );
        
        $month_path     = $year_path
            . '/' . date( 'm' );
        
        $day_path       = $month_path
            . '/' . date( 'd' );
        
        $hour_path      = $day_path
            . '/' . date( 'H' );

        $log_contexts   = array(
            'year'      => $year_path,
            'month'     => $month_path,
            'day'       => $day_path,
        );
        
        // Tenta salvar o log
        foreach ( $log_contexts as $freq => $path )
        {
            if ( $freq === 'day' )
                return $this->write( $path );

            if ( !file_exists( $path ) )
            {
                mkdir( $path, 0777, true );
                continue;
            }
            
            if ( !is_writable( $path ) )
                exit( "Estamos sem permissão para criar a pasta '$path'!" );
        }
    }

    /**
     * Salva o log de erro no banco-de-dados.
     */
    protected function saveLogDB()
    {
        if ( !$this->dbTarget )
            return;

        $this->dataLog[ 'extra' ] = json_encode( $this->dataLog[ 'extra' ] );
        $columns        = array_keys( $this->dataLog );
        $columns        = '`' . implode( '`, `', $columns ) . '`';

        foreach ( $this->dataLog as $key => $value )
            $params[]   = SQL::makeParam();

        $this->dataLog  = array_combine( $params, $this->dataLog );
        $params         = implode( ', ', $params );

        $sql            = "INSERT INTO `{$this->table}` ($columns) VALUES( $params )";
        
        try {
            $db         = DB::instance( $this->schema );
        }
        catch ( \Exception $e )
        {
            $env        = ENV;
            $msg        = "Não foi possível conectar ao banco [{$this->schema}] no ambiente [{$env}].";
            die( $msg );
        }

        $result         = $db->execute( $sql, $this->dataLog );
    }

    /**
     * Método para escrever o log.
     *
     * @return boolean
     */
    protected function write( $file_path )
    {
        $logFile    = new \SplFileObject( $file_path . '_feature.errors', 'a' );
        $log_saved  = $logFile->fwrite( $this->text . PHP_EOL );
        $logFile    = null;

        return $log_saved;
    }

    /**
     * Retorna o caminho dos arquivos de log.
     *
     * @return boolean
     */
    protected function logPath()
    {
        return DIR_LOGS . '/' . $this->context;
    }

    /**
     * Retorna o caminho dos arquivos de log.
     */
    protected function saveRoutineLog( Array $options )
    {
        $name           = str_replace( '\\', '/', $options[ 'name' ] );
        $start          = $options[ 'start' ];
        $end            = $options[ 'end' ];
        $duration       = $options[ 'duration' ];
        
        $log_filename   = DIR_LOGS . '/routine/' . $name . '.log';
        $error          = preg_replace( '/[\r\n]+/', PHP_EOL . '           ', $this->error );

        $log            = new IniMaker();
        $log->addSection( uniqid( '', true ) );
        $log->addLine( 'DATE', date( 'Y-m-d' ) );
        $log->addLine( 'START', $start );
        $log->addLine( 'END', $end );
        $log->addLine( 'DURATION', $duration );
        $log->addLine( 'RESULT', $error );

        $log_file       = new \SplFileObject( $log_filename, 'a' );
        $result         = $log_file->fwrite( $log->content() . PHP_EOL );
        $log_file       = null;

        return $result;
    }
}
