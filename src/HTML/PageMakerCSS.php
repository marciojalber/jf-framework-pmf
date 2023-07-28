<?php

namespace JF\HTML;

/**
 * Trait da operação para montar a tag script.
 */
trait PageMakerCSS
{
    /**
     * Inclue uma folha de estilo marcando o tempo de modificação do arquivo,
     * para forçar atualização pelo navegador do cliente.
     */
    public function css( $filename, array $options = array() )
    {
        // Verifica se existe o arquivo de estilo
        $min        = !empty( $options[ 'min' ] );
        $prefix     = $min
            ? 'css/'
            : '';
        $sufix      = $min
            ? '.min.css'
            : '';
        $file_source    = 'ui/' . $prefix . $filename . $sufix;
        $filepath       = DIR_BASE . '/' . $file_source;

        if ( $min && ENV_DEV )
        {
            $min_files      = Config::get( 'minification.css.' . $filename );
            $min_monitor    = DIR_TEMP . '/minification/css/' . $filename . '.php';

            if ( !file_exists( $min_monitor ) || !file_exists( $filepath ) )
            {
                self::makeMin( 'css', $filename, $min_files, $min_monitor );
            }
            else
            {
                $times  = include $min_monitor;

                foreach ( $times as $file => $time )
                {
                    $filesource = DIR_UI . '/' . $file;
                    $file_time  = file_exists( $filesource )
                        ? filemtime( $filesource )
                        : null;

                    if ( $file_time !== $time )
                    {
                        self::makeMin( 'css', $filename, $min_files, $min_monitor );
                        break;
                    }
                }
            }
        }

        if ( !file_exists( $filepath ) )
        {
            $this->depends[ $file_source ] = null;
            return ENV_DEV
                ? "<!-- ARQUIVO DO ESTILO NÃO ENCONTRADO: {$filepath} -->"
                : '';
        }

        // Retorna a tag do arquivo de estilo
        $filetime   = filemtime( $filepath );
        $this->depends[ $file_source ] = $filetime;
        $media      = isset( $options[ 'media' ] )
            ? $options[ 'media' ]
            : 'all';
        $media      = "media='{$media}'";
        
        $rel        = isset( $options[ 'rel' ] )
            ? $options[ 'rel' ]
            : 'stylesheet';
        $rel        = "rel='{$rel}'";
        $type       = 'type="text/css"';
        $filecss    = $this->ui() . $prefix . $filename . $sufix;
        $href       = "href='{$filecss}?v={$filetime}'";
        $link       = "<link {$rel} {$type} {$href} {$media} />";

        return $link;
    }
}
