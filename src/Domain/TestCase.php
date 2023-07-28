<?php

namespace JF\Domain;

/**
 * Classe de testes das operações do domínio.
 */
class TestCase
{
    const SUFIX = '__TestCase';

    /**
     * Retorna o nome da classe de destino dos testes.
     */
    public static function getTarget()
    {
        $class_name = get_called_class();
        $ini_pos    = 0;
        $end_pos    = -strlen( self::SUFIX );
        
        return substr( $class_name, $ini_pos, $end_pos );
    }
}
