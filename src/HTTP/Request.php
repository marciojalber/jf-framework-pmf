<?php

namespace JF\HTTP;

use JF\Config;
use JF\Exceptions\ErrorException;
use JF\Messager;

/**
 * Classe que manipula informações enviadas ao servidor na requisição,
 * tempo de requisição, envia outras requisições e manipula URLS.
 */
class Request
{
    /**
     * Códigos de retorno da requisição
     */
    const OK                    = 200; // Requisição bem-sucedida
    const NOT_FOUND             = 404; // Página não encontrada

    /**
     * Marcador do tempo de execução da requisição.
     */
    protected static $old_time  = null;

    /**
     * Método para informar se a requisição foi feita em ajax.
     */
    public static function ipServer()
    {
        $has_server_addr    = isset( $_SERVER[ 'SERVER_ADDR' ] );
        $ipServer           = !$has_server_addr || $_SERVER[ 'SERVER_ADDR' ] === '::1'
            ? '127.0.0.1'
            : $_SERVER[ 'SERVER_ADDR' ];
        
        return $ipServer;
    }

    /**
     * Método para informar o IP real do cliente requisitante.
     */
    public static function ipClient()
    {
        if ( empty( $_SERVER[ 'REMOTE_ADDR' ] ) )
            return null;

        $ip_client = isset( $_SERVER[ 'HTTP_X_FORWARDED_FOR' ] )
            ? preg_replace( '@.*?, ?@', '', $_SERVER[ 'HTTP_X_FORWARDED_FOR' ] )
            : $_SERVER[ 'REMOTE_ADDR' ];

        $ip_client = $ip_client === '::1'
            ? '127.0.0.1'
            : $ip_client;

        return $ip_client;
    }

    /**
     * Método para informar se a requisição foi feita em ajax.
     */
    public static function ajax()
    {
        $ajax_header  = isset( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] )
            ? strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] )
            : null;
        
        return $ajax_header === 'xmlhttprequest';
    }

    /**
     * Método para forçar o protocolo HTTPS nas requisições.
     */
    public static function forceHttps()
    {
        $is_https   = !empty( $_SERVER[ 'HTTPS' ] ) && $_SERVER['HTTPS'] === 'on';
        $host       = str_replace( '.prevnet', '', $_SERVER[ 'SERVER_NAME' ] );
        $uri        = $_SERVER[ 'REQUEST_URI' ];
        
        if ( $is_https )
        {
            header( 'Location: https://' . $host . $uri );
        }
    }

    /**
     * Método para enviar requisições POST.
     */
    public static function sendPost( $url, $data, $options = array() )
    {
        return self::sendRequest( 'POST', $url, $data, $options );
    }

    /**
     * Método para enviar requisições GET.
     */
    public static function sendGet( $url, $options = array() )
    {
        return self::sendRequest( 'GET', $url, array(), $options );
    }

    /**
     * Método para enviar requisições GET / POST.
     */
    protected static function sendRequest( $method, $url, $data, $options )
    {
        $data               = http_build_query( $data );
        $header             = "Connection: close\r\n" .
            "Content-type: application/x-www-form-urlencoded\r\n" .
            "Content-Length: " . strlen( $data ) .
            "\r\n";
        
        if ( !empty( $options[ 'origin' ] ) )
        {
            $header         .= "Origin: {$options[ 'origin' ]}\r\n";
        }
        
        $context            = [
            'http'          => [
                'method'    => $method,
                'header'    => $header,
                'content'   => $data,
            ],
        ];

        $content            = stream_context_create( $context );
        $response           = file_get_contents( $url, null, $content );
        
        return $response;
    }

    /**
     * Método para fazer uma requisição via CURL.
     */
    public static function sendCurlRequest( $url, $args, $options = [] )
    {
        $options    = (object) $options;
        $curl       = curl_init( $url );
        $httpheader = ['Content-Type: multipart/form-data'];

        curl_setopt( $curl, CURLOPT_HTTPHEADER, $httpheader );

        if ( !empty( $args ) )
        {
            curl_setopt( $curl, CURLOPT_POSTFIELDS, $args );
        }

        if ( isset( $options->username ) )
        {
            $username   = $options->username;
            $password   = isset( $options->password )
                ? $options->password
                : null;
            curl_setopt( $curl, CURLOPT_USERPWD, $username . ':' . $password );
            unset( $options->username );
            unset( $options->password );
        }

        foreach ( $options as $key => $value )
        {
            curl_setopt( $curl, $key, $value );
        }

        $response   = curl_exec( $curl );
        $error_num  = curl_errno( $curl );
        $error_msg  = curl_error( $curl );

        if ( !$response && $error_num )
        {
            $msg    = $error_num . ' - ' . $error_msg;
            throw new ErrorException( $msg );
        }

        if ( !$response && !$error_num )
        {
            $msg    = Messager::get( 'request', 'server_not_answer' );
            throw new ErrorException( $msg );
        }

        curl_close( $curl );
        
        return $response;
    }

    /**
     * Método para redirecionar a página.
     */
    public static function redirect( $url = '', $literal_link = false )
    {
        if ( !$literal_link )
        {
            $url        = str_replace( '.prevnet', '', $url );
            $url        = substr( $url, 0, 1 ) !== '/'
                ? URL_BASE . '/' . $url
                : URL_BASE . $url;
        }
        
        header( 'Location: ' . $url );
        exit;
    }

    /**
     * Método para enviar um código da situação da requisição.
     */
    public static function sendCode( $status_code )
    {
        http_response_code( $status_code );
    }

    /**
     * Método para capturar os dados de cabeçalho da requisição.
     */
    public static function headers()
    {
        if ( function_exists( 'getallheaders' ) )
        {
            return getallheaders();
        }

        if ( function_exists( 'apache_request_headers' ) )
        {
            return apache_request_headers();
        }

        if ( function_exists( 'http_get_request_headers' ) )
        {
            return http_get_request_headers();
        }

        if ( function_exists( 'get_headers' ) )
        {
            return get_headers( $_SERVER[ 'REQUEST_URI' ] );
        }
    }

    /**
     * Método para capturar os dados de cabeçalho da requisição.
     */
    public static function time()
    {
        $new_time           = microtime( true );
        $diff               = $new_time - $_SERVER[ 'REQUEST_TIME_FLOAT' ];
        
        return $diff;
    }

    /**
     * Método para capturar os dados de cabeçalho da requisição.
     */
    public static function partialTime()
    {
        if ( !self::$old_time )
        {
            self::$old_time = $_SERVER[ 'REQUEST_TIME_FLOAT' ];
        }
        
        $new_time           = microtime( true );
        $diff               = $new_time - self::$old_time;
        self::$old_time     = $new_time;
        
        return $diff;
    }
}
