<?php

namespace JF\HTTP;

/**
 * Interface que permite a uma feature responder chamadas de API.
 */
trait HTTP_Service_Trait
{
    /**
     * Retorna os métodos HTTP permitidos.
     */
    public static function acceptHTTPMethods()
    {
        return [ 'GET', 'POST' ];
    }
    
    /**
     * Retorna o charset do serviço.
     */
    public function charset()
    {
        return 'UTF-8';
    }
    
    /**
     * Retorna o separador de campos nas respostas em CSV.
     */
    public function separator()
    {
        return ',';
    }
    
    /**
     * Retorna o encapsulador dos campos nas respostas em CSV.
     */
    public function enclosure()
    {
        return '"';
    }
    
    /**
     * Retorna o mapeamento dos campos para respostas em CSV.
     */
    public function csvMap()
    {
        return [];
    }
}
