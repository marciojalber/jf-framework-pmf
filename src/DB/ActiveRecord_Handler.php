<?php

namespace JF\DB;

use JF\Config;
use JF\Exceptions\ErrorException as Error;

/**
 * Classe que representa um registro no banco-de-dados.
 */
trait ActiveRecord_Handler
{
    /**
     * Retorna os valores do registro em um array.
     */
    public function values( $unsafe = false )
    {
        // Modo desprotegido retorna até as colunas ocultas
        $alt_values     = $this->alt_values;

        foreach ( $alt_values as &$value )
        {
            if ( $value instanceof Collection )
            {
                $value  = $value->values();
            }
        }
        
        if ( $unsafe )
        {
            return (object) array_merge( $this->values, $alt_values );
        }

        $values = $this->values;
        $show   = static::$show;
        $hide   = static::$hide;

        // Verifica quais propriedades podem ser exibidas
        if ( $show )
        {
            foreach ( $values as $prop => $val )
            {
                if ( !in_array( $prop, $show ) )
                {
                    unset( $values[ $prop ] );
                }
            }

            return $values;
        }

        // Verifica quais propriedades ficarão ocultas
        if ( $hide )
        {
            foreach ( $values as $prop => $val )
            {
                if ( in_array( $prop, $hide ) )
                {
                    unset( $values[ $prop ] );
                }
            }
        }
        
        // Retorna os valores
        return (object) array_merge( $values, $alt_values );
    }

    /**
     * Informa se uma determinada propriedade existe no objeto.
     * Atenção: propriedades não previstas em $props
     * podem ter sido informadas para o objeto.
     */
    public function has( $prop )
    {
        $has_in_values      = array_key_exists( $prop, $this->values );
        $has_in_alt_values  = array_key_exists( $prop, $this->alt_values );
        
        return $has_in_values || $has_in_alt_values;
    }

    /**
     * Retorna o valor de uma coluna do registro.
     */
    public function get( $column, $get_values = false, $index_by = null )
    {
        if ( array_key_exists( $column, $this->values ) )
        {
            return $this->values[ $column ];
        }

        if ( array_key_exists( $column, $this->alt_values ) )
        {
            return $this->alt_values[ $column ];
        }
        
        $props  = static::props();
        $unsafe = true;

        // Tenta capturar os relacionamentos has-one
        foreach ( $props as $key => $prop )
        {
            $type = !empty( $prop[ 'type' ] )
                ? $prop[ 'type' ]
                : null;
            
            if ( $type !== 'reference' || $prop[ 'relation' ] !== $column )
            {
                continue;
            }

            $result = $this->getRelatedParent( $key, $prop );
            $this->set( $column, $result, $unsafe );
            
            return $result && $get_values
                ? $result->values()
                : $result;
        }

        // Tenta capturar os relacionamentos has-many
        $relations  = static::relations();

        if ( !isset( $relations->$column ) )
        {
            $classe = get_called_class();
            $msg    = "Propriedade '$column' não encontrada na instância da classe '{$classe}'!";
            throw new Error( $msg );
        }

        $target     = $relations->$column;
        $result     = $this->relatedMany( $column, $target, $index_by );
        
        // Se for para persistir o valor dentro dos valures do registro
        $this->set( $column, $result, $unsafe );

        // Retorna o valor solicitado
        return $result && $get_values
            ? $result->values()
            : $result;
    }

    /**
     * Tenta capturar os relacionamentos has-one.
     */
    protected function getRelatedParent( $key, $prop )
    {
        if ( isset( $prop[ 'model' ] ) )
        {
            $model          = str_replace( '.', '\\', $prop[ 'model' ] );
            $target         = self::PREFIX . $model . self::SUFIX;
        }
        else
        {
            $this_schema    = static::schemaName();
            $target         = ucfirst( str_replace( '.', '\\', $prop[ 'source' ] ) );
            
            if ( strpos( $target, '\\' ) < 1 )
            {
                $target     = ucfirst( $this_schema ) . '\\' . $target;
            }

            $target         = self::PREFIX . $target . self::SUFIX;
        }

        $result         = $target::prepare()
            ->select( $target::columns( true ) )
            ->is( $target::primaryKey(), $this->get( $key ) )
            ->one();

        return $result;
    }

    /**
     * Recupera o valor de uma propriedade.
     */
    public function __get( $prop )
    {
        if ( array_key_exists( $prop, $this->values ) )
        {
            return $this->values[ $prop ];
        }

        if ( array_key_exists( $prop, $this->alt_values ) )
        {
            return $this->alt_values[ $prop ];
        }
    }

    /**
     * Define o valor para uma ou várias propriedades.
     */
    public function set( $prop, $val = null, $unsafe = false )
    {
        // Define uma variável local para as propriedades do registro
        $props      = static::$props;

        // Transforma o valor unitário de uma propriedade em um array
        $multi_prop = is_array( $prop )
            ? $prop
            : array( $prop => $val );

        // Itera com todas as propriedades e seus valores
        foreach ( $multi_prop as $prop => $val )
        {
            if ( !$unsafe )
            {
                self::applyFilters( $props, $prop, $val );
            }

            // Se a chave não existe nas propriedades do ActiveRecord.
            // Ou seja, valor decorrente de relacionamento ou inserção insegura
            if ( !array_key_exists( $prop, $props ) )
            {
                $this->alt_values[ $prop ] = $val;
                continue;
            }

            $no_created         = $this->status !== 'created';
            $modified           = $this->values[ $prop ] !== $val;
            $has_saved_value    = array_key_exists( $prop, $this->saved_values );
            
            if ( $no_created && $modified && $has_saved_value)
            {
                if ( $this->saved_values[ $prop ] === $val )
                {
                    unset( $this->saved_values[ $prop ] );
                }
            }

            if ( $no_created && $modified && !$has_saved_value)
            {
                $this->saved_values[ $prop ] = $this->values[ $prop ];
            }

            // Se a propriedade tem função de encriptação declarada
            if ( isset( $props[ $prop ][ 'encrypt' ] ) )
            {
                $val = self::applyEncripty( $val, $props[ $prop ][ 'encrypt' ] );
            }
            
            // Define o valor para a propriedade
            $this->values[ $prop ] = $val;
        }

        return $this;
    }

    /**
     * Checa se o valor respeita o valor mínimo permitido.
     */
    private function applyFilters( $props, $prop, &$val )
    {
        $class  = get_called_class();

        // Se tentou informar valor para uma propriedade não declarada
        if ( !array_key_exists( $prop, $props ) )
        {
            $text   = "propriedade '$prop' desconhecida - $class.";
            throw new Error( $text );
        }

        // Se é requerido um valor para a propriedade e o valor está em branco
        $required   = !empty( $props[ $prop ][ 'required' ] );
        $no_value   = $val === null || $val === '';

        if ( $required && $no_value )
        {
            $text   = "requerido um valor para '$prop' - $class";
            throw new Error( $text );
        }

        if ( $no_value )
        {
            return;
        }

        $numberval  = floatval( $val );

        // Se foi informado valor menor que o permitido
        if ( isset( $props[ $prop ][ 'min' ] ) )
        {
            $val    = $numberval;
            self::validateMinval( $val, $props[ $prop ][ 'min' ] );
        }
        
        // Se foi informado valor maior que o permitido
        if ( isset( $props[ $prop ][ 'max' ] ) )
        {
            $val    = $numberval;
            self::validateMaxval( $val, $props[ $prop ][ 'max' ] );
        }

        $lenval         = strlen( $val );

        // Se foi informado valor com número de caracteres menor que o permitido
        if ( isset( $props[ $prop ][ 'minlength' ] ) )
        {
            self::validateMinlength( $lenval, $props[ $prop ][ 'minlength' ] );
        }

        // Se foi informado valor com número de caracteres maior que o permitido
        if ( isset( $props[ $prop ][ 'maxlength' ] ) )
        {
            self::validateMaxlength( $lenval, $props[ $prop ][ 'maxlength' ] );
        }
        
        // Se foi declarado um tipo de dado para a propriedade
        if ( empty( $props[ $prop ][ 'type' ] ) )
        {
            return;
        }
        
        if ( $val === null || $val === '' && empty( $props[ 'required' ] ) )
        {
            return;
        }
        
        if ( !$required && !$val )
        {
            return;
        }

        self::validateType( $val, $prop, $props[ $prop ] );
    }

    /**
     * Checa se o valor respeita o valor mínimo permitido.
     */
    private function validateMinval( $val, $min )
    {
        if ( $val < $min )
        {
            $class  = get_called_class();
            $text   = "valor ($val) menor que o permitido ($minlength) - $class";
            throw new Error( $text );
        }
    }

    /**
     * Checa se o valor respeita o valor máximo permitido.
     */
    private function validateMaxval( $val, $min )
    {
        if ( $val > $min )
        {
            $class  = get_called_class();
            $text   = "valor ($val) maior que o permitido ($minlength) - $class";
            throw new Error( $text );
        }
    }

    /**
     * Checa se o respeita o limite mínimo de caracteres.
     */
    private function validateMinlength( $lenval, $minlength )
    {
        $class  = get_called_class();

        if ( $minlength < 0 )
        {
            $text   = "Quantidade máxima de caracteres menor do que zero ($minlength) - $class";
            throw new Error( $text );
        }

        if ( $lenval < $minlength )
        {
            $text   = "Quantidade de caracteres ($lenval) menor que o permitido ($minlength) - $class";
            throw new Error( $text );
        }
    }

    /**
     * Checa se o valor respeita o limite máximo de caracteres.
     * 
     * @return null
     */
    private function validateMaxlength( $lenval, $maxlength )
    {
        $class  = get_called_class();

        if ( $maxlength < 0 )
        {
            $text   = "Quantidade máxima de caracteres menor do que zero ($maxlength) - $class";
            throw new Error( $text );
        }

        if ( $lenval > $maxlength )
        {
            $text   = "Quantidade de caracteres ($lenval) maior que o permitido ($maxlength) - $class";
            throw new Error( $text );
        }
    }

    /**
     * Checa se o valor respeita o limite máximo de caracteres.
     * 
     * @return null
     */
    private function applyEncripty( $val, $encprity )
    {
        if ( is_string( $encrypt ) )
        {
            $encrypt_class  = get_called_class();
            $encrypt_method = $encrypt;
        }
        else {
            $encrypt_class  = $encrypt[0];
            $encrypt_method = $encrypt[1];
        }

        return $encrypt_class::$encrypt_method( $val );
    }

    /**
     * Checa se o valor corresponde ao tipo indicado.
     * 
     * @return null
     */
    private function validateType( $val, $prop, $props )
    {
        // string|number|list|date|datetime|time|money
        $type           = isset( $props[ 'type' ] )
            ? $props[ 'type' ]
            : null;
        $list_types     = ['enum', 'set'];
        $number_types   = ['number', 'money', 'integer'];
        $year           = substr( $val, 0, 4 );
        $month          = substr( $val, 5, 2 );
        $day            = substr( $val, 8, 2 );
        $label          = isset( $props[ 'label' ] )
            ? $props[ 'label' ]
            : $prop;

        if ( in_array( $type, $list_types ) )
        {
            return $this->validateTypeList( $val, $label, $props );
        }

        if ( in_array( $type, $number_types ) )
        {
            return $this->validateTypeNumber( $val, $label, $type );
        }
        
        if ( $type === 'time' )
        {
            return $this->validateTypeTime( $val, $label );
        }

        if ( $type === 'date' )
        {
            return $this->validateTypeDate( $val, $label, $day, $month, $year );
        }

        if ( $type === 'datetime' )
        {
            return $this->validateTypeDateTime( $val, $label, $day, $month, $year );
        }
    }

    /**
     * Define o valor de uma propriedade.
     */
    private function validateTypeList( $val, $label, $props )
    {
        $class      = get_called_class();
        $options    = is_string( $props[ 'options' ] )
            ? (array) Config::get( 'options.' . $props[ 'options' ] )
            : $props[ 'options' ];

        if ( !$options )
        {
            $text   = "valores permitidos não declarados para '{$label}'!";
            throw new Error( $text );
        }

        $type_val   = gettype( $val );
        $type_valid = in_array( $type_val, array( 'string', 'integer' ) );
        
        if ( !$type_valid || !array_key_exists( $val, $options ) )
        {
            $text   = isset( $props[ 'invalid_text' ] )
                ? $props[ 'invalid_text' ]
                : "valor '{$val}' não consta na lista de valores permitidos " .
                  "para '{$label}' - $class";
            throw new Error( $text );
        }
    }

    /**
     * Define o valor de uma propriedade.
     */
    private function validateTypeNumber( $val, $label, $type )
    {
        $class          = get_called_class();
        
        $has_only_digit = (
            filter_var( $val, FILTER_VALIDATE_FLOAT ) !== false ||
            filter_var( $val, FILTER_VALIDATE_INT ) !== false
        );
        $float          = (float) $val;
        $number         = ceil( $float );

        // Tipo número
        if ( $type === 'number' )
        {
            if ( !$has_only_digit )
            {
                $text   = "valor informado não é um número válido para '{$label}' - $class";
                throw new Error( $text );
            }
            return;
        }

        // Tipo valor monetário
        if ( $type === 'money' )
        {
            $parts      = explode( '.', $float );
            $decimal    = isset( $parts[ 1 ] )
                ? $parts[ 1 ]
                : null;
            
            if ( !$has_only_digit || $decimal && strlen( $decimal ) > 2 )
            {
                $text   = 
                    "valor informado não é um valor monetário válido para '{$label}' - $class";
                throw new Error( $text );
            }
            return;
        }

        // Tipo inteiro
        if ( $type === 'integer' )
        {
            if ( $val != $float || $float !== $number )
            {
                $text   =
                    "valor informado não é um número inteiro válido para '{$label}' - $class";
                throw new Error( $text );
            }
            return;
        }
    }

    /**
     * Define o valor de uma propriedade.
     */
    private function validateTypeTime( $val, $label )
    {
        $class          = get_called_class();
        $hour_time      = substr( $val, 0, 2 );
        $mins_time      = substr( $val, 3, 2 );
        $segs_time      = substr( $val, 6, 2 );
        $is_time        = (
            preg_match( '/^\d{2}:\d{2}:\d{2}(.\d{1,6})?$/', $val ) === 1 &&
            $hour_time >= 0 && $hour_time <= 23 &&
            $mins_time >= 0 && $mins_time <= 59 &&
            $segs_time >= 0 && $segs_time <= 59
        );

        if ( !$is_time )
        {
            $text   = "valor informado não é um horário válida para '{$label}' - $class";
            throw new Error( $text );
        }
        return;
    }

    /**
     * Define o valor de uma propriedade.
     */
    private function validateTypeDate( $val, $label, $day, $month, $year )
    {
        $class          = get_called_class();
        $is_date        = (
            preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) === 1 &&
            checkdate( $month, $day, $year )
        );

        if ( !$is_date )
        {
            $text   = "valor informado não é uma data válida para '{$label}' - $class";
            throw new Error( $text );
        }

        return;
    }

    /**
     * Define o valor de uma propriedade.
     */
    private function validateTypeDateTime( $val, $label, $day, $month, $year )
    {
        $class          = get_called_class();

        // Tipo data e hora
        $hour_datetime  = substr( $val, 11, 2 );
        $mins_datetime  = substr( $val, 14, 2 );
        $segs_datetime  = substr( $val, 17, 2 );
        $is_datetime    = (
            preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(.\d{1,6})?$/', $val ) === 1 &&
            checkdate( $month, $day, $year )            &&
            $hour_datetime >= 0 && $hour_datetime <= 23 &&
            $mins_datetime >= 0 && $mins_datetime <= 59 &&
            $segs_datetime >= 0 && $segs_datetime <= 59
        );
        
        if ( !$is_datetime )
        {
            $text   = "valor informado não é uma data e hora válida para '{$label}' - $class";
            throw new Error( $text );
        }
    }

    /**
     * Define o valor de uma propriedade.
     */
    public function __set( $prop, $val )
    {
        return $this->set( $prop, $val );
    }

    /**
     * Retorna os valores do registro em um array.
     */
    public function modified()
    {
        return (bool) $this->saved_values;
    }

    /**
     * Retorna os valores do registro em um array.
     */
    public function originalValues()
    {
        return $this->saved_values;
    }

    /**
     * Restaura os valores originais do registro.
     */
    public function restore()
    {
        // Um registro recém-criado não tem valores de origem
        if ( $this->status === 'created' )
        {
            throw new Error( 'O registro ainda não foi salvo!' );
        }

        // Restaura os valores de origem
        foreach ( $this->saved_values as $prop => $val )
        {
            $this->values[ $prop ] = $val;
        }

        $this->saved_values = [];
    }

    /**
     * Retorna informações do ActiveRecord.
     */
    public function __debugInfo()
    {
        $table_name = static::tableName();
        $props      = static::$props;
        $show       = static::$show;
        $hide       = static::$hide;
        $values     = $this->values;
        $info       = array();

        // Prepara a exibição dos dados
        foreach ( $values as $prop_name => $val )
        {
            $private  = '';
            $prop_def = $props[ $prop_name ];
            $required = null;

            if ( $show )
            {
                if ( !in_array( $prop_name, $show ) )
                {
                    $private = '*';
                }
            }
            elseif ( $hide && in_array( $prop_name, $hide ) )
            {
                $private = '*';
            }
            
            if ( !empty( $prop_def[ 'required' ] ) )
            {
                $required = ' REQUIRED';
            }
            
            $type = !empty( $prop_def[ 'type' ] )
                ? $prop_def[ 'type' ]
                : gettype( $val );
            $value = ( $values[ $prop_name ] === null || $values[ $prop_name ] === '' ) && $required
                ? '(EMPTY)'
                : $values[ $prop_name ];
            $info[ "{$private}{$prop_name}:{$type}{$required}" ] = $value;
        }

        // Retorna os dados
        return [
            'table'         => $table_name,
            'status'        => $this->status . ( $this->saved_values ? ' (editing)' : '' ),
            'saved_values'  => $this->saved_values,
            'values'        => $info,
            'alt_values'    => $this->alt_values,
        ];
    }
}
