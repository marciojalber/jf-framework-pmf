<?php

namespace JF;

use JF\Exceptions\WarningException as Warning;

/**
 * Modelo de tipo de dado.
 */
class Type
{
    /**
     * Rótulo do dado.
     */
    protected static $label         = null;

    /**
     * Valor padrão do dado.
     */
    private static $types           = [
        'date',
        'datetime',
        'time',
        'enum',
        'set',
        'number',
        'float',
        'text',
    ];

    /**
     * Tipo do dado.
     */
    protected static $type          = 'text';

    /**
     * Valor padrão do dado.
     */
    protected static $default       = null;

    /**
     * Máscara do dado.
     */
    protected static $mask          = null;

    /**
     * Valor mínimo para o dado.
     */
    protected static $min           = null;

    /**
     * Valor máximo para o dado.
     */
    protected static $max           = null;

    /**
     * Mínimo de caracteres para o dado.
     */
    protected static $minlength     = null;

    /**
     * Máximo de caracteres para o dado.
     */
    protected static $maxlength     = null;

    /**
     * Mínimo de itens para o dado.
     */
    protected static $minitens      = null;

    /**
     * Máximo de itens para o dado.
     */
    protected static $maxitens      = null;

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
     * Exporta a estrutura do tipo de dado.
     */
    public static function export()
    {
        return [
            'type'      => static::$type,
            'default'   => static::$default,
            'label'     => static::$label,
            'mask'      => static::$mask,
            'min'       => static::$min,
            'max'       => static::$max,
            'minlength' => static::$minlength,
            'maxlength' => static::$maxlength,
            'minitens'  => static::$minitens,
            'maxitens'  => static::$maxitens,
            'options'   => static::$options,
            'pattern'   => static::$pattern,
            'tip'       => static::$tip,
            'trim'      => static::$trim,
        ];
    }

    /**
     * Instancia o objeto do dado.
     */
    public function __construct( $value )
    {
        $this->value = $value;
    }

    /**
     * Retorna o valor mascarado.
     */
    public static function mask( $val )
    {
        return $val;
    }

    /**
     * Higieniza o dado informado.
     */
    public static function sanitize( $value )
    {
        if ( static::$type == 'text' && is_string( $value ) && static::$trim )
            return trim( $value );
        
        if ( static::$type == 'number' && is_numeric( $value ) )
            return intval( $value );
        
        if ( static::$type == 'float' && is_float( $value ) )
            return floatval( $value );
        
        return $value;
    }

    /**
     * Encontra a diferença do dado informado e o tipo de dado.
     */
    public static function diff( $val, $label = null )
    {
        $inst   = new static( $val );
        $label  ??= static::$label;

        if ( is_null( $val ) )
            return null;

        if ( $diff = self::diffType( $val, $label ) )
            return $diff;

        if ( $diff = self::diffMax( $val, $label ) )
            return $diff;

        if ( $diff = self::diffMaxlength( $val, $label ) )
            return $diff;

        if ( $diff = self::diffMaxitens( $val, $label ) )
            return $diff;

        if ( $diff = self::diffMin( $val, $label ) )
            return $diff;

        if ( $diff = self::diffMinlength( $val, $label ) )
            return $diff;

        if ( $diff = self::diffMinitens( $val, $label ) )
            return $diff;

        if ( $diff = self::diffOptions( $val, $label ) )
            return $diff;

        if ( $diff = self::diffPattern( $val, $label ) )
            return $diff;

        return null;
    }

    /**
     * Valida o tipo dado.
     */
    public static function diffType( $val, $label )
    {
        $scalar         = is_scalar( $val );
        $type           = static::$type;
        $date_pattern   = '/^\d{4}-\d{2}-\d{2}$/';
        $dt_pattern     = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

        if ( !in_array( $type, self::$types ) )
            return "O tipo de dado informado para o validador de [$label] é inválido.";

        if ( $type == 'date' )
        {
            if ( !$scalar )
                return "O valor informado para [$label] não é uma data válida.";

            if ( !preg_match( $date_pattern, $val ) )
                return "O valor informado para [$label] não é uma data válida.";

            $year   = substr( $val, 0, 4 );
            $month  = substr( $val, 5, 2 );
            $date   = substr( $val, -2 );
            
            if ( !checkdate( $month, $date, $year ) )
                return "O valor informado para [$label] não é uma data válida.";
        }

        if ( $type == 'datetime' )
        {
            if ( !$scalar )
                return "O valor informado para [$label] não é uma data e hora válida.";

            if ( !preg_match( $dt_pattern, $val ) )
                return "O valor informado para [$label] não é uma data e hora válida.";

            $year   = substr( $val, 0, 4 );
            $month  = substr( $val, 5, 2 );
            $date   = substr( $val, 8, 2 );
            $hour   = substr( $val, 11, 2 );
            $min    = substr( $val, 14, 2 );
            $seg    = substr( $val, -2 );
            
            if ( !checkdate( $month, $date, $year ) )
                return "O valor informado para [$label] não é uma data e hora válida.";
            
            if ( $hour < 0 || $hour > 23 )
                return "O valor informado para [$label] não é uma data e hora válida.";
            
            if ( $min < 0 || $min > 59 )
                return "O valor informado para [$label] não é uma data e hora válida.";
            
            if ( $seg < 0 || $seg > 59 )
                return "O valor informado para [$label] não é uma data e hora válida.";
        }

        if ( $type == 'number' && !( $scalar && !preg_match( '/[^0-9]/', $val ) ) )
            return "O valor informado para [$label] não é um número.";

        if ( $type == 'float' && !( $scalar && !preg_match( '/\D/', $val ) ) )
            return "O valor informado para $label não é um número ou decimal.";

        if ( $type == 'enum' && !$scalar )
            return "O tipo de valor informado para $label é inválido.";

        if ( $type == 'set' && !is_array( $val ) )
            return "O valor informado para $label não é um conjunto de valores.";

        return true;
    }

    /**
     * Valida o valor mínimo do dado.
     */
    public static function diffMin( $val, $label )
    {
        $min = static::$min;

        if ( $val < static::$min )
           return "O valor informado para $label é inferior a $min.";
    }

    /**
     * Valida o valor máximo do dado.
     */
    public static function diffMax( $val, $label )
    {
        $max = static::$max;

        if ( $val > static::$max )
           return "O valor informado para $label é superior a $max.";
    }

    /**
     * Valida o mínimo de caracteres do dado.
     */
    public static function diffMinlength( $val, $label )
    {
        $minlength  = static::$minlength;

        if ( !isset( $val[ $minlength ] ) )
            return "$label deve ter no míninmo $minlength caracteres.";
    }

    /**
     * Valida o máximo de caracteres do dado.
     */
    public static function diffMaxlength( $val, $label )
    {
        $maxlength  = static::$maxlength;

        if ( isset( $val[ $maxlength ] ) )
            return "$label deve ter no máximo $minlength caracteres.";
    }

    /**
     * Valida o mínimo de itens do dado.
     */
    public static function diffMinitens( $val, $label )
    {
        $minitens   = static::$minitens;
        $tot_itens  = count( $val );

        if ( $tot_itens < $minitens )
            return "$label deve ter no mínimo $minitens itens.";
    }

    /**
     * Valida o máximo de itens do dado.
     */
    public static function diffMaxitens( $val, $label )
    {
        $maxitens   = static::$maxitens;
        $tot_itens  = count( $val );

        if ( $tot_itens > $maxitens )
            return "$label deve ter até $maxitens itens.";
    }

    /**
     * Valida os valores informados com a lista de valores permitidas.
     */
    public static function diffOptions( $val, $label )
    {
        if ( !in_array( static::$type, ['enum', 'set'] ) )
            return null;

        if ( static::$type == 'enum' && !in_array( $val, static::$options ) )
            return "O valor informado para $label é inválido.";

        if ( static::$type != 'set' )
            return null;

        foreach ( $val as $item )
            if ( !in_array( $item, static::$options ) )
                return "Foi informado para $label um valor inválido.";
    }

    /**
     * Valida o formato do dado.
     */
    public static function diffPattern( $val, $label )
    {
        if ( !preg_match( static::$pattern, $val ) )
            return "O valor informado para $label é inválido.";
    }
}
