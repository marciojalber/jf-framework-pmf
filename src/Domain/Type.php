<?php

namespace JF\Domain;

/**
 * Modelo de tipo de dado.
 */
class Type
{
    use ValidationTrait;

    /**
     * Quantidade de casas decimais.
     */
    protected static $decimals  = null;

    /**
     * Valor padrão do dado.
     */
    protected static $default   = null;

    /**
     * Rótulo do dado.
     */
    protected static $label     = null;

    /**
     * Máscara do dado.
     */
    protected static $mask      = null;

    /**
     * Valor máximo para o dado.
     */
    protected static $max       = null;

    /**
     * Máximo de itens para o dado.
     */
    protected static $maxitens  = null;

    /**
     * Máximo de caracteres para o dado.
     */
    protected static $maxlength = null;

    /**
     * Valor mínimo para o dado.
     */
    protected static $min       = null;

    /**
     * Mínimo de itens para o dado.
     */
    protected static $minitens  = null;

    /**
     * Mínimo de caracteres para o dado.
     */
    protected static $minlength = null;

    /**
     * Opções de resposta para o dado.
     */
    protected static $options   = [];

    /**
     * Padrão de validação do dado.
     */
    protected static $pattern   = null;

    /**
     * Requerido informar um valor para o dado.
     */
    protected static $required  = false;

    /**
     * Dica de preenchimento do dado.
     */
    protected static $tip       = null;

    /**
     * Indica se deve remover os espaços em branco do texto.
     */
    protected static $trim      = false;

    /**
     * Tipo do dado.
     */
    protected static $type      = null;

    /**
     * Armazena o valor do dado.
     */
    protected $value            = null;

    /**
     * Cria uma instância da classe.
     */
    public static function instance( $value = null )
    {
        return new static( $value );;
    }

    /**
     * Exporta a estrutura do tipo de dado.
     */
    public static function export()
    {
        return (object) [
            'decimals'  => static::$decimals,
            'default'   => static::$default,
            'label'     => static::$label,
            'mask'      => static::$mask,
            'max'       => static::$max,
            'maxitens'  => static::$maxitens,
            'maxlength' => static::$maxlength,
            'min'       => static::$min,
            'minitens'  => static::$minitens,
            'minlength' => static::$minlength,
            'options'   => static::$options,
            'pattern'   => static::$pattern,
            'required'  => static::$required,
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
    public function __construct( $value = null )
    {
        $this->value = $value;
    }

    /**
     * Retorna o valor do dado.
     */
    public function value()
    {
        return in_array( static::$type, ['string', 'text'] ) && static::$trim
            ? trim( $this->value )
            : $this->value;
    }

    /**
     * Aplica as validações da estrutura do dado.
     */
    public function isValid()
    {
        $prop       = static::export();
        $value      = $this->value;

        if ( true !== ( $validation = $this->applyValidation( $value, $prop ) ) )
        {
            return $validation;
        }

        return true;
    }
}
