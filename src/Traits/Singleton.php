<?php

namespace JF\Traits;

/**
 * Implementa o padrão Singleton em uma classe.
 *
 * @author   Márcio Jalber <marciojalber@gmail.com>
 * @since    03/06/2015
 */
trait Singleton
{
    /**
     * Guarda a instância do objeto.
     */
    protected static $instance = null;

    /**
     * Invoca a instância da classe.
     */
    public static function call()
    {
        if ( !self::$instance )
        {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Evita a instanciação.
     */
    protected function __construct()
    {

    }

    /**
     * Evita a clonagem do objeto.
     */
    protected function __clone()
    {

    }

    /**
     * Evita a deserialização do objeto.
     */
    protected function __wakeup()
    {

    }

}
