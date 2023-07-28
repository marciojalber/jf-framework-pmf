<?php

namespace JF\DB;

/**
 * Classe que armazena as propriedades de um ActiveRecord.
 */
trait ActiveRecord_Props
{
    /**
     * Esquema de conexão com o banco-de-dados.
     */
    protected static $schema    = null;
    
    /**
     * Nome da tabela representada pelo model.
     */
    protected static $table    = null;
    
    /**
     * Define quais propriedades serão visíveis na exportação dos dados.
     * Se não informado, todas as propriedades serão visíveis.
     */
    protected static $show      = [];
    
    /**
     * Define quais propriedades ficarão ocultas na exportação dos dados.
     * Se uma propriedade for declarada em $show, declará-la em $hide fica sem efeito.
     */
    protected static $hide      = [];
    
    /**
     * Definição das propriedades do registro.
     */
    protected static $props     = [];
    
    /**
     * Definição dos relacionamentos da tabela.
     */
    protected static $relations = [];

    /**
     * Valores persistidos no banco-de-dados.
     */
    protected $saved_values     = [];

    /**
     * Valores atuais do registro.
     */
    protected $values           = [];

    /**
     * Valores decorrentes de relacionamentos e de inserção insegura.
     */
    protected $alt_values       = [];

    /**
     * Erros ocorridos ao tentar alterar os valores do registro.
     */
    protected $val_errors       = [];

    /**
     * Estado do registro.
     */
    protected $status           = 'creating';

}
