<?php

namespace JF\HTTP\Responders;

use JF\HTTP\Responder;

/**
 * Classe que formata e envia resposta das requisições ao cliente.
 */
class PDF_Responder extends Responder
{
    use Attachment_Responder;
    
    /**
     * Armazena os header do tipo de resposta.
     */
    protected static $headers = ['application/pdf'];

    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function send( $data, $controller_obj )
    {
        self::sendAttachment( $data, $controller_obj, 'pdf' );
    }

    /**
     * Configura o header da resposta de acordo com o formato do arquivo.
     */
    public static function setHeader( $type = null, $charset = null )
    {
        parent::setHeader( 'pdf', $charset );
    }
}
