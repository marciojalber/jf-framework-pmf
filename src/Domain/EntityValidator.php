<?php

namespace JF\Domain;

use JF\Config;
use JF\Reflection\DocBlockParser;
use JF\Exceptions\ErrorException;
use JF\Exceptions\WarningException;

/**
 * Habilita a entidade a realizar validações
 */
trait EntityValidator
{
    /**
     * Armazena a reflexão das propriedades.
     */
    protected static $allPropsParsed = false;

    /**
     * Armazena a reflexão das propriedades.
     */
    protected static $propsReflection = [];

    /**
     * Armazena a reflexão das propriedades.
     */
    protected static $htmlProps = [
        'identifier',
        'label',
        'max',
        'maxlength',
        'min',
        'minlength',
        'option',
        'placeholder',
        'readonly',
        'required',
        'type',
        'hidden',
    ];

    /**
     * Retorna a propriedade type do campo.
     */
    protected function getPropType( $field )
    {
        $props  = static::$propsReflection[ $field ];
        $type   =  isset( $props->tags[ 'type' ] )
            ? $props->tags[ 'type' ]
            : null;
        return $type;
    }

    /**
     * Retorna o valor convertido de acordo com a propriedade type.
     */
    protected function getValueAccordingPropType( $type, $value )
    {
        if ( $type == 'date' )
        {
            return ( new \DateTime( $value ) )->format( 'Y-m-d' );
        }

        if ( $type == 'datetime' )
        {
            return ( new \DateTime( $value ) )->format( 'Y-m-d H:i:s' );
        }

        if ( $type == 'time' )
        {
            return ( new \DateTime( $value ) )->format( 'H:i:s' );
        }

        return $value;
    }

    /**
     * Formata um valor para apresentação de acordo com a propriedade type.
     */
    protected function formatValueAccordingPropType( $type, $value )
    {
        $format_date        = Config::get( 'validations.format.date' );

        if ( $type == 'date' && $format_date )
        {
            return ( new \DateTime( $value ) )->format( $format_date );
        }
        
        $format_datetime    = Config::get( 'validations.format.datetime' );
        
        if ( $type == 'datetime' && $format_datetime )
        {
            return ( new \DateTime( $value ) )->format( $format_datetime );
        }

        $format_time        = Config::get( 'validations.format.time' );

        if ( $type == 'time' && $format_datetime )
        {
            return ( new \DateTime( $value ) )->format( $format_time );
        }

        return $value;
    }

    /**
     * Valida o valor de um bloco de propriedades.
     */
    protected function validateProps( $fields, $referer )
    {
        foreach ( $fields as $field )
        {
            static::getPropReflection( $field );
            
            if ( ENV_DEV )
            {
                self::applyTests( $field, $referer );
            }

            $this->validateProp( $this->$field, $field, $referer );
        }
    }

    /**
     * Aplica os testes.
     */
    protected function applyTests( $field, $referer )
    {
        $props          = static::$propsReflection[ $field ];
        $desc           = $props->desc;
        $testsAccept    = isset( $props->tags[ 'test-accept' ] )
            ? $props->tags[ 'test-accept' ]
            : [];
        $testsFail      = isset( $props->tags[ 'test-fail' ] )
            ? $props->tags[ 'test-fail' ]
            : [];

        foreach ( $testsAccept as $test )
        {
            try
            {
                $value      = $this->getValueAccordingPropType( $test[ 0 ], $test[ 1 ] );
                $this->validateProp( $value, $field, $referer );
            }
            catch ( WarningException $e )
            {
                $msg_original   = $e->getMessage();
                $msg            =
                    "O valor '{$value}' foi indicado como aceitável nos testes do atributo {$desc} {$referer}, " .
                    "mas foi invalidado: {$msg_original}";
                throw new WarningException( $msg );
            }
        }

        foreach ( $testsFail as $test )
        {
            try
            {
                $value      = $this->getValueAccordingPropType( $test[ 0 ], $test[ 1 ] );
                $this->validateProp( $value, $field, $referer );
                $msg        =
                    "O valor '{$value}' foi indicado como INACEITÁVEL nos testes do atributo {$desc} {$referer}, " .
                    'mas foi validado.';
                throw new ErrorException( $msg );
            }
            catch ( ErrorException $e )
            {
                throw new ErrorException( $e->getMessage() );
            }
            catch ( WarningException $e ) {}
        }
    }

    /**
     * Valida o valor de uma propriedade.
     */
    protected function validateProp( $value, $field, $referer )
    {
        $this->validatePropTAGRequired( $value, $field, $referer );

        if ( $value === null || $value === '' )
        {
            return;
        }

        $this->validatePropTAGType( $value, $field, $referer );
        $this->validatePropTAGMin( $value, $field, $referer );
        $this->validatePropTAGMax( $value, $field, $referer );
        $this->validatePropTAGMinlength( $value, $field, $referer );
        $this->validatePropTAGMaxlength( $value, $field, $referer );
        $this->validatePropTAGSelectedOption( $value, $field, $referer );
    }

    /**
     * Valida se foi informado valor para o campo indicado.
     */
    protected function validatePropTAGType( $value, $field, $referer )
    {
        $props = static::$propsReflection[ $field ];

        if ( !array_key_exists( 'type', $props->tags ) )
        {
            return;
        }

        $type   = $props->tags[ 'type' ];

        if ( $type === 'date' )
        {
            $type_valid  = !preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value )
                ? false
                : (bool) strtotime( $value );

            if ( !$type_valid )
            {
                $msg_model  = Config::get( 'validations.msg.type_date' );
                $msg        = sprintf( $msg_model, $props->desc, $referer );
                throw new WarningException( $msg );
            }
        }

        if ( $type === 'datetime' )
        {
            $type_valid  = !preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value )
                ? false
                : (bool) strtotime( $value );

            if ( !$type_valid )
            {
                $msg_model  = Config::get( 'validations.msg.type_datetime' );
                $msg        = sprintf( $msg_model, $props->desc, $referer );
                throw new WarningException( $msg );
            }
        }

        if ( $type === 'email' && !filter_var( $value, FILTER_VALIDATE_EMAIL ) )
        {
            $msg_model  = Config::get( 'validations.msg.type_email' );
            $msg        = sprintf( $msg_model, $value, $props->desc, $referer );
            throw new WarningException( $msg );
        }
    }

    /**
     * Valida se foi informado valor para o campo indicado.
     */
    protected function validatePropTAGRequired( $value, $field, $referer )
    {
        $props = static::$propsReflection[ $field ];

        if ( !array_key_exists( 'required', $props->tags ) )
        {
            return;
        }

        if ( $value === null || $value === '' )
        {
            $msg_model  = Config::get( 'validations.msg.required' );
            $msg        = sprintf( $msg_model, $props->desc, $referer );
            throw new WarningException( $msg );
        }
    }

    /**
     * Valida se foi informado valor para o campo indicado.
     */
    protected function validatePropTAGMin( $value, $field, $referer )
    {
        $props  = static::$propsReflection[ $field ];
        $type   = $this->getPropType( $field );

        if ( !array_key_exists( 'min', $props->tags ) )
        {
            return;
        }

        $min    = $props->tags[ 'min' ];
        $min    = $this->getValueAccordingPropType( $type, $min );

        if ( !( $value < $min ) )
        {
            return;
        }

        $formated_min   = $this->formatValueAccordingPropType( $type, $min );
        $formated_value = $this->formatValueAccordingPropType( $type, $value );
        $msg_model      = Config::get( 'validations.msg.min' );
        $msg            = sprintf(
            $msg_model,
            $props->desc,
            $referer,
            $formated_min,
            $formated_value
        );
        throw new WarningException( $msg );
    }

    /**
     * Valida se foi informado valor para o campo indicado.
     */
    protected function validatePropTAGMax( $value, $field, $referer )
    {
        $props  = static::$propsReflection[ $field ];
        $type   = $this->getPropType( $field );

        if ( !array_key_exists( 'max', $props->tags ) )
        {
            return;
        }

        $max            = $props->tags[ 'max' ];
        $max            = $this->getValueAccordingPropType( $type, $max );

        if ( !( $value > $max ) )
        {
            return;
        }

        $formated_max   = $this->formatValueAccordingPropType( $type, $max );
        $formated_value = $this->formatValueAccordingPropType( $type, $value );
        $msg_model      = Config::get( 'validations.msg.max' );
        $msg            = sprintf(
            $msg_model,
            $props->desc,
            $referer,
            $formated_max,
            $formated_value
        );
        throw new WarningException( $msg );
    }

    /**
     * Valida se foi informado valor para o campo indicado.
     */
    protected function validatePropTAGMinlength( $value, $field, $referer )
    {
        $props = static::$propsReflection[ $field ];

        if ( !array_key_exists( 'minlength', $props->tags ) )
        {
            return;
        }

        $lenval         = strlen( $value );
        $minlength      = $props->tags[ 'minlength' ];
        
        if ( $lenval < $props->tags[ 'minlength' ] )
        {
            $msg_model  = Config::get( 'validations.msg.minlength' );
            $msg        = sprintf( $msg_model, $props->desc, $referer, $minlength, $lenval );
            throw new WarningException( $msg );
        }
    }

    /**
     * Valida se foi informado valor para o campo indicado.
     */
    protected function validatePropTAGMaxlength( $value, $field, $referer )
    {
        $props = static::$propsReflection[ $field ];

        if ( !array_key_exists( 'maxlength', $props->tags ) )
        {
            return;
        }

        $lenval         = strlen( $value );
        $maxlength      = $props->tags[ 'maxlength' ];
        
        if ( $lenval > $props->tags[ 'maxlength' ] )
        {
            $msg_model  = Config::get( 'validations.msg.maxlength' );
            $msg        = sprintf( $msg_model, $props->desc, $referer, $maxlength, $lenval );
            throw new WarningException( $msg );
        }
    }

    /**
     * Valida se foi informado valor para o campo indicado.
     */
    protected function validatePropTAGSelectedOption( $value, $field, $referer )
    {
        $props = static::$propsReflection[ $field ];

        if ( !array_key_exists( 'option', $props->tags ) )
        {
            return;
        }

        if ( empty( $props->tags[ 'option' ][ $value ] ) )
        {
            $msg_model  = Config::get( 'validations.msg.options' );
            $msg        = sprintf( $msg_model, $props->desc, $referer );
            throw new WarningException( $msg );
        }
    }

    /**
     * Captura as definições da propriedade indicada da entidade.
     */
    protected static function getPropReflection( $field )
    {
        if ( isset( static::$propsReflection[ $field ] ) )
        {
            return static::$propsReflection[ $field ];
        }

        $reflection = new \ReflectionProperty( get_called_class(), $field );
        $parser     = DocBlockParser::parse( $reflection->getDocComment() );
        $tags       = $parser->getTAGs();

        foreach ( $tags as $key => &$tag )
        {
            if ( $key == 'option' )
            {
                $tag    = self::parseOptions( $tag );
                continue;
            }

            if ( in_array( $key, ['test-accept', 'test-fail'] ) )
            {
                foreach ( $tag as &$test )
                {
                    $test = preg_split( '@[\s\t]+@', $test, 2 );

                    if ( !isset( $test[ 1 ] ) )
                    {
                        $test[ 1 ] = null;
                    }
                }

                continue;
            }

            $tag        = $tag[ 0 ];
        }

        static::$propsReflection[ $field ] = (object) [
            'desc'  => $parser->getDescription(),
            'tags'  => $tags,
        ];

        return static::$propsReflection[ $field ];
    }

    /**
     * Retorna as propriedades da classe.
     */
    public static function getLayout( $unsafe = false )
    {

        $active_class   = get_called_class();
        $reflection     = new \ReflectionClass( get_called_class() );
        $metadata       = DocBlockParser::parse( $reflection->getDocComment() );
        $tags           = $metadata->getTags();
        $entity_name    = $tags[ 'entity' ][0];
        $delete_text    = $tags[ 'delete-text' ][0];
        $layout         = [
            'entity'        => $entity_name,
            'delete_text'   => $delete_text,
        ];

        $properties     = $reflection->getProperties();
        $columns        = [];

        foreach ( $properties as $i_prop => $prop )
        {
            if ( $prop->isStatic() )
            {
                continue;
            }

            $parser             = DocBlockParser::parse( $prop->getDocComment() );
            $name               = $prop->name;
            $tags               = $parser->getTAGs();

            foreach ( $tags as $key => &$tag )
            {
                if ( $key == 'private' && !$unsafe )
                {
                    unset( $prop->$i_prop );
                    continue 2;
                }

                if ( !in_array( $key, self::$htmlProps ) )
                {
                    unset( $tags[ $key ] );
                    continue;
                }

                if ( $key == 'option' )
                {
                    $tag    = self::parseOptions( $tag );
                    continue;
                }

                $tag            = $tag[ 0 ];
            }

            $tags[ 'label' ]    = $parser->getDescription();
            $columns[ $name ]   = $tags;
        }

        $layout[ 'columns' ] = $columns;

        return json_decode( json_encode( $layout ) );
    }

    /**
     * Captura os options da TAG.
     */
    public static function parseOptions( $tag )
    {
        $options = [];

        foreach ( $tag as $option )
        {
            $parts_option           = preg_split( '@[\s\t]+@', $option );
            $key_option             = array_shift( $parts_option );
            $options[ $key_option ] = $parts_option
                ? implode( ' ', $parts_option )
                : $key_option;
        }
        
        return $options;
    }
}
