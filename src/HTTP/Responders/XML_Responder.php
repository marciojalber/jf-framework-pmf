<?php

namespace JF\HTTP\Responders;

use JF\HTTP\Responder;
use JF\XML;

/**
 * Classe que formata e envia resposta das requisições ao cliente.
 */
class XML_Responder extends Responder
{
    /**
     * Armazena os header do tipo de resposta.
     */
    protected static $headers = ['application/xml'];


    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function send( $data, $controller_obj )
    {
        self::setHeader( 'xml', $controller_obj->charset() );
        echo XML::create( 'document', $data )->asXml();
    }

    /**
     * Configura o header da resposta de acordo com o formato do arquivo.
     */
    public static function setHeader( $type = null, $charset = null )
    {
        parent::setHeader( 'xml', $charset );
    }
}
