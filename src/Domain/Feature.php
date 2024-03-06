<?php

namespace JF\Domain;

use JF\Config;
use JF\DB\DB;
use JF\Exceptions\ErrorException as Error;
use JF\FileSystem\Dir;
use JF\Reflection\DocBlockParser;
use JF\User;

/**
 * Classe de funcionalidades do domínio.
 */
class Feature extends \StdClass
{
    /**
     * Indica se a funcionalidade exige permissão de acesso.
     */
    protected static $requirePermission = false;

    /**
     * Etapas da execução.
     */
    protected $_steps = [];

    /**
     * Mensagem de retorno.
     */
    protected $msg = '';

    /**
     * Mensagem de retorno.
     */
    protected $_performaceTime = null;

    /**
     * Instância do esquema para registrar a performance.
     */
    protected $servicePerformace;

    /**
     * Método construtor.
     */
    public function __construct()
    {
        $this->_microtime   = $_SERVER[ 'REQUEST_TIME_FLOAT' ];
        $this->_ticks       = [];
        $this->setSteps();
    }

    /**
     * Método construtor.
     */
    public function __destruct()
    {
        $this->registerRequest();
    }

    /**
     * Define as etapas da funcionalidade.
     */
    protected function setSteps()
    {
        
    }

    /**
     * Define as etapas da funcionalidade.
     */
    public function getSteps()
    {
        $steps = $this->_steps;

        foreach ( $steps as &$step )
        {
            $reflection = new \ReflectionMethod( $this, $step );
            $comment    = $reflection->getDocComment();
            $step       = DocBlockParser::parse( $comment )->getDescription();
        }

        return $steps;
    }

    /**
     * Implementa a execução da funcionalidade.
     */
    protected function execution()
    {

    }

    /**
     * Cria uma nova instância da entidade.
     */
    public static function instance( $props = [] )
    {
        $instance = new static();
        
        foreach ( $props as $key => $value )
        {
            $instance->set( $key, $value );
        }

        return $instance;
    }

    /**
     * Cria uma instância da operação e executa.
     */
    public static function run()
    {
        return static::instance()->execute();
    }

    /**
     * Define um valor para a funcionalidade.
     */
    public function set( $key, $value )
    {
        $this->$key = $value;

        return $this;
    }

    /**
     * Exporta as propriedades da funcionalidade.
     */
    public static function export()
    {
        $reflection     = new \ReflectionClass( get_called_class() );
        $props          = $reflection->getProperties();
        $response       = (object) [];

        foreach ( $props as $prop )
        {
            if ( !$prop->isPublic() || $prop->isStatic() )
            {
                continue;
            }

            $comment            = $prop->getDocComment();
            $name               = $prop->getName();
            $response->$name    = DocBlockParser::parse( $comment )->getDescription();
        }

        return $response;
    }

    /**
     * Define uma etapa da execução.
     */
    public function step( $step )
    {
        if ( !method_exists( $this, $step ) )
        {
            $reflection = new \ReflectionClass( $this );
            $comment    = $reflection->getDocComment();
            $desc       = DocBlockParser::parse( $comment )->getDescription();
            
            throw new Error( "Método $step não encontrado na funcionalidade \"$desc\"." );
        }

        $this->_steps[ $step ] = $step;
    }

    /**
     * Aplica as regras de negócio da funcionalidade.
     */
    public function execute()
    {
        $req_permission = static::$requirePermission;

        if ( $req_permission && !User::get() )
            throw new Error( 'Usuário identificado.' );

        if ( $req_permission && !User::hasPermission( get_called_class() ) )
            throw new Error( 'Usuário sem permissão para executar a operação.' );

        $entities       = [];
        $reflection     = new \ReflectionClass( get_called_class() );
        $props          = $reflection->getProperties();

        foreach ( $props as $prop )
        {
            if ( !$prop->isPublic() || $prop->isStatic() )
                continue;

            $comment    = $prop->getDocComment();
            $tags       = DocBlockParser::parse( $comment )->getTags();

            if ( empty( $tags[ 'entity' ] ) )
                continue;
            
            $entities[] = $prop->name;
        }

        foreach ( $entities as $entity )
            $this->$entity->validate();

        foreach ( $this->_steps as $step )
            $this->$step();

        return $this->execution();
    }

    /**
     * Aplica as regras de negócio da operação.
     */
    public function applyRules( $context = null )
    {
        $classname      = get_called_class();
        $reflection     = new \ReflectionClass( $classname );
        $rule_ns        = $reflection->getNamespaceName() . '\\Rules';
        $rule_ns       .= $context
            ? '\\' . $context
            : '';
        $dir_rules      = 1;

        $namespaces     = Config::get( 'namespaces' );
        $rules_path     = $rule_ns;

        foreach ( $namespaces as $ns => $path )
        {
            if ( strpos( $rule_ns, $ns ) === 0 )
            {
                $rules_path = $path . substr( $rule_ns, strlen( $ns ) );
                break;
            }
        }

        $rules_path     = str_replace( '\\', '/', $rules_path );
        $rules_path     = DIR_BASE . '/' . $rules_path;

        if ( !file_exists( $rules_path ) )
            Dir::makeDir( $rules_path );

        $dir_rules      = new \FilesystemIterator( $rules_path );
        $rulemodel      = 'JF\\Domain\\Rule';

        foreach ( $dir_rules as $item )
        {
            if ( !$item->isFile() )
                continue;

            $filename   = $item->getFileName();
            $ruleclass  = $rule_ns . '\\' . substr( $filename, 0, -4 );

            if ( substr( $filename, -10 ) != '__Rule.php' )
                continue;

            if ( !class_exists( $ruleclass ) )
                throw new Error( "Regra de negócio {$ruleclass} não encontrada em {$classname}." );

            if ( !is_subclass_of( $ruleclass, $rulemodel ) )
                throw new Error( "{$ruleclass} não estende à classe $rulemodel." );

            $rule_obj   = new $ruleclass( $this );
            $rule_obj->execute();
        }
    }

    /**
     * Aplica as regras de negócio da operação.
     */
    public function applyRule( $rule )
    {
        $rule       = str_replace( '.', '\\', $rule );
        $classname  = get_called_class();
        $reflection = new \ReflectionClass( $classname );
        $namespace  = $reflection->getNamespaceName();
        $ruleclass  = $namespace . '\\Rules\\' . $rule . '__Rule';
        $rulemodel  = 'JF\\Domain\\Rule';

        if ( !class_exists( $ruleclass ) )
            throw new Error( "Regra de negócio {$ruleclass} não encontrada em {$classname}." );

        if ( !is_subclass_of( $ruleclass, $rulemodel ) )
            throw new Error( "{$ruleclass} não estende à classe $rulemodel." );

        $rule_obj   = new $ruleclass( $this );
        $rule_obj->execute();
    }

    /**
     * Aplica as regras de negócio da operação.
     */
    protected function registerPerformance( $step, $extra = [] )
    {
        $table      = Config::get( 'logs.performance.table' );
        $feature    = preg_replace( '@^Features\\\(.*?)\\\Feature@', '$1', static::CLASS );
        $feature    = str_replace( '\\', '.', $feature );

        if ( !$this->servicePerformace )
        {
            $schema                     = Config::get( 'logs.performance.schema' );
            $this->servicePerformace    = DB::instance( $schema );
            $sql                        = "DELETE FROM `$table` WHERE `feature` = '$feature'";
            $result                     = $this->servicePerformace->execute( $sql )->count();
        }
        
        $now            = microtime( true );
        $data           = [
            'feature'   => $feature,
            'date'      => date( 'Y-m-d' ),
            'time'      => date( 'H:i:s' ),
            'duration'  => microtime( true ) - ($this->_performaceTime ?? $_SERVER[ 'REQUEST_TIME_FLOAT' ]),
            'step'      => $step,
            'extra'     => json_encode( $extra ),
        ];
        $this->_performaceTime = $now;
        $sql            = "INSERT INTO `$table`( `feature`, `date`, `time`, `duration`, `step`, `extra` ) VALUES ( :feature, :date, :time, :duration, :step, :extra )";
        $result         = $this->servicePerformace->execute( $sql, $data )->count();
    }

    /**
     * Aplica as regras de negócio da operação.
     */
    private function registerRequest()
    {
        $schema     = Config::get( 'logs.requests.schema' );
        $table      = Config::get( 'logs.requests.table' );
        $service    = preg_replace( '@^Features\\\(.*?)\\\Feature@', '$1', static::CLASS );
        $service    = str_replace( '\\', '.', $service );
        $data           = [
            'service'   => $service,
            'date'      => date( 'Y-m-d' ),
            'time'      => date( 'H:i:s' ),
            'duration'  => microtime( true ) - $_SERVER[ 'REQUEST_TIME_FLOAT' ],
        ];
        $sql            = "INSERT INTO `$table`( `service`, `date`, `time`, `duration` ) VALUES ( :service, :date, :time, :duration )";
        $result         = DB::instance( $schema )->execute( $sql, $data )->count();
    }

    /**
     * Retorna a mensagem de resposta da operação.
     */
    public function msg()
    {
        return $this->msg;
    }

    /**
     * Marca o tempo de execução do momento.
     */
    public function tick( $name = null )
    {
        $microtime          = microtime(1);
        $tick               = $microtime - $this->_microtime;
        $this->_microtime   = $microtime;
        $this->_ticks[]     = $name
            ? [ $tick, $name ]
            : $tick;
    }

    /**
     * Retorna os tempos marcados.
     */
    public function ticks()
    {
        return $this->_ticks;
    }
}
