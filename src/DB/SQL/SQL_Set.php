<?php

namespace JF\DB\SQL;

/**
 * Classe para montar consultas SQL.
 */
trait SQL_Set
{
    /**
     * Armazena os valores a serem inseridos na tabela.
     */
    protected $values = [];

    /**
     * Informa os valores a serem inseridos na tabela.
     */
    public function set( $values )
    {
        $this->values = $values;

        return $this;
    }
}
