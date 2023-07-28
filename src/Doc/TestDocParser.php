<?php

namespace JF\Doc;

use JF\Reflection\DocBlockParser;

/**
 * Classe para gerar documentação da aplicação.
 *
 * @example
 *
 * Linha 1.
 * Linha 2.
 * 
 * Linha 3.
 * 
 * @ignore
 * @given   Dados
 * @when    Ação
 * @then    Resultado
 */
class TestDocParser extends DocBlockParser
{
    /**
     * Obter documentação a partir de um DocBlock.
     */
    public static function getDoc( $doc_comment )
    {
        $parser = self::parse( $doc_comment );
        $tags   = $parser->getTags();

        if ( isset( $tags[ 'ignore' ] ) )
        {
            return null;
        }

        $desc   = $parser->getDescription();
        $given  = self::getTAGsBDD( $tags, 'given' );
        $when   = self::getTAGsBDD( $tags, 'when' );
        $then   = self::getTAGsBDD( $tags, 'then' );
        
        $doc    = [
            'desc'  => $desc,
            'given' => $given,
            'when'  => $when,
            'then'  => $then,
        ];

        return (object) $doc;
    }

    /**
     * Obter os parâmetros do serviço na documentação.
     */
    private static function getTAGsBDD( $tags, $name )
    {
        return !empty( $tags[ $name ] )
            ? $tags[ $name ]
            : [];

        $tags_bdd = [];

        if ( empty( $tags[ $name ] ) )
        {
            return [];
        }

        foreach ( $tags[ $name ] as $tag )
        {
            $parts              = preg_split( '/[\s\t]+/', $tag );
            $name               = array_shift( $parts );
            $required           = false;

            if ( $parts[ 0 ] == '[required]' )
            {
                $required       = 'required';
                array_shift( $parts );
            }
            
            $type               = array_shift( $parts );
            $params[ $name ]    = [
                'type'          => $type,
                'required'      => $required,
                'content'       => implode( ' ', $parts ),
            ];
        }
        
        return $params;
    }
}
