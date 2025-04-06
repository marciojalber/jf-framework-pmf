<?php

namespace JF;

use JF\HTTP\Responders\Error_Responder;
use JF\Log;

/**
 * Classe para manipulação de erros.
 */
final class ErrorHandler
{
    /**
     * Disparado quando ocorrer erros fatais.
     */
    public static function register()
    {
        static $registered  = false;

        if ( $registered )
            return;

        $registered         = true;
        
        register_shutdown_function( [ __CLASS__, 'shutdown' ]);
        set_error_handler([ __CLASS__, 'error' ]);
        set_exception_handler([ __CLASS__, 'exception' ]);
    }

    /**
     * Disparado quando ocorrer erros fatais.
     */
    public static function shutdown()
    {
        if ( !$error = error_get_last() )
            return;

        if ( JF_TESTING )
        {
            print_r( $error );
            return;
        }

        $loaded_classes     = Autoloader::loadedClasses( 1 );
        $error[ 'type' ]    = 'FATAL';
        $error[ 'trace' ]   = $loaded_classes;

        Log::register( $error, 'error' );
        Error_Responder::send( $error );
    }

    /**
     * Disparado quando ocorrer erros regulares.
     */
    public static function error( $code, $message, $file, $line )
    {
        ob_start();
        debug_print_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
        $trace              = trim( ob_get_clean() );
        $loaded_classes     = Autoloader::loadedClasses( 1 );
        $error              = array(
            'code'          => $code,
            'message'       => $message,
            'file'          => $file,
            'line'          => $line,
            'type'          => 'ERROR',
            'trace'         => $trace,
        );

        if ( JF_TESTING )
        {
            print_r( $error );
            return;
        }
        
        Log::register( $error, 'error' );

        if ( defined( 'ENV_DEV' ) && ENV_DEV )
            Error_Responder::send( $error );
    }

    /**
     * Disparado quando ocorrer exceções.
     */
    public static function exception( $exception )
    {
        $codeException  = $exception->getCode();
        $error          = array(
            'code'      => $codeException,
            'message'   => preg_replace( '/\nStack trace:.*/s', '', $exception->getMessage() ),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'type'      => 'EXCEPTION',
            'trace'     => trim( $exception->getTraceAsString() ),
        );
        
        if ( JF_TESTING )
        {
            print_r( $error );
            return;
        }
        
        Log::register( $error, 'error' );
        Error_Responder::send( $error );
    }
}
