<?php

namespace JF\HTTP;

use JF\Session;

/**
 * Classe que manipula formulários.
 *
 * @author  Márcio Jalber [marciojalber@gmail.com]
 * @since   19/07/2017
 */
class Form
{
    /**
     * Armazena o prefixo para diferenciar dos demais itens de sessão.
     * 
     * @type string
     */
    private static $token_prefix = 'jf_formtoken_';
    
    /**
     * Cria o token de validação do formulário.
     * 
     * @return void
     */
    public static function tokenize( $token_name )
    {
        $scheme             = $_SERVER[ 'REQUEST_SCHEME' ];
        $server_name        = $_SERVER[ 'SERVER_NAME' ];
        $request_uri        = $_SERVER[ 'REQUEST_URI' ];
        $origin             = $scheme . '://' . $server_name . $request_uri;

        $token              = uniqid( '', true );

        $data               = array(
            'origin'        => $origin,
            'value'         => $token,
        );
        
        Session::set( self::$token_prefix . $token_name, (object) $data );
        
        $hash               = password_hash( $token, PASSWORD_DEFAULT );
        return $hash;
    }
    
    /**
     * Valida um formulário submetido.
     * 
     * @return void
     */
    public static function validate( $token_name, $token_value, $retoken = false )
    {
        $token              = Session::get( self::$token_prefix . $token_name );
        $http_referer       = $_SERVER[ 'HTTP_REFERER' ];
        $token_checked      = password_verify( $token->value, $token_value );
        $valid_token        = $token_checked && $token->origin === $http_referer;
        $response           = (object) array(
            'valid'         => $valid_token,
        );

        if ( $retoken )
        {
            $new_token      = uniqid( '', true );
            $data           = array(
                'origin'    => $token->origin,
                'value'     => $new_token,
            );
            $response->token = password_hash( $new_token, PASSWORD_DEFAULT );
            Session::set( self::$token_prefix . $token_name, (object) $data );
        }
        else
        {
            Session::delete( self::$token_prefix . $token_name );
        }

        return $response;
    }
}
