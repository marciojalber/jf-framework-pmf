<?php

namespace JF\HTTP;

use JF\HTML\PHP2HTML;
use JF\Config;
use JF\Exceptions\ErrorException;
use JF\Messager;

/**
 * Classe que captura as rotas das requisições.
 */
class Router
{
    /**
     * Armazena variáveis para análise da rota.
     */
    protected static $route         = array();

    /**
     * Formato padrão de resposta
     */
    protected static $defaultFormat = 'json';

    /**
     * Formato padrão de resposta
     */
    protected static $homePage      = 'home.html';

    /**
     * Captura e define as URLs base e a rota.
     */
    public static function basicDefines()
    {
        if ( defined( 'URL_BASE' ) )
            return;

        $server         = SERVER;
        $root_on_dirapp = Config::get([
            "servers@$server@rootOnDirapp",
            'servers@default@rootOnDirapp'
        ], null, ['separator' => '@']);
        $base           = !$root_on_dirapp
            ? BASE_APP
            : '';
        $base           = preg_replace( '@\/$@', '', $base );
        $url_base       = '//' . $server . $base;        
        $url_route      = substr( REQUEST_URI, strlen( $base ) + 1 );
        $url_route      = preg_replace( '/\?.*/', '', $url_route );
        $url_route      = preg_replace( '@^ui/pages/@', '', strtolower( $url_route ) );

        define( 'URL_BASE',     $url_base );
        define( 'URL_UI',       URL_BASE . '/ui' );
        define( 'URL_PAGES',    URL_UI . '/pages' );
        define( 'ROUTE',        urldecode( $url_route ) );

        if ( ROUTE )
            return;

        $default_route  = method_exists( '\App\App', 'defaultRoute' )
            ? \App\App::defaultRoute()
            : Config::get( 'app.default_route', self::$homePage );
        
        $page_target    = URL_PAGES . '/' . $default_route;
        Request::redirect( $page_target, true );
    }

    /**
     * Inicia a aplicação.
     */
    public static function defineRoute()
    {
        self::$route            = new \StdClass();
        
        self::checkCallForJFTool();
        
        if ( !JFTOOL )
        {
            self::checkRedirectToLink();
            self::getRouteParts();
            self::validateRequestFormat();
        }
        
        self::defineArgs();
    }

    /**
     * Captura a URL da rota.
     */
    private static function checkCallForJFTool()
    {
        $prefix     = 'jftools/';
        $lenprefix  = strlen( $prefix );
        $jftool     = substr( ROUTE, 0, $lenprefix ) == $prefix
            ? substr( ROUTE, $lenprefix )
            : null;
        
        define( 'JFTOOL', $jftool );
    }

    /**
     * Captura a URL da rota.
     */
    private static function checkRedirectToLink()
    {
        $route              = ROUTE;
        $links              = Config::get( 'links' );

        if ( isset( $links->$route ) )
        {
            $url_redirect   = is_array( $links->$route )
                ? $links->$route[ 0 ]
                : $links->$route;

            $remove_urlbase = is_array( $links->$route )
                ? !empty( $links->$route[ 1 ] )
                : false;

            Request::redirect( $url_redirect, $remove_urlbase );
        }
    }

    /**
     * Captura a URL da rota.
     */
    private static function getRouteParts()
    {
        $route_parts    = explode( '/', ROUTE );
        $route_action   = array_pop( $route_parts );
        $action_parts   = explode( '.', $route_action );

        if ( count( $action_parts ) > 2 )
        {
            $msg        = Messager::get( 'router', 'invalid_route', ROUTE );
            throw new ErrorException( $msg );
        }

        $request_format = isset( $action_parts[ 1 ] )
            ? $action_parts[ 1 ]
            : '';
        $len_format     = strlen( $request_format ) + 1;
        
        self::$route->route         = $request_format
            ? substr( ROUTE, 0, -$len_format )
            : ROUTE;

        self::$route->response_type = $request_format
            ? $request_format
            : self::$defaultFormat;

        self::$route->type          = self::$route->response_type == 'html'
            ? 'view'
            : 'service';

        self::$route->url_route     = URL_BASE . '/' . self::$route->route;
    }

    /**
     * Valida o formato da resposta.
     */
    private static function validateRequestFormat()
    {
        $request_format = self::$route->response_type;

        if ( !Responder::validateType( $request_format ) )
        {
            $msg        = Messager::get( 'router', 'unsuported_format', $request_format );
            throw new ErrorException( $msg );
        }
    }

    /**
     * Define os argumentos passados pela URL.
     */
    private static function defineArgs()
    {
        $route                  = self::$route;
        $route->args            = array();
        $route->url_args        = $_SERVER[ 'QUERY_STRING' ];

        if ( !$route->url_args )
        {
            return;
        }

        $url_args           = explode( '/', $route->url_args );
        $max                = count( $url_args );
        
        for ( $i = 0; $i < $max; $i += 2 )
        {
            $key_arg        = $url_args[ $i ];
            $key_value      = $i + 1;
            $value          = isset( $url_args[ $key_value ] )
                ? $url_args[ $key_value ]
                : null;
            $route->args[ $key_arg ] = $value;
        }
    }

    /**
     * Retorna parâmetros capturados da rota.
     */
    public static function get( $param = null )
    {
        if ( !$param )
        {
            return self::$route;
        }

        return isset( self::$route->$param )
            ? self::$route->$param
            : null;
    }
}
