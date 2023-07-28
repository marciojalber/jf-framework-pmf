<?php

namespace JF\Doc;

use JF\HTTP\ControllerParser;
use JF\Reflection\DocBlockParser;

/**
 * Classe para gerar documentação da aplicação.
 */
class DocParserFeature
{
    /**
     * Monta a documentação da feature.
     */
    public static function parse()
    {
        if ( !ENV_DEV )
            return;

        $controller     = ControllerParser::controller();
        $prefix         = 'Features';
        $len_prefix     = strlen( $prefix );
        $len_sufix      = strlen( 'Controller' );
        $entity_class   = substr( $controller, 0, -$len_sufix ) . 'Entity';
        $feature_class  = substr( $controller, 0, -$len_sufix ) . 'Feature';

        if ( substr( $controller, 0, $len_prefix ) != $prefix )
            return;

        $reflection     = new \ReflectionClass( $controller );
        $comment        = $reflection->getDocComment();
        $filename       = $reflection->getFilename();
        $docpath        = dirname( $filename );
        $doc            = DocBlockParser::parse( $comment );

        if ( !$doc )
            return;

        $url_controller     = substr( $reflection->name, $len_prefix, -$len_sufix );
        $url_controller     = strtolower( $url_controller );
        $url_controller     = str_replace( '\\', '/', $url_controller );
        $url_controller     = URL_BASE . str_replace( '_', '-', $url_controller );
        $method             = $controller::$post
            ? 'POST'
            : 'GET';
        $content            =
            '[SERVICE]' . N .
            'URL_SERVICE    = "' . $url_controller . '"' . N .
            'DESCRIPTION    = "' . $doc->getDescription() . '"' . N .
            'METHOD         = "' . $method . '"';

        if ( $controller::$expect )
        {
            $content       .= N . N . '[PARAMS]';
            $props          = (object) [
                'entity'    => class_exists( $entity_class )
                    ? $entity_class::export()->props
                    : [],   
                'feature'   => class_exists( $feature_class )
                    ? $feature_class::export()
                    : [],
            ];

            foreach ( $controller::$expect as $key => $value )
            {
                $map        = explode( '.', $value );
                $source     = $map[ 0 ];
                $index      = $map[ 1 ];
                $prop       = $props->$source;
                $key        = str_pad( $key, 14, ' ' );

                if ( $source == 'feature' )
                {
                    if ( !array_key_exists( $index, $prop ) )
                        die( "A propriedade ($index) não foi declarada na funcionalidade ($feature_class)." );

                    $content   .= N . $key . ' = "' . $prop->$index . '"';
                }

                if ( $source == 'entity' )
                {
                    if ( !array_key_exists( $index, $prop ) )
                        die( "A propriedade ($index) não foi declarada na entidade ($entity_class)." );

                    $content    .= N . $key . ' = "' . $prop->$index->label . '"';
                }
            }
        }

        file_put_contents( $docpath . '/_service.document', $content );

        if ( !class_exists( $feature_class ) )
            return;

        $reflection = new \ReflectionClass( $feature_class );
        $comment    = $reflection->getDocComment();
        $doc        = DocBlockParser::parse( $comment );
        
        $tags       = $doc->getTags();
        $who        = isset( $tags[ 'who' ] )
            ? $tags[ 'who' ][ 0 ]
            : '[USUÁRIO]';
        $goal       = isset( $tags[ 'goal' ] )
            ? $tags[ 'goal' ][ 0 ]
            : '[PROPÓSITO]';
        $how        = isset( $tags[ 'how' ] )
            ? $tags[ 'how' ][ 0 ]
            : '[AÇÃO]';
        $when       = isset( $tags[ 'when' ] )
            ? $tags[ 'when' ][ 0 ]
            : '[FREQUÊNCIA/DISPARADOR]';
        $content    =
            '[FEATURE]' . N .
            'DESCRIPTION    = "' . $doc->getDescription() . '"' . N .
            "SCENE          = \"Como {$who}, para {$goal}, preciso {$how}, que acontecerá {$when}.\"" . N . N .
            '[WORKFLOW]';

        $feature    = new $feature_class();
        $steps      = $feature->getSteps();

        foreach ( $steps as $step => $desc )
        {
            $step       = str_pad( $step, 14, ' ' );
            $content   .= N . $step . ' = "' . $desc . '"';
        }

        file_put_contents( $docpath . '/_feature.document', $content );
    }
}
