<?php

namespace JF\Minifiers;

/**
 * Classe para minificação de arquivos CSS.
 * 
 * @since   01/04/2015
 */
class CSS
{
    /**
     * Padrões de Expressão Regular para minificação.
     */
    public static $patterns         = [
        '/[\n\r\t]/'                => ' ',
        '/ {2,}/s'                  => '',
        '/;{2,}/s'                  => ';',
        '/\/\*.*?\*\//s'            => '',
        '/\s*([;,>\{}:\'\"]+)\s*/s' => '\1',
        '/\( /s'                    => '(',
        '/;}/s'                     => '}',
        '/\( +/s'                   => '(',
        '/ +\)/s'                   => ')',
    ];

    /**
     * Método para minificar uma folha de estilos.
     */
    public static function minify( $files )
    {
        $now        = date( 'Y-m-d H:i:s' );
        $header     = "/**\n";
        $header    .= " * Minified by JF Framework in $now:\n *\n";
        $content    = array();
        $updates    = array();

        foreach ( $files as $file )
        {
            $filesource = DIR_UI . '/' . $file;

            if ( !file_exists( $filesource ) )
            {
                $header .= " * {$file} not found / empty.\n";
                continue;
            }

            $content[]          = self::getContentMinified( $filesource );
            $update_file        = filemtime( $filesource );
            $update_text        = date( 'Y-m-d H:i:s', $update_file );
            $updates[ $file ]   = $update_file;
            $header            .= " * {$file} (last update in {$update_text}).\n";
        }
        
        $header .= " */\n";
        
        return (object) [
            'content'   => $header . implode( $content ),
            'updates'   => $updates,
        ];
    } 

    /**
     * Método para minificar uma folha de estilos.
     */
    private static function getContentMinified( $filesource )
    {
        $content    = file_get_contents( $filesource );

        foreach ( self::$patterns as $pattern => $replace )
        {
            $content = preg_replace( $pattern, $replace, $content );
        }

        return $content;
    }
}
