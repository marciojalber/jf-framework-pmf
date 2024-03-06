<?php

namespace JF\HTTP;

use JF\Doc\ServiceDocParser;
use JF\HTTP\API;
use JF\HTTP\Controller;
use JF\HTTP\Input;
use JF\Exceptions\InfoException as Info;
use JF\Exceptions\WarningException as Warning;
use JF\Exceptions\ErrorException as Error;
use JF\Messager;
use JF\Config;

/**
 * Classe que formata e envia resposta das requisições ao cliente.
 */
class Responder
{
    const DEFAULT_CHARSET = 'UTF-8';

    /**
     * Formatos de resposta das requisições HTTP.
     */
    protected static $types = [
        'html',
        'csv',
        'download',
        'event',
        'json',
        'pdf',
        'php',
        'xls',
        'xml',
        'txt',
    ];

    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function validateType( $type )
    {
        return in_array( $type, self::$types );
    }

    /**
     * Disparado quando ocorrer exceções.
     */
    public static function sendResponse()
    {
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH' );
        header( 'Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization' );
        
        static::testAPICall();

        $controller_class   = ControllerParser::controller();
        $ctrl_obj           = new $controller_class();

        self::parseInputs( $ctrl_obj, $controller_class );

        $has_wrapper        = method_exists( $ctrl_obj, 'wrapper' );
        $fn_before          = function() use ( $ctrl_obj ) {
            return $ctrl_obj->before();
        };

        $data               = $has_wrapper
            ? $ctrl_obj->wrapper( $fn_before )
            : $fn_before();
        
        $fn_action          = function() use ( $ctrl_obj ) {
            return $ctrl_obj->execute();
        };

        if ( !$data )
        {
            $data           = $has_wrapper
                ? $ctrl_obj->wrapper( $fn_action )
                : $fn_action();
        }

        self::sendSpecificResponse( $data, $ctrl_obj );

        $ctrl_obj->after();
    }

    /**
     * Configura o header da resposta de acordo com o formato do arquivo.
     */
    public static function parseInputs( $ctrl_obj, $controller_class )
    {
        if ( empty( $controller_class::$expect ) )
            return;

        $method             = $controller_class::$post
            ? 'post'
            : 'args';
        $ctrl_obj->input    = (object) [];

        foreach ( $controller_class::$expect as $featureKey => $inputKey )
        {
            $value                          = Input::$method( $featureKey );
            $ctrl_obj->input->$featureKey   = $value;
        }
    }

    /**
     * Configura o header da resposta de acordo com o formato do arquivo.
     */
    public static function sendSpecificResponse( $data, $ctrl_obj, $exception = false )
    {
        http_response_code( 200 );
        
        $response_type      = Router::get( 'response_type' );
        $errors_format      = [ 'json', 'php', 'xml', 'txt' ];
        $response_type      = $exception && !in_array( $response_type, $errors_format )
            ? 'json'
            : $response_type;
        $response_class     = in_array( $response_type, ['download', 'event'] )
            ? ucfirst( $response_type )
            : strtoupper( $response_type );
        $response_class     = $response_class == 'PDF' && $data !== null && !is_scalar( $data )
            ? 'JSON_Responder'
            : $response_class . '_Responder';
        $response_class     = 'JF\\HTTP\\Responders\\' . $response_class;
        $response_class::send( $data, $ctrl_obj );
    }

    /**
     * Configura o header da resposta de acordo com o formato do arquivo.
     */
    public static function setHeader( $type, $charset = null )
    {
        // Define o content-type
        $content_type   = static::$headers;
        $charset        = $charset
            ? $charset
            : self::DEFAULT_CHARSET;
        
        foreach ( $content_type as $ct )
            header( "Content-Type: $ct; charset=$charset" );
        
        // Define os demais atributos do header
        $plain_formats  = array( 'html', 'json', 'php', 'xml', 'txt' );
        $plain_format   = in_array( $type, $plain_formats );

        if ( $type === 'event' )
            return header( 'Cache-Control: no-cache' );
        
        if ( $plain_format )
            return header( 'Cache-Control: public, must-revalidate, proxy-revalidate' );
        
        header( 'Cache-Control: public, must-revalidate, post-check=0, pre-check=0' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Transfer-Encoding: binary' );
        header( 'Expires: 0' );
    }

    /**
     * Testa se a chamada foi feita pela rota /api e, em caso positivo,
     * processa a requisição.
     */
    public static function testAPICall()
    {
        try
        {
            $instance       = new Controller();
            $api_request    = API::parse();

            if ( !$api_request )
                return;

            $feature    = $api_request->feature;
            $args       = $api_request->args;
            $response   = [];
            $instance   = new $feature();

            $instance->before();
            $fn         = function( $matches ) {
                return strtoupper( $matches[ 1 ] );
            };

            foreach ( $args as $arg_name => $arg_value )
            {
                $arg_name = preg_replace_callback( '@-(.)@', $fn, $arg_name );
                $instance->set( $arg_name, $arg_value );
            }

            $response       = $instance->execute();
            $response_type  = Router::get( 'response_type' );
            $after          = $instance->after();

            if ( $response_type != 'pdf' )
            {
                $response   = $response
                    ? (array) $response
                    : [];
            }

            if ( in_array( $response_type, ['json', 'php', 'txt'] ) )
            {
                $response   = array_merge( [
                    'type'  => 'success',
                    'text'  => $instance->msg(),
                ], $response );
            }

            if ( $response && $after && $response_type != 'pdf' )
            {
                $response   = array_merge( $response, $after );
            }

            self::sendSpecificResponse( $response, $instance );
            exit();
        }
        catch ( Info $except )
        {
            $response   = [
                'type'  => 'info',
                'text'  => $except->getMessage(),
            ];
            self::sendSpecificResponse( $response, $instance, true );
            $instance && $instance->after();
            exit();
        }
        catch ( Warning $except )
        {
            $response   = [
                'type'  => 'warning',
                'text'  => $except->getMessage(),
            ];
            self::sendSpecificResponse( $response, $instance, true );
            $instance && $instance->after();
            exit();
        }
        catch ( Error $except )
        {
            $response   = [
                'type'  => 'error',
                'text'  => $except->getMessage(),
            ];
            self::sendSpecificResponse( $response, $instance, true );
            $instance && $instance->after();
            exit();
        }
    }
}
