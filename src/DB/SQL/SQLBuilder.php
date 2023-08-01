<?php

namespace JF\DB\SQL;

/**
 * Trait para executar a exclusão de registros.
 */
class SQLBuilder
{
    /**
     * DAO da consulta.
     */
    protected $dto;

    /**
     * Opções da consulta.
     */
    protected $opts = [];

    /**
     * Mensagem de erro em caso de falha na execução da operação.
     */
    protected $msgOnFail;

    /**
     * Se o valor passado estiver vazio, lança uma exceção de erro.
     */
    public function onFail( $msg )
    {
        $this->msgOnFail = $msg;
        return $this;
    }
}
