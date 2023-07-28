<?php

namespace JF\HTTP\Responders;

use JF\HTTP\Responder;

/**
 * Classe que formata e envia resposta das requisições ao cliente.
 */
class XLS_Responder extends Responder
{
    /**
     * Armazena os header do tipo de resposta.
     */
    protected static $headers = [
        'application/x-msexcel',
        'application/vnd.ms-excel',
        // 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function send( $data, $controller_obj )
    {
        self::setHeader( 'xls', $controller_obj->charset() );
        $reflection     = new \ReflectionClass( $controller_obj );
        $action         = $reflection->getName();
        $action         = str_replace( '\\', '_', $action );
        $action         = substr( $action, 12, -12 );
        $filename       = isset( $controller_obj->filename )
            ? $controller_obj->filename . '.xls'
            : $action . '.xls';
        $data           = json_decode( json_encode( $data ), true );
        
        if ( gettype( current( $data ) ) !== 'array' )
        {
            $data       = array( $data );
        }
        
        $labels         = array_keys( current( $data ) );
        $groups         = '';
        $source         = [];

        if ( !empty( $controller_obj->csvMap ) )
        {
            $labels     = [];
            $deep       = is_array( current( $controller_obj->csvMap ) );

            if ( $deep )
            {
                $groups .= '<tr>';

                foreach ( $controller_obj->csvMap as $group_name => $group )
                {
                    $colspan    = count( $group[ 'columns' ] );
                    $background = isset( $group[ 'background' ] )
                        ? 'background: ' . $group[ 'background' ] . ';'
                        : '';
                    $color      = isset( $group[ 'color' ] )
                        ? 'color: ' . $group[ 'color' ] . ';'
                        : '';
                    $groups    .= "<th colspan='$colspan' style='{$background}{$color}'>$group_name</th>";
                    $source     = array_merge( $source, array_keys( $group[ 'columns' ] ) );
                    $labels[]   = "<th style='{$background}{$color}'>" . implode(
                        "</th><th style='{$background}{$color}'>",
                        $group[ 'columns' ]
                    ) . '</th>';
                }

                $labels  = implode( $labels );
                $groups .= '</tr>';
            }
        }
        else
        {
            $labels     = '<th>' . implode( '</th><th>', $labels ) . '</th>';
        }
        
        $content        = '<table style="font-family: arial"><thead>';
        $content       .= $groups;
        $content       .= '<tr>' . $labels . '</tr>';
        $content       .= '</thead><tbody>';

        foreach ( $data as $row )
        {
            if ( !$source )
            {
                $content   .= '<tr><td>' . implode( '</td><td>', $row ) . '</td></tr>';
                continue;
            }

            $values     = [];

            foreach ( $source as $label )
            {
                $values[] = $row[ $label ];
            }
            
            $content   .= '<tr><td>' . implode( '</td><td>', $values ) . '</td></tr>';
        }
        
        $content       .= '</tbody></table>';

        header( "Content-Disposition: attachment; filename=$filename" );
        header( "Content-Length: " . strlen( $content ) );

        echo $content;
    }

    /**
     * Configura o header da resposta de acordo com o formato do arquivo.
     */
    public static function setHeader( $type = null, $charset = null )
    {
        parent::setHeader( 'xls', $charset );
    }
}
