<?php

namespace JF\HTTP;

use JF\Autoloader;
use JF\Config;
use JF\Exceptions\ErrorException;
use JF\Messager;

/**
 * Classe para validar uma rota.
 */
class ControllerParser
{
    /**
     * Armazena o controller.
     */
    protected static $controller;

    /**
     * Retorna o nome do controlador.
     */
    public static function controller( $validate = false )
    {
        if ( !self::$controller )
        {
            self::parseRoute();
        }

        if ( $validate )
        {
            self::testExistsController();
            self::validateController();
        }

        return self::$controller;
    }

    /**
     * Define o controller da requisição.
     */
    protected static function parseRoute()
    {
        // Captura as variáveis básicas
        $route       = Router::get( 'route' );

        if ( !$route )
        {
            $msg    = Messager::get( 'router', 'control_not_informed' );
            throw new ErrorException( $msg );
        }

        $controller = str_replace( '-', '_', $route );
        $controller = str_replace( '/', '\\', $controller );
        $controller = preg_replace_callback( '/(^.|\\\.|_.)/', function( $matches ) {
            return strtoupper( $matches[1] );
        }, $controller );
        $controllers        = Config::get( 'controllers' );

        foreach ( $controllers as $path => $control_target )
        {
            if ( strpos( $route, $path ) === 0 )
            {
                $len_path   = strlen( $control_target );
                $controller = $control_target . substr( $controller, $len_path );
                break;
            }
        }

        $controller1        = "Features\\{$controller}\\Controller";
        $controller2        = "Controllers\\{$controller}__Controller";
        self::$controller   = file_exists( Autoloader::getClassFilename( $controller1 ) )
            ? $controller1
            : $controller2;
    }

    /**
     * Valida o controller.
     */
    public static function validateController()
    {
        if ( !self::$controller )
        {
            return;
        }

        self::testExistsController();
        self::testExistsController();
    }

    /**
     * Testa se o controller existe.
     */
    protected static function testExistsController()
    {
        if ( !class_exists( self::$controller ) )
        {
            $msg        = Messager::get(
                'router',
                'controller_not_found',
                self::$controller
            );
            throw new ErrorException( $msg );
        }
    }

    /**
     * Testa se a classe corresponde a um controller JF.
     */
    protected static function testClassIsController()
    {
        $jf_controller  = 'JF\\HTTP\\Controller';

        if ( !is_subclass_of( self::$controller, $jf_controller ) )
        {
            $msg        = Messager::get(
                'router',
                'class_not_controller',
                self::$controller
            );

            throw new ErrorException( $msg );
        }
    }
}
