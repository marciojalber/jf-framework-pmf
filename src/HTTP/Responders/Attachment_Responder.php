<?php

namespace JF\HTTP\Responders;

use JF\HTTP\Router;

/**
 * Classe que formata e envia resposta das requisições ao cliente.
 */
trait Attachment_Responder
{
    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function sendAttachment( $data, $controller_obj, $content_type )
    {
        $type   = Router::get( 'response_type' );

        if ( isset( $data[ 'error' ] ) || isset( $data->error ) )
        {
            static::setHeader( 'json', $controller_obj->charset() );
            echo json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            return;
        }

        if ( !$data && empty( $controller_obj->filepath ) )
        {
            $data           = ['error' => "Nenhum arquivo indicado ou conteúdo fornecido."];
            echo json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            return;
        }
        
        $feature_name   = substr( $controller_obj::CLASS, 0, 8 ) == 'Features'
            ? basename( dirname( $controller_obj::CLASS ) )
            : '';
        $control_name   = substr( $controller_obj::CLASS, 0, 8 ) == 'Controllers'
            ? str_replace( '__Controller', '', basename( $controller_obj::CLASS ) )
            : '';
        $basename       = $feature_name . $control_name;
        $basename       = isset( $controller_obj->filepath )
            ? basename( $controller_obj->filepath )
            : $basename;
        $filename       = isset( $controller_obj->filename )
            ? $controller_obj->filename
            : $basename;
        $length         = $data
            ? strlen( $data )
            : filesize( $controller_obj->filepath );

        if ( $type == 'pdf' && substr( $basename, -4 ) != '.pdf' )
            $basename  .= '.pdf';
        
        if ( $type == 'pdf' && substr( $filename, -4 ) != '.pdf' )
            $filename  .= '.pdf';

        if ( !$data && !file_exists( $controller_obj->filepath ) )
        {
            $data           = ['error' => "Arquivo '$basename' não encontrado."];
            echo json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            return;
        }

        static::setHeader( $content_type, $controller_obj->charset() );
        header( "Content-Length: " . $length );
        $content_disposition = $content_type == 'download'
            ? 'attachment'
            : 'inline';
        header( "Content-Disposition: $content_disposition; filename=$filename" );
        
        if ( !$data )
            return readfile( $controller_obj->filepath );

        echo $data;
    }
}
