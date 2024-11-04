<?php

namespace JF\Tests;

/**
 * Classe modelo para os testes automatizados.
 */
class TestCase extends \StdClass
{
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

        try
        {
            $this->$method();
            $this->assertion    = 1;
        }
        catch( \Exception | \AssertionError $e )
        {
            $line               = is_a( $e, 'AssertionError' )
                ? $e->getTrace()[1]['line']
                : $e->getLine();

            return (object) [
                'file'      => $e->getFile(),
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
