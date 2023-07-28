<?php

namespace JF\Doc;

use JF\Config;
use JF\Domain\Test;
use JF\Exceptions\ErrorException;
use JF\FileSystem\Dir;
use JF\Messager;
use JF\Reflection\DocBlockParser;

/**
 * Classe para gerar documentação da aplicação.
 */
class DocParser
{
    /**
     * Armazena os dados do time do projeto.
     */
    private $team       = [];

    /**
     * Armazena os dados das tecnologias utilizadas.
     */
    private $tech       = [];

    /**
     * Armazena os dados das páginas do projeto.
     */
    private $pages      = [];

    /**
     * Armazena os dados dos serviço do projeto.
     */
    private $services   = [];

    /**
     * Armazena os dados das funcionalidades do projeto.
     */
    private $features   = [];

    /**
     * Armazena os dados das rotinas do projeto.
     */
    private $routines   = [];

    /**
     * Gera a documentação.
     */
    public static function run()
    {
        if ( !ENV_DEV )
        {
            return;
        }

        $doc = new self();

        $doc->getDocTeam();
        $doc->getDocTech();
        $doc->getDocPages( DIR_VIEWS );
        $doc->getDocServices( DIR_FEATURES );
        $doc->getDocFeatures( DIR_FEATURES );
        $doc->getDocRoutines();

        $doc->save();
        
        /*
        $content = file_get_contents( DIR_PRODUCTS . '/doc/document.html' );
        echo '<pre>';
        echo $content;
        echo '</pre>';
        exit;
        */
    }

    /**
     * Captura o time do projeto.
     */
    public function getDocTeam()
    {
        $this->getDocContext( 'team' );
    }

    /**
     * Captura as tecnologias utilizadas no projeto.
     */
    public function getDocTech()
    {
        $this->getDocContext( 'tech' );
    }

    /**
     * Captura as tecnologias utilizadas no projeto.
     */
    public function getDocContext( $context )
    {
        $context_path  = DIR_PRODUCTS . '/doc/' . $context;

        if ( !file_exists( $context_path ) )
        {
            Dir::makeDir( $context_path );
        }

        $context_obj   = new \FileSystemIterator( $context_path );

        foreach ( $context_obj as $item )
        {
            $filename       = str_replace( '\\', '/', $item->getPathName() );
            $$context       = parse_ini_file( $filename );

            foreach ( $$context as $label => &$data )
            {
                $data       = utf8_encode( str_pad( $label, 15, ' ' ) . ': ' . $data );
            }

            $this->{$context}[]   = implode(
                N,
                array_values( $$context )
            );
        }

        $this->$context = implode( N . N, $this->$context );
    }

    /**
     * Captura a documentação das páginas.
     */
    public function getDocPages( $path )
    {
        $path_obj   = new \FileSystemIterator( $path );
        $prod_env   = Config::get( 'app.product_env' );

        foreach ( $path_obj as $item )
        {
            $item_name = str_replace( '\\', '/', $item->getPathName() );

            if ( $item->isDir() )
            {
                $this->getDocPages( $item_name );
                continue;
            }

            if ( !$item->isFile() || substr( $item_name, -8 ) !== 'view.php' )
            {
                continue;
            }
            
            $doc_source     = str_replace( 'view.php', '.document', $item_name );
            $url_page       = '..' . substr( $item_name, strlen( DIR_VIEWS ), -9 ) . '.html';
            $page_doc       = 'URL PAGE       : ' . $url_page . N;
            $page_doc      .= file_exists( $doc_source )
                ? 'DESCRIPTION    : ' . file_get_contents( $doc_source ) . N
                : '';

            $this->pages[]  = $page_doc;
        }
    }

    /**
     * Captura a documentação dos serviços.
     */
    public function getDocServices( $path )
    {
        $path_obj   = new \FileSystemIterator( $path );
        $len_prefix = strlen( 'Features/' );
        $len_sufix  = strlen( '\\Controller' );

        foreach ( $path_obj as $item )
        {
            $pathname = str_replace( '\\', '/', $item->getPathName() );

            if ( $item->isDir() )
            {
                $this->getDocServices( $pathname );
                continue;
            }

            if ( !$item->isFile() || substr( $pathname, -14 ) !== 'Controller.php' )
            {
                continue;
            }

            $len_dirbase    = strlen( DIR_BASE );
            $classname      = substr( $pathname, $len_dirbase + 1, -4 );
            $namespaces     = Config::get( 'namespaces' );

            foreach ( $namespaces as $namespace => $local )
            {
                if ( strpos( $classname, $local ) === 0 )
                {
                    $classname = $namespace . substr( $classname, strlen( $local ) );
                }
            }

            $classname          = str_replace( '/', '\\', $classname );
            
            if ( !class_exists( $classname ) )
            {
                continue;
            }

            $reflection         = new \ReflectionClass( $classname );
            $comment            = $reflection->getDocComment();
            $doc                = ClassDocParser::getDoc( $comment );

            if ( !$doc )
            {
                continue;
            }

            $url_controller     = substr( $reflection->name, $len_prefix, -$len_sufix );
            $url_controller     = strtolower( $url_controller );
            $url_controller     = str_replace( '\\', '/', $url_controller );
            $url_controller     = URL_BASE . '/' . str_replace( '_', '-', $url_controller );
            $method             = $classname::$post
                ? 'POST'
                : 'GET';
            $content            =
                '[SERVICE]' . N .
                'URL_SERVICE    = "' . $url_controller . '"' . N .
                'DESCRIPTION    = "' . $doc->desc . '"' . N .
                'METHOD         = "' . $method . '"';

            if ( $classname::$expect )
            {
                $content       .= N . N . '[PARAMS]';
                $entity_name    = substr( $classname, 0, -$len_sufix ) . '\\Entity';
                $props          = (object) [
                    'entity'    => $entity_name::export()->props,
                    'feature'   => [],
                ];

                foreach ( $classname::$expect as $key => $value )
                {
                    $map        = explode( '.', $value );
                    $source     = $map[ 0 ];
                    $prop       = $map[ 1 ];
                    $content    .= N . $key . ' = "' . $props->$source->$prop->label . '"';
                }
            }

            $docpath            = dirname( $pathname ) . '/_service.document';
            file_put_contents( $docpath, $content );
        }
    }

    /**
     * Captura a documentação das funcionalidades do sistema.
     */
    public function getDocFeatures( $path )
    {
        $path_obj   = new \FileSystemIterator( $path );

        foreach ( $path_obj as $item )
        {
            $pathname = str_replace( '\\', '/', $item->getPathName() );

            if ( $item->isDir() )
            {
                $this->getDocFeatures( $pathname );
                continue;
            }

            $len_dirbase    = strlen( DIR_BASE );
            $classname      = substr( $pathname, $len_dirbase + 1, -4 );
            $namespaces     = Config::get( 'namespaces' );

            foreach ( $namespaces as $namespace => $local )
            {
                if ( strpos( $classname, $local ) === 0 )
                {
                    $classname = $namespace . substr( $classname, strlen( $local ) );
                }
            }

            $classname      = str_replace( '/', '\\', $classname );

            if ( !is_subclass_of( $classname, 'JF\\Domain\\Feature' ) )
            {
                continue;
            }

            $reflection         = new \ReflectionClass( $classname );
            $comment            = $reflection->getDocComment();
            $doc                = ClassDocParser::getDoc( $comment );

            if ( !$doc )
            {
                continue;
            }

            $props              = $reflection->getProperties();
            $inputs             = [];

            foreach ( $props as $prop )
            {
                $comment        = $prop->getDocComment();
                $doc_input      = InputDocParser::getDoc( $comment );

                if ( !$doc_input )
                {
                    continue;
                }

                $input          = $doc_input->required
                    ? '*'
                    : '';
                $input         .= $prop->getName();
                $input         .= $doc_input->type
                    ? " ($doc_input->type)"
                    : '';
                $input         .= $doc_input->desc
                    ? ' - ' . $doc_input->desc
                    : '';
                $inputs[]       = $input;
            }

            $methods            = $reflection->getMethods();
            $rules              = [];

            foreach ( $methods as $method )
            {
                $method_name    = $method->getName();

                if ( substr( $method_name, 0, 4 ) != 'rule' )
                {
                    continue;
                }

                $comment        = $method->getDocComment();
                $doc_rule       = RuleDocParser::getDoc( $comment );
                $rules[]        = $doc_rule->desc;
            }

            $test_class         = $classname . Test::SUFIX;
            $tests              = [];
            $reflection_test    = class_exists( $test_class )
                ? new \ReflectionClass( $test_class )
                : null;

            if ( $reflection_test && is_subclass_of( $test_class, 'JF\\Domain\\Test' ) )
            {
                $methods        = $reflection_test->getMethods();

                foreach ( $methods as $method )
                {
                    $test_name  = $method->getName();

                    if ( substr( $test_name, 0, 5 ) != 'test_' )
                    {
                        continue;
                    }

                    $comment    = $method->getDocComment();
                    $doc_test   = TestDocParser::getDoc( $comment );
                    $tests[]    = 'Scene: ' . $doc_test->desc;

                    foreach ( $doc_test->given as $given )
                    {
                        $tests[]    = '  Dado que ' . $given;
                    }

                    foreach ( $doc_test->when as $when )
                    {
                        $tests[]    = '  Quando ' . $when;
                    }

                    foreach ( $doc_test->then as $then )
                    {
                        $tests[]    = '  Então ' . $then;
                    }
                    $tests[]    = '';
                }
            }

            $separator          = N . str_repeat( ' ', 17 );
            $inputs             = implode( $separator, $inputs );
            $rules              = implode( $separator, $rules );
            $tests              = implode( N, $tests );
            $this->features[]   =
                'CLASS          : ' . $classname . N .
                'FEATURE        : ' . $doc->desc . N .
                'INPUTS         : ' . $inputs . N .
                'RULES          : ' . $rules . N . N .
                '*** Tests ***' . N . N . $tests . N . N . '---';
        }
    }

    /**
     * Captura as tecnologias utilizadas no projeto.
     */
    public function getDocRoutines()
    {
        $rotuines_obj           = new \FileSystemIterator( DIR_ROUTINES );

        foreach ( $rotuines_obj as $item )
        {
            $filename           = str_replace( '\\', '/', $item->getFileName() );
            $filename           = substr( $filename, 0, -4 );
            $classname          = 'Routines\\' . str_replace( '-', '_', $filename );
            $routine_name       = substr( $filename, 0, -9 );

            if ( !class_exists( $classname ) )
            {
                $msg            = Messager::get( 'doc', 'routine_not_exists', $classname );
                throw new ErrorException( $msg );
            }

            $reflection         = new \ReflectionClass( $classname );
            $classname          = $reflection->name;
            $parser             = DocBlockParser::parse( $reflection->getDocComment() );
            $description        = $parser->getDescription();
            $this->routines[]   =
                'NAME           : ' . $classname . N .
                'DESCRIPTION    : ' . $description . N;
        }
    }

    /**
     * Salva a documentação.
     */
    public function save()
    {
        /*
        ob_start();
        include DIR_APP . '/templates/pdf/docmodel.php';
        header( 'Content-Type: text/html; charset=UTF8' );
        $document = ob_get_clean();
        echo $document;exit;
        */
        $filename   = DIR_PRODUCTS . '/doc/document.html';
        $content    = [];
        $content[]  = 'TEAM' . N . '====' . N . N . $this->team . N;
        $content[]  = 'TECNOLOGIES' . N . '===========' . N . N . $this->tech . N;
        $content[]  = 'PAGES' . N . '=====' . N . N . implode( N, $this->pages ) . N;
        $content[]  = 'FEATURES' . N . '========' . N . N . implode( N, $this->features ) . N;
        $content[]  = 'ROUTINES' . N . '========' . N . N . implode( N, $this->routines ) . N;
        $content    = implode( N . N, $content );
        file_put_contents( $filename, $content );
    }
}
