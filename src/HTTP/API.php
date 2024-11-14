<?php

namespace JF\HTTP;

use JF\Exceptions\ErrorException as Error;
use JF\Config;

/**
 * Classe que manipula requisições HTTP.
 *
 * @author  Márcio Jalber [marciojalber@gmail.com]
 * @since   26/04/2019
 */
class API
{
    /**
     * Analisa se uma requisição é uma chamada à API.
     */
    public static function parse()
    {
        $route      = Router::get( 'route' );
        $feature    = str_replace( '/', '\\', ucfirst( $route ) );
        $feature    = str_replace( '-', '_', $feature );
        $feature    = preg_replace_callback( '@(\\\.|_.)@', function( $matches ) {
            return strtoupper( $matches[ 1 ] );
        }, $feature );
        $feature1   = "App\\Services\\{$feature}\Service";
        $feature    = null;

        if ( class_exists( $feature1 )  )
            $feature = $feature1;
        
        if ( !$feature )
            return;
        
        if ( !is_subclass_of( $feature, 'JF\\Domain\\Feature') )
            return;

        $uses       = class_uses( $feature );
        $traits     = [ 'JF\\HTTP\\HTTP_Service_Trait', 'JF\\HTTP\\API_Trait' ];

        if ( !array_intersect( $traits, $uses ) )
            return;

        $method     = $_SERVER[ 'REQUEST_METHOD' ];
        $methods    = $feature::acceptHTTPMethods();
        $args       = $_SERVER[ 'REQUEST_METHOD' ] == 'GET'
            ? json_decode( json_encode( Input::args() ) )
            : json_decode( json_encode( Input::post() ) );

        array_walk( $methods, function( &$value ) {
            $value      = strToUpper( $value );
        });

        if ( !in_array( $method, $methods ) )
            throw new Error( "Método $method não permitido para a chamada do serviço \"{$route}\"." );

        if ( ENV_DEV && Config::get( 'app.codeAnalyse' ) )
        {
            // \JF\Domain\FeatureDocWriter::instance( $feature )->make();
            \JF\Domain\FeatureCodeAnalyser::instance( $feature )->analyse();
        }

        return (object) [
            'feature'   => $feature,
            'args'      => $args,
        ];
    }
}
