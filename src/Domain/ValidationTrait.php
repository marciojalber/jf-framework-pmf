<?php

namespace JF\Domain;

/**
 * Modelo de tipo de dado.
 */
trait ValidationTrait
{
    /**
     * Valor padrão do dado.
     */
    private static $types           = [
        'date',
        'datetime',
        'email',
        'enum',
        'float',
        'ipv4',
        'number',
        'password',
        'range',
        'rate',
        'set',
        'string',
        'switch',
        'text',
        'tel',
        'time',
        'url',
    ];

    /**
     * Aplica as validações da estrutura do dado.
     */
    protected function applyValidation( $value, $prop )
    {
        if ( true !== ( $validation = $this->validateType( $value, $prop ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = $this->validateRequired( $value, $prop ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = $this->validateMax( $value, $prop ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = $this->validateMaxlength( $value, $prop ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = $this->validateMaxitens( $value, $prop ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = $this->validateMin( $value, $prop ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = $this->validateMinlength( $value, $prop ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = $this->validateMinitens( $value, $prop ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = $this->validateOptions( $value, $prop ) ) )
        {
            return $validation;
        }

        if ( true !== ( $validation = $this->validatePattern( $value, $prop ) ) )
        {
            return $validation;
        }

        return true;
    }

    /**
     * Valida o tipo dado.
     */
    protected function validateType( $value, $prop )
    {
        if ( is_null( $value ) )
        {
            return true;
        }

        $is_scalar      = is_scalar( $value );
        $filter_ip      = FILTER_VALIDATE_IP;

        if ( !in_array( $prop->type, self::$types ) )
        {
            return "O tipo de dado definido para {$prop->label} é inválido.";
        }

        if ( 'date' == $prop->type )
        {
            $date_pattern   = '/^\d{4}-\d{2}-\d{2}$/';

            return $is_scalar && preg_match( $date_pattern, $value ) && strtotime( $value )
                ? true
                : "O valor informado para {$prop->label} não é uma data válida.";
        }

        if ( 'datetime' == $prop->type )
        {
            $dt_pattern     = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

            return $is_scalar && preg_match( $dt_pattern, $value ) && strtotime( $value )
                ? true
                : "O valor informado para {$prop->label} não é uma 'data e hora' válida.";
        }

        if ( 'email' == $prop->type )
        {
            return $is_scalar && filter_var( $value, FILTER_VALIDATE_EMAIL )
                ? true
                : "O valor informado para {$prop->label} não é um e-mail válido.";
        }

        if ( 'float' == $prop->type )
        {
            return $is_scalar && !preg_match( '/[^\d.]/', $value )
                ? true
                : "O valor informado para {$prop->label} não é um número ou decimal válido.";
        }

        if ( 'ip' == $prop->type )
        {
            return $is_scalar && filter_var( $value, FILTER_VALIDATE_IP )
                ? true
                : "O valor informado para {$prop->label} não é um IP válido.";
        }

        if ( 'ipv4' == $prop->type )
        {
            return $is_scalar && filter_var( $value, $filter_ip, FILTER_FLAG_IPV4 )
                ? true
                : "O valor informado para {$prop->label} não é um IP no formato IPV4 válido.";
        }

        if ( 'ipv6' == $prop->type )
        {
            return $is_scalar && filter_var( $value, $filter_ip, FILTER_FLAG_IPV6 )
                ? true
                : "O valor informado para {$prop->label} não é um IP no formato IPV6 válido.";
        }

        if ( 'number' == $prop->type )
        {
            return $is_scalar && !preg_match( '/[^0-9]/', $value )
                ? true
                : "O valor informado para {$prop->label} não é um número válido.";
        }

        if ( 'set' == $prop->type )
        {
            return is_array( $value )
                ? true
                : "O valor informado para {$prop->label} não é um conjunto de valores.";
        }

        if ( in_array( $prop->type, ['enum', 'rate', 'switch'] ) )
        {
            return $is_scalar
                ? true
                : "O tipo de valor informado para {$prop->label} é inválido.";
        }

        if ( 'range' == $prop->type )
        {
            if ( !$is_scalar || preg_match( '/[^0-9]/', $value ) )
            {
                return "O valor informado para {$prop->label} não é um número.";
            }

            if ( is_null( $prop->min ) || is_null( $prop->max ) )
            {
                return "Valor mínimo/máximo não definido para {$prop->label}.";
            }

            if ( $prop->min > $prop->max )
            {
                return "{$prop->label} definido com valor mínimo ($prop->min) maior que o valor máximo ($prop->max).";
            }

            return true;
        }

        if ( 'time' == $prop->type )
        {
            $time_pattern   = '/^\d{2}:\d{2}:\d{2}$/';

            return $is_scalar && preg_match( $time_pattern, $value ) && strtotime( $value )
                ? true
                : "O valor informado para {$prop->label} não é um horário válido.";
        }

        if ( 'url' == $prop->type )
        {
                return $is_scalar && filter_var( $value, FILTER_VALIDATE_URL )
                ? true
                : "O valor informado para {$prop->label} não é uma URL válida.";
        }

        // string, password, text
        if ( $is_scalar )
        {
            return true;
        }

        if ( in_array( $prop->type, ['string', 'text'] ) )
        {
            return "O valor informado para {$prop->label} não é um texto válido.";
        }

        if ( 'password' == $prop->type )
        {
            return "O valor informado para {$prop->label} não é uma senha válida.";
        }
    }

    /**
     * Valida se, sendo um valor requerido, foi informado.
     */
    protected function validateRequired( $value, $prop )
    {
        return empty( $prop->required ) || ( !is_null( $value ) && $value !== '' )
            ? true
            : "É obrigatório informar um valor para $prop->label.";
    }

    /**
     * Valida o valor máximo do dado.
     */
    protected function validateMax( $value, $prop )
    {
        $max = isset( $prop->max )
            ? $prop->max
            : null;

        if ( $max === null )
        {
            return true;
        }

        if ( $value <= $max )
        {
            return true;
        }

        return $value <= $max
            ? true
            : "O valor informado para {$prop->label} é superior a $max.";
    }

    /**
     * Valida o máximo de caracteres do dado.
     */
    protected function validateMaxlength( $value, $prop )
    {
        $maxlength  = isset( $prop->maxlength )
            ? $prop->maxlength
            : null;

        if ( $maxlength === null )
        {
            return true;
        }

        $length     = mb_strlen( $value );

        $txt_max    = $maxlength == 1
            ? $prop->label . " deve ter até $maxlength caractere"
            : $prop->label . " deve ter até $maxlength caracteres";
        
        $txt_info   = $length == 1
            ? "o valor informado tem $length caractere."
            : "o valor informado tem $length caracteres.";
        
        return $length <= $maxlength
            ? true
            : $txt_max . ' - ' . $txt_info;
    }

    /**
     * Valida o máximo de itens do dado.
     */
    protected function validateMaxitens( $value, $prop )
    {
        $maxitens   = isset( $prop->maxitens )
            ? $prop->maxitens
            : null;

        if ( $maxitens == null )
        {
            return true;
        }

        $itens      = count( $value );

        $txt_max    = $maxitens == 1
            ? "{$prop->label} deve ter até $maxitens item"
            : "{$prop->label} deve ter até $maxitens itens";
        
        $txt_info   = $itens == 1
            ? "foi informado somente $itens item."
            : "foram informados $itens itens.";
        
        return $itens <= $maxitens
            ? true
            : $txt_max . ' - ' . $txt_info;
    }

    /**
     * Valida o valor mínimo do dado.
     */
    protected function validateMin( $value, $prop )
    {
        $min = isset( $prop->min )
            ? $prop->min
            : null;

        if ( $min === null )
        {
            return true;
        }

        return $value >= $min
            ? true
            : "O valor informado para {$prop->label} é inferior a $min.";
    }

    /**
     * Valida o mínimo de caracteres do dado.
     */
    protected function validateMinlength( $value, $prop )
    {
        $minlength = isset( $prop->minlength )
            ? $prop->minlength
            : null;

        if ( $minlength === null )
        {
            return true;
        }

        $length     = mb_strlen( $value );

        $txt_min    = $minlength == 1
            ? $prop->label . " deve ter no mínimo $minlength caractere"
            : $prop->label . " deve ter no mínimo $minlength caracteres";
        
        $txt_info   = $length == 1
            ? "o valor informado tem apenas $length caractere."
            : "o valor informado tem apenas $length caracteres.";

        return $length >= $minlength
            ? true
            : $txt_min . ' - ' . $txt_info;
    }

    /**
     * Valida o mínimo de itens do dado.
     */
    protected function validateMinitens( $value, $prop )
    {
        $minitens = isset( $prop->minitens )
            ? $prop->minitens
            : null;

        if ( $minitens === null )
        {
            return true;
        }

        $itens      = count( $value );

        $txt_min    = $minitens == 1
            ? "{$prop->label} deve ter no mínimo $minitens item"
            : "{$prop->label} deve ter no mínimo $minitens itens";

        $txt_info   = $itens == 1
            ? "foi informado apenas $itens item."
            : "foram informados apenas $itens itens.";

        return $itens >= $minitens
            ? true
            : $txt_min . ' - ' . $txt_info;
    }

    /**
     * Valida o(s) valor(es) informado(s) com a lista de valores permitidas.
     */
    protected function validateOptions( $value, $prop )
    {
        if ( !in_array( $prop->type, ['set', 'enum', 'rate', 'switch'] ) )
        {
            return true;
        }

        if ( !$prop->options )
        {
            return true;
        }

        if ( $prop->type != 'set' )
        {
            return in_array( $value, array_keys( $prop->options ) )
                ? true
                : "O valor '{$value}' informado para {$prop->label} não consta na lista de valores permitidos.";
        }

        foreach ( $value as $item )
        {
            if ( !in_array( $item, array_keys( $prop->options ) ) )
            {
                return "O valor '{$item}' informado para {$prop->label} não consta na lista de valores permitidos.";
            }
        }

        if ( $value != array_unique( $value ) )
        {
            return "Algum valor foi informado em duplicidade para {$prop->label}.";
        }

        return true;
    }

    /**
     * Valida o formato do dado.
     */
    protected function validatePattern( $value, $prop )
    {
        if ( empty( $prop->pattern ) )
        {
            return true;
        }

        return preg_match( '/' . $prop->pattern . '/', $value )
            ? true
            : "O valor informado para {$prop->label} é inválido.";
    }
}
