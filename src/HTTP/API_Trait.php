<?php

namespace JF\HTTP;

/**
 * Interface que permite a uma feature responder chamadas de API.
 */
trait API_Trait
{
    /**
     * Retorna os métodos HTTP permitidos.
     */
    public static function acceptHTTPMethods()
    {
        return ['get', 'post'];
    }
}
