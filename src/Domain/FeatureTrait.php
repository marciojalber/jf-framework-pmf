<?php

namespace JF\Domain;

use JF\Exceptions\DeniedException;
use JF\Exceptions\InfoException;
use JF\Exceptions\ErrorException;
use JF\Exceptions\WarningException;
use JF\User;

/**
 * Classe de funcionalidades do domínio.
 */
trait FeatureTrait
{
    /**
     * Indica se a funcionalidade exige permissão de acesso.
     */
    protected $requirePermission = false;
    
    /**
     * Instância da entidade que receberá os dados.
     */
    protected $_entity = null;

    /**
     * Cria uma nova instância da entidade.
     */
    public static function instance()
    {
        $instance = new static();

        if ( !empty( static::$entityClass ) )
        {
            $instance->_entity = new static::$entityClass();
        }

        return $instance;
    }

    /**
     * Cria uma instância da operação.
     */
    public static function run()
    {
        $instance = new static();
        
        return $instance->execute();
    }

    /**
     * Define um valor para a funcionalidade ou para a entidade embutida.
     */
    public function set( $key, $value )
    {
        if ( !$this->_entity || in_array( $key, static::getPublicProperties() ) )
        {
            $this->$key = $value;
            return $this;
        }

        $this->_entity->set( $key, $value );
        return $this;
    }

    /**
     * Identifica as propriedades públicas da funcionalidade.
     */
    protected static function getPublicProperties()
    {
        $class_reflection   = new \ReflectionClass( get_called_class() );
        $is_public          = \ReflectionProperty::IS_PUBLIC;
        $properties         = $class_reflection->getProperties( $is_public );

        foreach ( $properties as &$prop )
        {
            $prop = $prop->getName();
        }

        return $properties;
    }

    /**
     * Aplica as regras de negócio da operação.
     */
    public function execute()
    {
        if ( $this->requirePermission && !User::get() )
        {
            $msg = 'Usuário identificado.';
            throw new ErrorException( $msg );
        }

        if ( $this->requirePermission && !User::hasPermission( get_called_class() ) )
        {
            $msg = 'Usuário sem permissão para executar a operação.';
            throw new ErrorException( $msg );
        }

        $validation = !$this->_entity
            ? true
            : $this->_entity->isValid();

        if ( $validation !== true )
        {
            throw new WarningException( $validation );
        }

        $response   = $this->execution();
        $msg_error  = $this->msgError();
        
        if ( $msg_error && !$this->result )
        {
            throw new ErrorException( $msg_error );
        }
        
        return !method_exists( $this, 'response' )
            ? $response
            : $this->response();
    }

    /**
     * Aplica as regras de negócio da operação.
     */
    public function apply( $rule, $input = [] )
    {
        $method     = 'rule' . $rule;
        $class      = get_called_class();

        if ( !method_exists( $class, $method ) )
        {
            $msg    = "Regra de negócio $method não encontrada para a operação $class.";
            throw new ErrorException( $msg );
        }

        return call_user_func( [$this, $method], (object) $input );
    }

    /**
     * Aplica as regras de negócio da operação e, se não retornar true,
     * lança uma exceção de informação usando a mensagem de retorno da regra.
     */
    public function info( $rule, $input = [] )
    {
        $msg = $this->apply( $rule, $input );

        if ( $msg !== true )
        {
            throw new InfoException( $msg );
        }
    }

    /**
     * Aplica as regras de negócio da operação e, se não retornar true,
     * lança uma exceção de alerta usando a mensagem de retorno da regra.
     */
    public function warning( $rule, $input = [] )
    {
        $msg = $this->apply( $rule, $input );

        if ( $msg !== true )
        {
            throw new WarningException( $msg );
        }
    }

    /**
     * Aplica as regras de negócio da operação e, se não retornar true,
     * lança uma exceção de erro usando a mensagem de retorno da regra.
     */
    public function error( $rule, $input = [] )
    {
        $msg = $this->apply( $rule, $input );

        if ( $msg !== true )
        {
            throw new ErrorException( $msg );
        }
    }

    /**
     * Monta a mensagem de erro caso a operação dê errado.
     */
    public function msgError()
    {
        return null;
    }
}
