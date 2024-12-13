<?php

namespace $ns;

/**
 * DTO da tabela '$_table'.
 * 
 * @update Atualizada em $hoje.
 */
#[schema( '$_schema' )]
#[table( '$_table' )]
class $classname extends \JF\DB\DTO
{
    use $traitname;

$_props

    /**
     * Relacionamentos da tabela.
     */
    protected static $fks = $_fks;
}
