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
 * @method  post
 * @param   siape [required] Number
 *          Siape do perito
 */
class ServiceDocParser extends DocBlockParser
{
    /**
     * Obter documentação a partir de um DocBlock.
     */
    public static function getDoc( $doc_comment )
    {
        $parser = self::parse( $doc_comment );
        $tags   = $parser->getTAGs();

        if ( isset( $tags[ 'ignore' ] ) )
        {
            return null;
        }

        $desc   = $parser->getDescription();
        $method = self::getTAGMethod( $tags );
        $params = self::getTAGParams( $tags );
        
        $doc    = [
            'desc'      => $desc,
            'method'    => $method,
            'params'    => $params,
        ];

        return (object) $doc;
    }

    /**
     * Obter o método da requisição na documentação.
     */
    private static function getTAGMethod( $tags )
    {
        $method = isset( $tags[ 'method' ] )
            ? current( $tags[ 'method' ] )
            : null;

        return $method;
    }

    /**
     * Obter os parâmetros do serviço na documentação.
     */
    private static function getTAGParams( $tags )
    {
        $params = [];

        if ( empty( $tags[ 'param' ] ) )
        {
            return [];
        }

        foreach ( $tags[ 'param' ] as $param )
        {
            $parts              = preg_split( '/[\s\t]+/', $param );
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
