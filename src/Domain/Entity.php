<?php

namespace JF\Domain;

use JF\Reflection\DocBlockParser;
use JF\Exceptions\WarningException;
use JF\Exceptions\ErrorException;

/**
 * Habilita a entidade a realizar validações
 */
class Entity
{
    use ValidationTrait;
    
    /**
     * Nome da entidade.
     */
    protected static $name  = null;
    
    /**
     * Propriedades da entidade e regras de preenchimento.
     */
    protected static $props = null;
    
    /**
     * Regras de preenchimento condicionais.
     */
    protected static $rules = [];

    /**
     * Cria uma nova instância da entidade.
     */
    public static function instance()
    {
        return new static();
    }

    /**
     * Exporta a estrutura da entidade.
     */
    public static function export()
    {
        $classname      = get_called_class();
        $class          = new \ReflectionClass( $classname );
        $classprops     = $class->getProperties();
        $response       = (object) [];

        foreach ( $classprops as $prop )
        {
            if ( $prop->isStatic() )
            {
                continue;
            }

            $propname           = $prop->getName();
            $comments           = $prop->getDocComment();
            $parser             = DocBlockParser::parse( $comments );
            $tags               = (object) $parser->getTags();
            $type               = isset( $tags->type )
                ? $tags->type[ 0 ]
                : null;
            $type_class         = strpos( $type, 'Types\\' ) === 0;

            if ( $type_class && !class_exists( $type ) )
            {
                $entity_name    = static::$name;
                $msg            = "Propriedade '$propname' da entidade '$entity_name' indica um tipo de dado não encontrado: $type.";
                throw new ErrorException( $msg );
            }

            $attrs              = $type_class
                ? $type::export()
                : null;

            $props              = [
                'name'          => $propname,
                'type'          => $attrs
                    ? $attrs->type
                    : $type,
            ];
            
            self::getTAG( $props, $attrs, $tags, 'decimals' );
            self::getTAG( $props, $attrs, $tags, 'default' );
            self::getTAG( $props, $attrs, $tags, 'label' );
            self::getTAG( $props, $attrs, $tags, 'mask' );
            self::getTAG( $props, $attrs, $tags, 'max' );
            self::getTAG( $props, $attrs, $tags, 'maxitens' );
            self::getTAG( $props, $attrs, $tags, 'maxlength' );
            self::getTAG( $props, $attrs, $tags, 'min' );
            self::getTAG( $props, $attrs, $tags, 'minitens' );
            self::getTAG( $props, $attrs, $tags, 'minlength' );
            self::getTAG( $props, $attrs, $tags, 'options' );
            self::getTAG( $props, $attrs, $tags, 'pattern' );
            self::getTAG( $props, $attrs, $tags, 'required', true );
            self::getTAG( $props, $attrs, $tags, 'tip' );
            self::getTAG( $props, $attrs, $tags, 'trim', true );

            $response->$propname = (object) $props;
        }

        return (object) [
            'name'              => static::$name,
            'props'             => $response,
            'rules'             => json_decode( json_encode( static::$rules ) ),
        ];
    }

    /**
     * Exporta a estrutura da entidade.
     */
    public static function getTAG( &$props, $attrs, $tags, $context, $invert = false )
    {
        if ( isset( $tags->$context ) )
        {
            $prop = $tags->$context;

            if ( isset( $prop[ 0 ] ) )
            {
                $props[ $context ] = $prop[ 0 ];
            }
            
            return;
        }

        if ( isset( $attrs->$context ) )
        {
            $props[ $context ] = $attrs->$context;
        }
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
     * Aplica as validações de cada propriedade da entidade.
     */
    public function validate()
    {
        $validation = $this->isValid();

        if ( $validation !== true )
        {
            throw new WarningException( $validation );
        }

        return $this;
    }

    /**
     * Aplica as validações de cada propriedade da entidade.
     */
    public function isValid()
    {
        $entity = static::export();
        $props  = $entity->props;
        $rules  = $entity->rules;

        foreach ( $props as $name => $prop )
        {
            $value = $this->$name;

            if ( true !== ( $validation = $this->applyValidation( $value, $prop ) ) )
            {
                return $validation;
            }
        }

        foreach ( $rules as $rule )
        {
            $validation = $this->validateRulePeriod( $rule, $props );

            if ( true !== $validation )
            {
                return $validation;
            }
        }

        return true;
    }

    /**
     * Aplica as validações de cada propriedade da entidade.
     */
    protected function validateRulePeriod( $rule, $props )
    {
        $start         = $rule->start;
        $end           = $rule->end;
        $start_value   = $this->$start;
        $end_value     = $this->$end;

        if ( !$start_value || !$end_value )
        {
            return true;
        }

        if ( $start_value <= $end_value )
        {
            return true;
        }

        $start_label   = $props->$start->label;
        $end_label     = ucfirst( $props->$end->label );
        $entity        = static::$name;

        return "{$end_label} de $entity deve ser maior que {$start_label}.";
    }
}
