<?php

namespace JF\HTTP\Responders;

use JF\HTTP\Responder;
use JF\FileSystem\Dir;

/**
 * Classe que formata e envia resposta das requisições ao cliente.
 */
class CSV_Responder extends Responder
{
    /**
     * Armazena os header do tipo de resposta.
     */
    protected static $headers = [ 'text/csv' ];

    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function send( $data, $controller_obj )
    {
        $parts          = explode( '\\', get_class( $controller_obj ) );
        array_pop( $parts );
        $control_name   = array_pop( $parts );
        // self::setHeader( 'csv', $controller_obj->charset() );
        $filename       = isset( $controller_obj->filename )
            ? $controller_obj->filename . '.csv'
            : $control_name . '.csv';
        $data           = json_decode( json_encode( $data ), true );
        $data           = gettype( current( $data ) ) !== 'array'
            ? array( $data )
            : $data;
        $labels         = array_keys( current( $data ) );

        foreach ( $controller_obj->csvMap() as $old_label => $new_label )
        {
            unset( $controller_obj->csvMap()[ $old_label ] );

            $pos_label = array_search( $old_label, $labels );
            
            if ( $pos_label !== false )
            {
                $labels[ $pos_label ] = $new_label;
            }
        }
        
        $tmp_dir        = DIR_PRODUCTS . "/temp";
        file_exists( $tmp_dir ) || Dir::makeDir( $tmp_dir );
        $tmp_filename   = microtime( true ) * 10000;
        $tmp_filename   = "{$tmp_dir}/{$tmp_filename}.csv";
        $file           = new \SplFileObject( $tmp_filename, 'w' );
        $separator      = $controller_obj->separator();
        $enclosure      = $controller_obj->enclosure();
        
        $file->fputcsv( $labels, $separator, $enclosure );

        foreach ( $data as $row )
            $file->fputcsv( $row, $separator, $enclosure );

        $file           = null;

        header( "Content-Disposition: attachment; filename=$filename" );
        header( "Content-Length: " . filesize( $tmp_filename ) );
        readfile( $tmp_filename );
        unlink( $tmp_filename );
    }

    /**
     * Configura o header da resposta de acordo com o formato do arquivo.
     */
    public static function setHeader( $type = null, $charset = null )
    {
        parent::setHeader( 'csv', $charset );
    }
}
