<?php

namespace JF\Domain;

/**
 * Classe de funcionalidades do domínio.
 */
trait FeatureReturnTextTrait
{
    /**
     * Textos de retorno.
     */
    protected $_texts;

    /**
     * Retorna um determinado texto.
     */
    public function getReturnText( $index )
    {
        if ( is_null( $this->_texts ) )
            $this->loadTexts();

        return $this->_texts->TEXTS->$index ?? $index;
    }

    /**
     * Carrega os textos.
     */
    public function loadTexts()
    {
        $filename       = __DIR__ . '/return-texts.ini';
        $texts          = parse_ini_file( $filename, true );
        $this->_texts   = (object) $texts;
    }
}
