<?php

namespace JF\HTML\Elements;

/**
 * Entidade representativa do CSS.
 */
class CSS
{
    /**
     * Estilos nomeados.
     */
    protected static $namedStyles = [];

    /**
     * Define um alias para um estilo.
     */
    public static function set( $name, $style )
    {
        self::$namedStyles[ $name ] = $style;
    }

    /**
     * Retorna o conteúdo da referência de um estilo nomeado ou sua expressão como um estilo.
     */
    public static function get( $expression )
    {
        return self::$namedStyles[ $expression ] ?? $expression;
    }
}
