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
 * @type    mixed
 * @required
 */
class InputDocParser extends DocBlockParser
{
    /**
     * Obter documentação a partir de um DocBlock.
     */
    public static function getDoc( $doc_comment )
    {
        $parser     = self::parse( $doc_comment );
        $tags       = $parser->getTAGs();

        if ( isset( $tags[ 'ignore' ] ) )
        {
            return null;
        }

        $desc       = $parser->getDescription();
        $type       = self::getTAGType( $tags );
        $required   = self::getTAGRequired( $tags );
        
        $doc        = [
            'desc'      => $desc,
            'type'      => $type,
            'required'  => $required,
        ];

        return (object) $doc;
    }

    /**
     * Obter o método da requisição na documentação.
     */
    private static function getTAGRequired( $tags )
    {
        $required = isset( $tags[ 'required' ] )
            ? current( $tags[ 'required' ] )
            : null;

        return $required;
    }

    /**
     * Obter os parâmetros do serviço na documentação.
     */
    private static function getTAGType( $tags )
    {
        $type = isset( $tags[ 'type' ] )
            ? current( $tags[ 'type' ] )
            : null;

        return $type;
    }
}
