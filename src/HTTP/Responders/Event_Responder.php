<?php

namespace JF\HTTP\Responders;

use JF\HTTP\Responder;

/**
 * Classe que formata e envia resposta das requisições ao cliente.
 */
class Event_Responder extends Responder
{
    /**
     * Armazena os header do tipo de resposta.
     */
    protected static $headers = ['text/event-stream'];

    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function send( $data, $controller )
    {
        self::setHeader( 'event', $controller->charset() );
        $id_event   = isset( $controller->eventId )
            ? $controller->eventId
            : time();
        $data       = $data;

        if ( !$data )
            return null;
        
        echo 'id: ' . $id_event . PHP_EOL;
        echo 'data: ' . json_encode( $data ) . PHP_EOL;

        if ( isset( $controller->event_name ) )
            echo 'event: ' . $controller->event_name . PHP_EOL;
        
        echo PHP_EOL;
        
        ob_flush();
        flush();
    }

    /**
     * Configura o header da resposta de acordo com o formato do arquivo.
     */
    public static function setHeader( $type = null, $charset = null )
    {
        parent::setHeader( 'event', $charset );
    }
}
