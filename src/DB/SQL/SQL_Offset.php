<?php

namespace JF\DB\SQL;

/**
 * Trait para informar o saldo de registros do retorno da consulta.
 */
trait SQL_Offset
{
	/**
	 * Valor do salto.
	 */
	protected $offset = 0;

	/**
	 * Indica o salto da consulta.
	 */
	public function offset( $offset = 0 )
	{
		$this->offset = $offset;

		return $this;
	}

	/**
	 * Retorna o salto da consulta.
	 */
	public function getOffset()
	{
		return $this->offset;
	}
}
