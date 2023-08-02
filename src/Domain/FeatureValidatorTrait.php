<?php

namespace JF\Domain;

use JF\Exceptions\WarningException as Warning;
use JF\Types\DateTime__Type;

/**
 * Classe de funcionalidades do domínio.
 */
trait FeatureValidatorTrait
{
    /**
     * Verifica se a variável não está vazia.
     */
    public function isNotEmpty( $val, $msg )
    {
        if ( !empty( $val ) )
            return $this;

        $msg = $this->getReturnText( $msg );

        throw new Warning( $msg );
    }

    /**
     * Verifica se a variável é uma data válida.
     */
    public function isDate( $val, $msg )
    {
        if ( DateTime__Type::validateDate( $val ) )
            return $this;

        $msg = $this->getReturnText( $msg );

        throw new Warning( $msg );
    }

    /**
     * Verifica se a variável é menor que um determinado valor.
     */
    public function isLT( $val, $comparator, $msg )
    {
        if ( $val < $comparator )
            return $this;

        $msg = $this->getReturnText( $msg );

        throw new Warning( $msg );
    }

    /**
     * Verifica se a variável é menor ou igual a um determinado valor.
     */
    public function isLTE( $val, $comparator, $msg )
    {
        if ( $val <= $comparator )
            return $this;

        $msg = $this->getReturnText( $msg );

        throw new Warning( $msg );
    }

    /**
     * Verifica se a variável é maior que um determinado valor.
     */
    public function isLG( $val, $comparator, $msg )
    {
        if ( $val > $comparator )
            return $this;

        $msg = $this->getReturnText( $msg );

        throw new Warning( $msg );
    }

    /**
     * Verifica se a variável é maior ou igual a um determinado valor.
     */
    public function isLGE( $val, $comparator, $msg )
    {
        if ( $val >= $comparator )
            return $this;

        $msg = $this->getReturnText( $msg );

        throw new Warning( $msg );
    }

    /**
     * Verifica se a variável está compreendida num determinado intervalo.
     */
    public function isIN( $val, $values, $msg )
    {
        if ( in_array( $val, $values ) )
            return $this;

        $msg = $this->getReturnText( $msg );

        throw new Warning( $msg );
    }
}
