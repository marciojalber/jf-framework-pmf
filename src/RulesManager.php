<?php

namespace JF;

/**
 * Gerenciador das regras de negócio.
 */
class RulesManager
{
    /**
     * Regras a serem aplicadas.
     */
    protected static $rules = [];

    /**
     * Encontra um gerenciador para uma determinada regra de negócio.
     */
    public static function find( $contexto )
    {
        $class_ruler = 'App\\Rules\\' . str_replace( '.', '\\', $contexto ) . '__Ruler';

        return class_exists( $class_ruler )
            ? new $class_ruler()
            : new self();
    }

    /**
     * Define um valor para o gerenciador de regras.
     */
    public function set( $key, $value )
    {
        $this->$key = $value;

        return $this;
    }

    /**
     * Aplica as regras de negócio da operação.
     */
    public function apply()
    {
        foreach ( static::$rules as $i => $rule )
        {
            $this->$rule();
        }

        return $this;
    }
}
