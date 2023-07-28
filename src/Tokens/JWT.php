<?php

namespace JF\Tokens;

use JF\Config;

/**
 * Classe para manipulação de tokens JWT.
 */
class JWT
{
    /**
     * Cria um novo token.
     */
    public static function create( $payload, $senha = '' )
    {
        $header         = [
           'alg'        => 'HS256',
           'typ'        => 'JWT',
        ];
        $header         = json_encode( $header );
        $header         = self::base64urlEncode( $header );

        $payload        = (object) $payload;
        $payload->iat   = $payload->iat ?? microtime( true );
        $payload        = json_encode( $payload );
        $payload        = self::base64urlEncode( $payload );

        $senha          = $senha
            ? $senha
            : md5( Config::get( 'security.hash', '' ) );
        $signature      = hash_hmac( 'sha256', "$header.$payload", $senha, true );
        $signature      = urlencode( self::base64urlEncode( $signature ) );
        $token          = "$header.$payload.$signature";

        return $token;
    }

    /**
     * Valida um token e retorna os dados do payload se o token for válido.
     */
    public static function validate( $token )
    {
        if ( !$token || $token == 'undefined' )
            return false;

        $parts      = explode( '.', $token );

        if ( count( $parts ) != 3 )
            return false;
        
        $header     = $parts[ 0 ];
        $payload    = $parts[ 1 ];
        $signature  = $parts[ 2 ];

        $senha      = md5( Config::get( 'security.hash', '' ) );
        $hash       = hash_hmac( 'sha256', "$header.$payload", $senha, true );
        $hash       = self::base64UrlEncode( $hash );

        if ( $hash != $signature )
        {
            return false;
        }

        $payload    = json_decode( self::base64UrlDecode( $payload ) );
        $exp        = isset( $payload->exp )
            ? $payload->exp
            : null;

        if ( $exp && $exp < microtime( true ) )
        {
            return false;
        }

        return $payload;
    }

    /**
     * Valida um token e retorna os dados do payload se o token for válido.
     */
    public static function base64UrlEncode( $value )
    {
        $value  = base64_encode( $value );
        $value  = strtr( $value, '+/', '-_' );

        return rtrim( $value, '=' );
    }

    /**
     * Valida um token e retorna os dados do payload se o token for válido.
     */
    public static function base64UrlDecode( $value )
    {
        $value  = strtr( $value, '-_', '+/' );
        $len    = strlen( $value );
        $value  = str_pad( $value, $len % 4, '=', STR_PAD_RIGHT );
        
        return base64_decode( $value, '=' );
    }
}
