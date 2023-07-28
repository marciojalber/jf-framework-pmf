<?php

namespace JF\HTTP\Responders;

use JF\HTTP\Responder;

/**
 * Classe que formata e envia resposta das requisições ao cliente.
 */
class PHP_Responder extends Responder
{
    /**
     * Armazena os header do tipo de resposta.
     */
    protected static $headers = ['text/plain'];

    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function send( $data, $controller_obj )
    {
        self::setHeader( 'php', $controller_obj->charset() );
        echo serialize( $data );
    }

    /**
     * Configura o header da resposta de acordo com o formato do arquivo.
     */
    public static function setHeader( $type = null, $charset = null )
    {
        parent::setHeader( 'php', $charset );
    }
}
