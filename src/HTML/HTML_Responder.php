<?php

namespace JF\HTML;

use JF\Config;
use JF\Exceptions\ErrorException;
use JF\FileSystem\Dir;
use JF\HTML\ParserHTML;
use JF\HTTP\Router;
use JF\Markdown\MDParser;
use JF\Messager;
use JF\Reflection\DocBlockParser;

/**
 * Classe que formata e envia resposta das requisições ao cliente.
 */
class HTML_Responder
{
    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function send()
    {
        self::testForJFToolAutoDoc();

        $route      = Router::get( 'route' );

        if ( Router::get( 'type' ) != 'view' )
            return;

        if ( !file_exists( DIR_PAGES ) )
            Dir::makeDir( DIR_PAGES );

        if ( !is_writable( DIR_PAGES ) )
        {
            $msg = Messager::get( 'html', 'path_is_not_writable', DIR_PAGES );
            throw new ErrorException( $msg );
        }

        if ( !ParserHTML::isUpdated( $route ) )
        {
            ParserHTML::makeDoc( $route );
            ParserHTML::parseView( $route );
        }
        
        http_response_code( 200 );
        header( 'Content-Type: text/html; charset=UTF-8' );

        $filename   = ParserHTML::getPagePath( $route );
        
        include $filename;
        exit();
    }

    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function testForJFToolAutoDoc()
    {
        if ( JFTOOL != 'appdoc' )
            return;

        $doc            = [];
        $metadata       = [];

        $routines       = [];
        $dir_routines   = new \FilesystemIterator( DIR_ROUTINES );
        $backups        = Config::get( 'db/prod.backups' );
        $freq_value     = $backups->frequency[ 1 ];
        $frequencies    = [
            's'         => 'segundo',
            'm'         => 'minuto',
            'h'         => 'hora',
            'd'         => 'dia',
        ];
        $freq_measure   = $frequencies[ $backups->frequency[ 0 ] ];
        $freq_measure  .= $freq_value > 1
            ? 's'
            : '';
        $frequency      = "<tr><td>Frequência</td><td>A cada $freq_value $freq_measure</td></tr>";
        $keep_value     = $backups->keep[ 1 ];
        $keep_measure   = $frequencies[ $backups->keep[ 0 ] ];
        $keep_measure  .= $keep_value
            ? 's'
            : '';
        $keep           = "<tr><td>Manter por</td><td>$keep_value $keep_measure</td></tr>";
        $schemas        = implode( ', ', $backups->schemas );
        $schemas        = "<tr><td>Esquemas de conexão</td><td>$schemas</td></tr>";
        $hosts          = implode( ', ', $backups->hosts );
        $hosts          = "<tr><td>Hosts autorizados a fazer backup</td><td>$hosts</td></tr>";
        $storage        = Config::get( 'products.path', 'products' ) . '/backups';
        $storage        = "<tr><td>Pasta de destino backup</td><td>$storage</td></tr>";

        foreach ( $dir_routines as $item )
        {
            $class_routine  = 'Routines\\' . substr( $item->getFilename(), 0, -4 );
            $reflection     = new \ReflectionClass( $class_routine );
            $rotune_comment = DocBlockParser::parse( $reflection->getDocComment() );
            $ref_expired    = new \ReflectionMethod( $class_routine, 'expired' );
            $expire_comment = DocBlockParser::parse( $ref_expired->getDocComment() );
            $routine        = [
                '<td>' . $class_routine . '</td>',
                '<td>' . $rotune_comment->getDescription() . '</td>',
                '<td>' . $expire_comment->getDescription() . '</td>',
            ];
            $routines[]     = '<tr>' . implode( '', $routine ) . '</tr>';
        }

        $total_routines = count( $routines );
        $routines       = implode( '', $routines );

        self::parseDocViews( DIR_VIEWS, $doc, $metadata );
        
        $summary        = [ '<table class="v-table theme--light elevation-4"><thead><tr><th class="text-xs-left">URL</th><th class="text-xs-left">Módulo</th><th class="text-xs-left">Título</th></tr></thead><tbody>' ];
        $total          = count( $doc );
        $module_key     = Config::get( 'doc.keys.module', 'module' );
        $title_key      = Config::get( 'doc.keys.title', 'title' );
        $desc_key       = Config::get( 'doc.keys.desc', 'desc' );


        foreach ( $doc as $item => &$md )
        {
            if ( !isset( $metadata[ $item ] ) )
            {
                continue;
            }
            
            $module     = $metadata[ $item ][ $module_key ];
            $title      = $metadata[ $item ][ $title_key ];
            $summary[]  = "<tr><td>$item</td><td>$module</td><td>$title</td></tr>";
        }

        $summary[]      = "</tbody><tfoot><tr><th colspan='3'>TOTAL DE PÁGINAS - $total</th></tr></tfoot></table><br><br><br>" . PHP_EOL;

        foreach ( $doc as $item => &$md )
        {
            if ( !isset( $metadata[ $item ] ) )
            {
                continue;
            }

            $module     = $metadata[ $item ][ $module_key ];
            $title      = $metadata[ $item ][ $title_key ];
            $desc       = $metadata[ $item ][ $desc_key ];
            $md         = "##$module | $title <small>(*$item*)</small>" . PHP_EOL . "### $desc" . PHP_EOL . '<br>' . $md . '<br><br><br>';
        }

        $doc        = array_merge( $summary, $doc );
        array_unshift( $doc, '<v-card class="mb-5"><v-card-title primary-title class="display-1">Páginas</v-card-title><v-card-text>' . N );
        $doc[]      = '</v-card-text></v-card>';

        $doc[]      = "<v-card class='mb-5'><v-card-title primary-title class='headline'>Rotinas</v-card-title><v-card-text><table class='v-table theme--light'><thead><tr><th class='text-xs-left'>Nome da Rotina</th><th class='text-xs-left'>Objetivo da Rotina</th><th class='text-xs-left'>Execução</th></tr></thead><tbdoy>$routines</tbdoy><tfoot><tr><th colspan='3'>TOTAL DE ROTINAS - $total_routines</th></tr></tfoot></table></v-card-text></v-card>";
        $doc[]          = "<v-card class='mb-5'><v-card-title primary-title class='headline'>Backups</v-card-title><v-card-text><table class='v-table theme--light'><thead><tr><th class='text-xs-left'>Informação</th><th class='text-xs-left'>Valor</th></tr></thead><tbdoy>{$frequency}{$keep}{$schemas}{$hosts}{$storage}</tbdoy></table></v-card-text></v-card>";

        $doc        = implode( N, $doc );
        $parser     = new MDParser();
        $content    = $parser->parse( $doc );

        header( 'Content-Type: text/html; charset=UTF-8' );

        ob_start();
        $maker      = new PageMaker( $content, true );
        $result     = $maker->makeDoc();

        echo $result->html;exit();
    }

    /**
     * Instancia a classe da rota, executa e envia a resposta ao cliente.
     */
    public static function parseDocViews( $path, &$doc, &$metadata )
    {
        $dir            = new \FilesystemIterator( $path );
        
        foreach ( $dir as $item )
        {
            $itempath   = str_replace( '\\', '/', $item->getPathName() );
            $itemdir    = substr( dirname( $itempath ), strlen( DIR_VIEWS ) ) . '.html';
            $filename   = $item->getFileName();

            if ( $item->isDir() )
            {
                self::parseDocViews( $itempath, $doc, $metadata );
                continue;
            }

            if ( $filename == 'view.md' )
            {
                $doc[ $itemdir ] = PHP_EOL . file_get_contents( $itempath ) . PHP_EOL;
            }

            if ( $filename == 'view.ini' )
            {
                $ini    = parse_ini_file( $itempath, true );
                $data   = $ini[ 'DATA' ];
                $metadata[ $itemdir ] = $data;
            }
        }
    }
}
