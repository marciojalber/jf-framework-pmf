<?php

namespace JF\DB\SQL;

/**
 * Trait para executar a inserção de registro.
 */
trait SQL_Into
{
	/**
	 * Armazena o nome da tabela onde serão inseridos os dados.
	 */
	protected $table = null;

    /**
     * Define a tabela onde serão inseridos os dados.
     */
    public function into( $table )
    {
    	$this->table = $table;

    	return $this;
    }
}
