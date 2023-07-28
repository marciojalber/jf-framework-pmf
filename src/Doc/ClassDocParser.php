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
 */
class ClassDocParser extends DocBlockParser
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
        
        $doc    = [
            'desc'      => $desc,
        ];

        return (object) $doc;
    }
}
