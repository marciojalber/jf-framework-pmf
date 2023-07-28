<?php

namespace JF\HTML;

use JF\Config;
use JF\FileSystem\Dir;

/**
 * Monta páginas HTML.
 */
final class ParserHTML
{
    /**
     * Verifica o arquivo HTML de uma rota existe e está atualizado.
     */
    public static function isUpdated( $route )
    {
        $html_path              = self::getPagePath( $route );
        $log_path               = self::path( $route, '_view.build' );
        $view_md                = self::path( $route, '_view.md' );
        $view_ini               = self::path( $route, 'view.ini' );

        if ( !file_exists( $html_path ) || !file_exists( $log_path ) )
            return false;

        $last_parse             = json_decode( file_get_contents( $log_path ) );
        $config_servers_path    = Config::path( 'servers' );
        $config_ui_path         = Config::path( 'ui' );
        $has_time_env           = isset( $last_parse->config_servers );
        $has_time_ui            = isset( $last_parse->config_ui );
        $has_time_dependencies  = isset( $last_parse->dependencies );
        $has_SERVER_ADDR        = isset( $last_parse->SERVER_ADDR );
        $has_DIR_BASE           = isset( $last_parse->DIR_BASE );
        $has_view_ini           = isset( $last_parse->view_ini );
        $has_view_md            = isset( $last_parse->view_md );

        if ( !$has_SERVER_ADDR || $_SERVER[ 'SERVER_ADDR' ] != $last_parse->SERVER_ADDR )
            return false;

        if ( !$has_DIR_BASE || DIR_BASE != $last_parse->DIR_BASE )
            return false;

        if ( !$has_time_env || !$has_time_ui || !$has_time_dependencies )
            return false;

        if ( !$has_time_env || !$has_time_ui || !$has_time_dependencies )
            return false;

        if ( !$has_view_ini || !file_exists( $view_ini ) || $last_parse->view_ini < filemtime( $view_ini ) )
            return false;

        if ( !$has_view_md || !file_exists( $view_md ) || $last_parse->view_md < filemtime( $view_md ) )
            return false;

        if ( $last_parse->config_servers < filemtime( $config_servers_path ) )
            return false;

        if ( $last_parse->config_ui < filemtime( $config_ui_path ) )
            return false;

        foreach ( $last_parse->dependencies as $file_source => $file_time )
        {
            $file_source    = DIR_BASE . '/' . $file_source;
            $file_new_time  = file_exists( $file_source )
                ? filemtime( $file_source )
                : null;
            
            if ( !$file_time || !$file_new_time || $file_time < $file_new_time )
                return false;
        }

        return true;
    }

    /**
     * Constrói uma página a partir de uma view.
     */
    public static function parseView( $route )
    {
        $maker      = new PageMaker( $route );
        $result     = $maker->makePage();
        $new_parse  = self::prepareParseLog( $route, $result->depends );

        self::makePagePath( $route );
        $page_path  = self::getPagePath( $route );
        $log_path   = self::path( $route, '_view.build' );
        $parts_path = self::path( $route, '_view.parts' );

        $pretty_json    = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        file_put_contents( $page_path, $result->html );
        file_put_contents( $log_path, json_encode( $new_parse, $pretty_json ) );
        file_put_contents( $parts_path, json_encode( $result->parts, $pretty_json ) );

        self::makePermissionsMixin( $route, $result->permissions );
    }

    /**
     * Prepara o arquivo de log de construção da página.
     */
    public static function prepareParseLog( $route, $dependencies )
    {
        $config_servers_path    = Config::path( 'servers' );
        $config_ui_path         = Config::path( 'ui' );
        $view_ini               = self::path( $route, 'view.ini' );
        $view_md                = self::path( $route, '_view.md' );
        $new_parse              = [
            'time'              => time(),
            'SERVER_ADDR'       => $_SERVER[ 'SERVER_ADDR' ],
            'DIR_BASE'          => DIR_BASE,
            'config_servers'    => file_exists( $config_servers_path )
                ? filemtime( $config_servers_path )
                : null,
            'config_ui'         => file_exists( $config_ui_path )
                ? filemtime( $config_ui_path )
                : null,
            'view_ini'          => file_exists( $view_ini )
                ? filemtime( $view_ini )
                : null,
            'view_md'           => file_exists( $view_md )
                ? filemtime( $view_md )
                : null,
            'dependencies'      => $dependencies,
        ];

        return $new_parse;
    }

    /**
     * Retorna o caminho para o arquivo de página.
     */
    public static function getPagePath( $route )
    {
        return DIR_UI . '/pages/' . $route . '.html';
    }

    /**
     * Retorna o caminho para o arquivo de página.
     */
    public static function makePagePath( $route )
    {
        $route_parts    = explode( '/', $route );
        array_pop( $route_parts );
        $path_route     = DIR_UI . '/pages/' . implode( '/', $route_parts );
        Dir::makeDir( $path_route );
    }

    /**
     * Retorna o caminho para o log de construção da página.
     */
    public static function path( $route, $filename )
    {
        return DIR_VIEWS . "/$route/$filename";
    }

    /**
     * Cria o arquivo com as permissões requeridas pela página.
     */
    public static function makePermissionsMixin( $route, $permissions )
    {
        $path_route     = DIR_UI . '/pages/' . $route;
        Dir::makeDir( $path_route );

        $file_mixin     = $path_route . '/permissions.js';

        $permissions    = json_encode( array_values( $permissions ) );
        $mixin_content  = "var permissionsRequired = $permissions;";
        file_put_contents( $file_mixin, $mixin_content );
    }

    /**
     * Cria a documentação da página.
     */
    public static function makeDoc( $route )
    {
        $content    = [];
        $path_route = DIR_VIEWS . '/' . $route . '/';
        $http_route = '/pages/ui/' . $route . '.html';
        $content[]  = '#' . $http_route;
        $content[]  = '';
        
        if ( file_exists( $path_route . '_view.md' ) )
        {
            $content[]  = "Descrição" . PHP_EOL . '---------' . PHP_EOL;
            $content[]  = file_get_contents( $path_route . '_view.md' );
        }
        
        $content    = implode( PHP_EOL, $content );
        file_put_contents( $path_route . '_view.document', $content );
    }
}
