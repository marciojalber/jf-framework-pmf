<?php

namespace JF;

use JF\Exceptions\ErrorException;

/**
 * Modelo de tipo de dado.
 */
class Type
{
    /**
     * Valor padrão do dado.
     */
    private static $types           = [
        'enum', 'set',
        'number', 'float',
        'text',
    ];

    /**
     * Valor padrão do dado.
     */
    protected static $default       = null;

    /**
     * Rótulo do dado.
     */
    protected static $label         = null;

    /**
     * Máscara do dado.
     */
    protected static $mask          = null;

    /**
     * Valor máximo para o dado.
     */
    protected static $max           = null;

    /**
     * Máximo de caracteres para o dado.
     */
    protected static $maxlength     = null;

    /**
     * Máximo de itens para o dado.
     */
    protected static $maxitens      = null;

    /**
     * Valor mínimo para o dado.
     */
    protected static $min           = null;

    /**
     * Mínimo de caracteres para o dado.
     */
    protected static $minlength     = null;

    /**
     * Mínimo de itens para o dado.
     */
    protected static $minitens      = null;

    /**
     * Opções de resposta para o dado.
     */
    protected static $options       = [];

    /**
     * Padrão de validação do dado.
     */
    protected static $pattern       = null;

    /**
     * Dica de preenchimento do dado.
     */
    protected static $tip           = null;

    /**
     * Indica se deve remover os espaços em branco do texto.
     */
    protected static $trim          = false;

    /**
     * Tipo do dado.
     */
    protected static $type          = 'text';

    /**
     * Armazena o valor do dado.
     */
    protected $value          = null;

    /**
     * Exporta a estrutura do tipo de dado.
     */
    public static function export()
    {
        return [
            'default'   => static::$default,
            'label'     => static::$label,
            'mask'      => static::$mask,
            'max'       => static::$max,
            'maxlength' => static::$maxlength,
            'maxitens'  => static::$maxitens,
            'min'       => static::$min,
            'minlength' => static::$minlength,
            'minitens'  => static::$minitens,
            'options'   => static::$options,
            'pattern'   => static::$pattern,
            'tip'       => static::$tip,
            'trim'      => static::$trim,
            'type'      => static::$type,
        ];
    }

    /**
     * Valida o dado.
     */
    public static function validate( $value )
    {
        return true;
    }

    /**
     * Aplica as validações da estrutura do dado e a validação customizaa.
     */
    public static function test( $value )
    {
        $instance   = new static( $value );
        $validation = $instance->isValid();

        return $validation !== true
            ? $validation
            : static::validate( $value );
    }

    /**
     * Instancia o objeto do dado.
     */
    public function __construct( $value )
    {
        $this->value = $value;
    }

    /**
     * Retorna o valor do dado.
     */
    public function value()
    {
        return static::$type == 'text' && static::$trim
            ? trim( $this->value )
            : $this->value;
    }

    /**
     * Aplica as validações da estrutura do dado.
     */
    public function isValid()
    {
        $label      = static::$label;

        if ( true !== ( $validation = self::validateType( $label ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = self::validateMax( $label ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = self::validateMaxlength( $label ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = self::validateMaxitens( $label ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = self::validateMin( $label ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = self::validateMinlength( $label ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = self::validateMinitens( $label ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = self::validateOptions( $label ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = self::validatePattern( $label ) ) )
        {
            return $validation;
        }

        return true;
    }

    /**
     * Valida o tipo dado.
     */
    public function validateType( $label )
    {
        if ( is_null( $this->value ) )
        {
            return true;
        }

        $is_scalar      = is_scalar( $this->value );

        if ( !in_array( static::$type, self::$types ) )
        {
            return "O tipo de dado definido para $label é inválido.";
        }

        if ( static::$type == 'number' )
        {
            return $is_scalar && !preg_match( '/[^0-9]/', $this->value )
                ? true
                : "O valor informado para $label não é um número.";
        }

        if ( static::$type == 'float' )
        {
            return $is_scalar && !preg_match( '/\D/', $this->value )
                ? true
                : "O valor informado para $label não é um número ou decimal.";
        }

        if ( static::$type == 'enum' )
        {
            return $is_scalar
                ? true
                : "O tipo de valor informado para $label é inválido.";
        }

        if ( static::$type == 'set' )
        {
            return is_array( $this->value )
                ? true
                : "O valor informado para $label não é um conjunto de valores.";
        }

        return true;
    }

    /**
     * Valida o valor máximo do dado.
     */
    public function validateMax( $label )
    {
        $max = static::$max;

        return $this->value <= $max
            ? true
            : "O valor informado para $label é superior a $max.";
    }

    /**
     * Valida o máximo de caracteres do dado.
     */
    public function validateMaxlength( $label )
    {
        $maxlength  = static::$maxlength;
        $length     = strlen( $this->value );

        return $length <= $maxlength
            ? true
            : "$label deve ter até $maxlength caracteres - o valor informado tem $length caracteres.";
    }

    /**
     * Valida o máximo de itens do dado.
     */
    public function validateMaxitens( $label )
    {
        $maxitens   = static::$maxitens;
        $itens      = count( $this->value );

        return $itens <= $maxitens
            ? true
            : "$label deve ter até $maxitens itens - foram informados $itens itens.";
    }

    /**
     * Valida o valor mínimo do dado.
     */
    public function validateMin( $label )
    {
        $min = static::$min;

        return $this->value <= $min
            ? true
            : "O valor informado para $label é inferior a $min.";
    }

    /**
     * Valida o mínimo de caracteres do dado.
     */
    public function validateMinlength( $label )
    {
        $minlength  = static::$minlength;
        $length     = strlen( $this->value );

        return $length <= $minlength
            ? true
            : "$label deve ter no míninmo $minlength caracteres - o valor informado tem $length caracteres.";
    }

    /**
     * Valida o mínimo de itens do dado.
     */
    public function validateMinitens( $label )
    {
        $minitens   = static::$minitens;
        $itens      = count( $this->value );

        return $itens <= $minitens
            ? true
            : "$label deve ter no mínimo $minitens itens - foram informados $itens itens.";
    }

    /**
     * Valida o(s) valor(es) informado(s) com a lista de valores permitidas.
     */
    public function validateOptions( $label )
    {
        if ( !in_array( static::$type, ['enum', 'set'] ) )
        {
            return true;
        }

        if ( static::$type == 'enum' )
        {
            return in_array( $this->value, static::$options )
                ? true
                : "O valor informado para $label não consta na lista de valores permitidos.";
        }

        foreach ( $this->value as $value )
        {
            if ( !in_array( $value, static::$options ) )
            {
                return "Foi informado para $label um valor que não consta na lista de valores permitidos.";
            }
        }

        return true;
    }

    /**
     * Valida o formato do dado.
     */
    public function validatePattern( $label )
    {
        return preg_match( '/' . static::$pattern . '/', $this->value )
            ? true
            : "O valor informado para $label é inválido.";
    }
}
