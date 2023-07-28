<?php

namespace JF;

/**
 * Classe que envia uma resposta a uma requisição em json.
 *
 * @author  Márcio Jalber <marciojalber@gmail.com>
 * @since   31/08/2015
 */
class XML
{
    /**
     * Método para criar um elemento XML a partir de um array ou objeto.
     */
    public static function create( $element, $data )
    {
        if ( is_scalar( $data ) )
        {
            $basic_xml_body = "<$element>$data</$element>";
            return simplexml_load_string( $basic_xml_body . N );
        }

        $xml = simplexml_load_string( "<$element/>" );
        self::setContent( $xml, $data );
        return $xml;
    }

    /**
     * Método para converter um conteúdo em elementos XML e injetá-lo num objeto XML.
     */
    protected static function setContent( $xml, $data )
    {
        foreach ( $data as $i => $value )
        {
            $valid_tag          = !is_integer( $i ) && !preg_match( '@^\d@', $i );
            $key                = !$valid_tag
                ? 'item'
                : $i;
            $is_scalar_value    = is_null( $value ) || is_scalar( $value );
            
            if ( $is_scalar_value )
            {
                self::addScalarItem( $xml, $key, $value, $valid_tag, $i );
                continue;
            }
            
            if ( is_resource( $value ) )
            {
                self::addResourceItem( $xml, $key, $value );
                continue;
            }

            $child = $xml->addChild( $key );
            self::setContent( $child, $value );
        }
    }

    /**
     * Adiciona um item scalar ao XML.
     */
    protected static function addScalarItem( $xml, $key, $value, $valid_tag, $i )
    {
        $item = $xml->addChild( $key, $value );

        if ( !$valid_tag )
        {
            $item->addAttribute( 'key', $i );
        }
    }

    /**
     * Adiciona um item do tipo recurso ao XML.
     */
    protected static function addResourceItem( $xml, $key, $value )
    {
        $desc_resource = strval( $value ) . ': ' . get_resource_type( $value );
        $xml->addChild( $key, $desc_resource );
    }
}
