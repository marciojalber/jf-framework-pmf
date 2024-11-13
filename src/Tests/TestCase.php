<?php

namespace JF\Tests;

use JF\Exceptions\ErrorException as Error;

/**
 * Classe modelo para os testes automatizados.
 */
class TestCase extends \StdClass
{
    /**
     * Tipo de asserção.
     */
    private static $assertTypes = [
        'TRUE',
        'FALSE',
        'EMPTY',
        'NOT_EMPTY',
        'THROW_EXCEPTION',
    ];

    /**
     * Tipo de asserção.
     */
    private $assertType     = null;

    /**
     * Mensagem de erro da asserção.
     */
    private $assertMsg      = null;

    /**
     * Código da exceção da asserção.
     */
    private $assertCode     = null;

    /**
     * Total de asserções realizadas.
     */
    private $totalAsserts   = 0;

    /**
     * Teste bem-sucedido.
     */
    private $assertion      = 0;

    /**
     * Um teste qualquer.
     */
    public function __construct()
    {
        $this->init();

        return $this;
    }

    /**
     * Um teste qualquer.
     */
    public function executeTest( $method )
    {
        $this->totalAsserts     = 0;
        $this->assertion        = 0;
        $this->assertType       = null;
        $this->assertMsg        = null;
        $this->assertCode       = null;

        try
        {
            // print_r( $this );exit;
            $result             = $this->$method();

            $this->assertType == 'TRUE' && $this->assert( $result, $this->assertMsg );
            $this->assertType == 'FALSE' && $this->assert( !$result, $this->assertMsg );
            $this->assertType == 'EMPTY' && $this->assertEmpty( $result, $this->assertMsg );
            $this->assertType == 'NOT_EMPTY' && $this->assertNotEmpty( $result, $this->assertMsg );
            $this->assertion    = 1;
        }
        catch( \Exception | \AssertionError $e )
        {
            $line               = is_a( $e, 'AssertionError' )
                ? $e->getTrace()[1]['line']
                : $e->getLine();
            $file               = is_a( $e, 'AssertionError' )
                ? $e->getTrace()[1]['file']
                : $e->getFile();

            if ( $this->assertType == 'THROW_EXCEPTION' && !$this->assertCode )
            {
                $this->totalAsserts++;
                $this->assertion    = 1;
                return;
            }

            if ( $this->assertType == 'THROW_EXCEPTION' && $this->assertCode == $e->getCode() )
            {
                $this->totalAsserts++;
                $this->assertion    = 1;
                return;
            }
            
            return (object) [
                'file'      => $file,
                'line'      => $line,
                'message'   => $e->getMessage(),
                'code'      => $e->getCode(),
            ];
        }
    }

    /**
     * Configurações iniciais da classe.
     */
    public function init()
    {

    }

    /**
     * Retorna o total de asserções realizadas.
     */
    public function totalAsserts()
    {
        return $this->totalAsserts;
    }

    /**
     * Retorna o total de asserções bem-sucedidas.
     */
    public function wasAssertion()
    {
        return $this->assertion;
    }

    /**
     * Define os parâmetros da asserção.
     */
    public function setAssert( $type, $msg = null, $code = null )
    {
        if ( !in_array( $type, self::$assertTypes ) )
        {
            $types = implode( ', ', self::$assertTypes );
            throw new Error( "Tipo de asserção [$type] inválida. Tipos válidos: $types." );
        }
        
        $this->assertType   = $type;
        $this->assertMsg    = $msg;
        $this->assertCode   = $code;
    }

    /**
     * Assegura que um teste retornará verdadeiro.
     */
    public function assert( $val, $msg_error = null )
    {
        $this->totalAsserts++;
        assert( $val, $msg_error );
    }

    /**
     * Assegura que um teste retornará valor vazio.
     */
    public function assertEmpty( $val, $msg_error = null )
    {
        $this->totalAsserts++;

        assert( empty( $val ), $msg_error );
    }

    /**
     * Assegura que um teste retornará valor não vazio.
     */
    public function assertNotEmpty( $val, $msg_error = null )
    {
        $this->totalAsserts++;
        assert( !empty( $val ), $msg_error );
    }

    /**
     * Assegura que um teste lançará uma excessão.
     */
    public function assertThrowException( $fn, $code, $msg_error = null )
    {
        $this->totalAsserts++;

        try
        {
            $fn();
            assert( 0, $msg_error );
        }
        catch ( \Exception $e )
        {
            assert( $e->getCode() == $code, $msg_error );
        }
    }
}
