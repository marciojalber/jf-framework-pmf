<?php

namespace JF\DB\SQL;

/**
 * Trait para criar parâmetros.
 */
trait SQL_MakeParam
{
    /**
     * Cria um patâmetro.
     */
    public function makeParam()
    {
        return ':' . str_replace( '.', '_', uniqid( '', true ) );
    }
}
