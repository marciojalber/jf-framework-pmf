<?php

namespace JF\DB\SQL;

/**
 * Trait para informar o limit de registros do retorno da consulta.
 */
trait SQL_Limit
{
	/**
	 * Armazena o limite de registros da consulta.
	 */
	protected $limit = 0;

	/**
	 * Indica o limite da consulta.
	 */
	public function limit( $limit = 0 )
	{
		$this->limit = $limit;

		return $this;
	}

	/**
	 * Retorna o limite da consulta.
	 */
	public function getLimit()
	{
		return $this->limit;
	}
}
