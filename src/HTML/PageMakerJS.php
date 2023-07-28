<?php

namespace JF\HTML;

use JF\FileSystem\Dir;

/**
 * Trait da operação para montar a tag script.
 */
trait PageMakerJS
{
    /**
     * Inclue um script marcando o tempo de modificação do arquivo,
     * para forçar atualização pelo navegador do cliente.
     */
    public function minifyJS( $js_path, $use_route_path = false )
    {
        if ( ENV_DEV )
            return $this->js( $js_path, $use_route_path );

        $real_path                  = $this->getRealPath( $js_path, $use_route_path );
        $file_source                = $use_route_path
            ? DIR_VIEWS . '/' . $real_path
            : DIR_UI . '/' . $real_path;
        $real_path                  = preg_replace( '@.js$@', '.min.js', $real_path );
        $js_name                    = substr( $file_source, strlen( DIR_BASE ) + 1 );

        $this->depends[ $js_name ]  = null;

        if ( !file_exists( $file_source ) )
        {
            return ENV_DEV
                ? "<!-- ARQUIVO DO SCRIPT NÃO ENCONTRADO: $file_source -->"
                : '';
        }

        $ui_target                  = $use_route_path
            ? 'pages/' . preg_replace( '@@', '', $real_path )
            : $real_path;
        
        $content                    = \App\App::minifyJS( $file_source );
        $this->copyJsFileToTarget( $use_route_path, $content, $ui_target, true );

        $filetime                   = filemtime( $file_source );
        $this->depends[ $js_name ]  = $filetime;

        return $this->mountJsScript( $use_route_path, $ui_target, $filetime );
    }

    /**
     * Inclue um script marcando o tempo de modificação do arquivo,
     * para forçar atualização pelo navegador do cliente.
     */
    public function js( $js_path, $use_route_path = false )
    {
        $real_path                  = $this->getRealPath( $js_path, $use_route_path );
        $file_source                = $use_route_path
            ? DIR_VIEWS . '/' . $real_path
            : DIR_UI . '/' . $real_path;

        $js_name                    = substr( $file_source, strlen( DIR_BASE ) + 1 );

        $this->depends[ $js_name ]  = null;

        if ( !file_exists( $file_source ) )
        {
            return ENV_DEV
                ? "<!-- ARQUIVO DO SCRIPT NÃO ENCONTRADO: $file_source -->"
                : '';
        }
        
        $ui_target                  = $use_route_path
            ? 'pages/' . $real_path
            : $real_path;

        $this->copyJsFileToTarget( $use_route_path, $file_source, $ui_target );

        $filetime                   = filemtime( $file_source );
        $this->depends[ $js_name ]  = $filetime;

        return $this->mountJsScript( $use_route_path, $ui_target, $filetime );
    }

    /**
     * Copia o arquivo da pasta das views para a pasta pública.
     */
    private function copyJsFileToTarget( $use_route_path, $source, $ui_target, $source_is_content = false )
    {
        if ( !$use_route_path && !$source_is_content )
            return;

        $filetarget     = DIR_UI . '/' . $ui_target;
        $target_path    = dirname( $filetarget );
        
        if ( !file_exists( $target_path ) )
            Dir::makeDir( $target_path );

        if ( !$use_route_path )
            return file_put_contents( $filetarget, $source );
        
        if ( file_exists( $filetarget ) )
            @unlink( $filetarget );
        
        if ( !$source_is_content )
            return copy( $source, $filetarget );

        file_put_contents( $filetarget, $source );
    }

    /**
     * Monta o texto da tag script.
     */
    private function mountJsScript( $use_route_path, $ui_target, $filetime )
    {
        $filejs     = $this->ui() . $ui_target;
        $src        = 'src="' . $filejs . '?v=' . $filetime . '"';
        $script     = "<script {$src}></script>";
        
        return $script;
    }
}
