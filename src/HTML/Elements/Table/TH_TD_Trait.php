<?php

namespace JF\HTML\Elements\Table;

/**
 * Características compartilhadas entre <th> e <td>.
 */
trait TH_TD_Trait
{
    /**
     * Número de colunas mescladas.
     */
    public $colspan;

    /**
     * Número de linhas mescladas.
     */
    public $rowspan;

    /**
     * Informa a quantidade de colunas a mesclar.
     */
    public function colspan( $colspan )
    {
        $this->colspan = $colspan;

        return $this;
    }

    /**
     * Informa a quantidade de linhas a mesclar.
     */
    public function rowspan( $rowspan )
    {
        $this->rowspan = $rowspan;

        return $this;
    }

    /**
     * Informa o calpso de linhas.
     */
    protected function mount()
    {
        $props = [];

        if ( $this->colspan )
            $props[] = "colspan='{$this->colspan}'";

        if ( $this->rowspan )
            $props[] = "rowspan='{$this->rowspan}'";

        return $props;
    }
}
