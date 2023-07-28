<?php

namespace JF\HTTP\Responders;

use JF\HTTP\Responder;

/**
 * Classe que formata e envia resposta das requisições ao cliente.
 */
class Error_Responder extends Responder
{
    /**
     * Armazena os header do tipo de resposta.
     */
    protected static $headers = ['application/json'];

    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function send( $exception )
    {
        if ( !headers_sent() )
            http_response_code( 404 );

        self::setHeader();
        
        // Define as mensagens de erro da requisição
        $handler    = '';
        $service    = '';
        $namespace  = '';
        
        // Captura a mensagem de erro a ser mostrada e o nome do arquivo
        $error_msg  = sprintf(
            "%s - %s [%s]: %s",
            $exception[ 'type' ],
            str_replace( '\\', '/', $exception[ 'file' ] ),
            $exception[ 'line' ],
            $exception[ 'message' ]
        );

        if ( !headers_sent() )
            header( 'Content-Type: application/json' );

        $legible_text = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        
        echo json_encode( array( 'error' => $error_msg ), $legible_text );
        exit();
    }

    /**
     * Configura o header da resposta de acordo com o formato do arquivo.
     */
    public static function setHeader( $type = null, $charset = null )
    {
        if ( !headers_sent() )
            parent::setHeader( 'json', $charset );
    }
}
