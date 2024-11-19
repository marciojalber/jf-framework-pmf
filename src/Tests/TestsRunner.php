<?php

namespace JF\Tests;

use JF\Config;
use JF\Reflection\DocBlockParser;

/**
 * Classe para executar os testes automatizados.
 */
class TestsRunner extends \StdClass
{
    /**
     * Classes de entidade.
     */
    private $classes    = [];

    /**
     * Cálculo da cobertura de testes.
     */
    private $totals     = [];

    /**
     * Testes.
     */
    private $tests      = [];

    /**
     * Asserções realizadas.
     */
    private $asserts    = 0;

    /**
     * Asserções bem-sucedidas.
     */
    private $assertions = 0;

    /**
     * Asserções que falharam.
     */
    private $failures   = 0;

    /**
     * Impede a instanciação direta da classe.
     */
    private function __construct()
    {
        register_shutdown_function( function( ) {
            if ( !$error = error_get_last() )
                return;
            
            print_r( $error );
        });

        set_error_handler( function( $code, $message, $file, $line ) {
            $error = array(
                'code'      => $code,
                'message'   => $message,
                'file'      => $file,
                'line'      => $line,
                'type'      => 'ERROR',
            );
            
            print_r( $error );
        });

        set_exception_handler( function( $exception ) {
            $codeException  = $exception->getCode();
            $error          = array(
                'code'      => $codeException,
                'message'   => preg_replace( '/\nStack trace:.*/s', '', $exception->getMessage() ),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'type'      => 'EXCEPTION',
                'stack'     => $exception->getTraceAsString(),
            );
            
            print_r( $error );
        });
    }

    /**
     * Executa os testes automatizados.
     */
    public static function run()
    {
        $inst               = new self();
        $inst->timeStart    = time();
        $inst->timeEnd      = 0;
        $inst->lenbase      = strlen( DIR_BASE ) + 1;
        $inst->context      = null;
        $inst->parse( DIR_APP . '/DTO', 'DTO' );
        $inst->parse( DIR_APP . '/Providers', 'Providers' );
        $inst->parse( DIR_APP . '/Services', 'Services' );
        $inst->parse( DIR_APP . '/Types', 'Types' );
        $inst->timeEnd      = time();
        $inst->calcTotals();
        $inst->sendText();
    }

    /**
     * Executa os testes automatizados.
     */
    private function parse( $path, $context = null )
    {
        if ( $context )
        {
            $this->context = $context;

            if ( !isset( $this->classes[ $context ] ) )
            {
                $this->classes[ $context ]  = [];
                $this->totals[ $context ]   = (object) [
                    'total'                 => 0,
                    'coverage'              => 0,
                ];
            }
        }

        $dir    = new \FilesystemIterator( $path );
        $lendir = $this->lenbase + 4 + strlen( $this->context ) + 1;

        foreach ( $dir as $item )
        {
            $subpath        = $item->getPathname();
            $pathroot       = dirname( $item->getPathname() );
            $subpath_name   = $item->getFilename();

            if ( $item->isDir() )
            {
                if ( $subpath_name != 'Rules')
                    $this->parse( $subpath );

                continue;
            }

            if ( $context )
                continue;

            $entity_id      = substr( $pathroot, $lendir );
            
            if ( !isset( $this->classes[ $this->context ][ $entity_id ] ) )
                $this->classes[ $this->context ][ $entity_id ] = 0;

            if ( substr( $item->getPathname(), -8 ) != 'Test.php' )
                continue;

            $classname      = $this->getClassName( $subpath );
            $refclass       = new \ReflectionClass( $classname );
            $methods        = $refclass->getMethods();

            foreach ( $methods as $i => $method )
            {
                $name       = $method->getName();
                $id         = $classname . '::' . $name;
                $comment    = $method->getDocComment();
                $doc        = DocBlockParser::parse( $comment );
                
                if ( substr( $name, 0, 4 ) != 'test' )
                    continue;

                $this->classes[ $this->context ][ $entity_id ] = 1;
                $test               = new $classname();
                $result             = $test->executeTest( $name );
                $tot_asserts        = $test->totalAsserts();
                $was_assertion      = $test->wasAssertion();
                $assertion          = null;

                if ( $tot_asserts || $result )
                {
                    $assertion      = $was_assertion
                        ? 'OK'
                        : 'Fail';
                }

                $this->asserts      += $tot_asserts;
                $this->assertions   += $assertion == 'OK'
                    ? 1
                    : 0;
                $this->failures     += $assertion == 'Fail' || $result
                    ? 1
                    : 0;

                $this->tests[ $id ]  = (object) [
                    'class'         => $classname,
                    'method'        => $name,
                    'desc'          => $doc->getDescription(),
                    'asserts'       => $tot_asserts,
                    'result'        => $assertion,
                    'message'       => null,
                    'file'          => null,
                    'line'          => null,
                ];

                if ( $result )
                {
                    $this->tests[ $id ]->message    = $result->message;
                    $this->tests[ $id ]->file       = $result->file;
                    $this->tests[ $id ]->line       = $result->line;
                }
            }
        }
    }

    /**
     * Executa os testes automatizados.
     */
    private function getClassName( $path )
    {
        $classname = substr( $path, $this->lenbase, -4 );
        $classname = str_replace( '/', '\\', $classname );

        return $classname;
    }

    /**
     * Envia o texto de saída.
     */
    private function calcTotals()
    {
        foreach ( $this->classes as $context => $items )
        {
            foreach ( $items as $class => $val )
            {
                if ( $val )
                {
                    echo $class . PHP_EOL;
                }

                $this->totals[ $context ]->coverage += $val;
                ++$this->totals[ $context ]->total;
            }
        }
    }

    /**
     * Envia o texto de saída.
     */
    private function sendText()
    {
        // 5; PISCANDO
        // 31 PRETO COM VERDE
        // 32 PRETO COM VERMELHO
        // 41 VERDE COM BRANCO
        // 42 VERMELHO COM BRANCO

        $br     = PHP_EOL;
        echo PHP_EOL;
        echo 'Iniciou em           : ' . date( 'd/m/Y - H:i:s', $this->timeStart ) . PHP_EOL;
        echo 'Concluiu em          : ' . date( 'd/m/Y - H:i:s', $this->timeStart ) . PHP_EOL;
        echo 'Duração de           : ' . round( $this->timeEnd - $this->timeStart, 2 ) . ' segundos' . PHP_EOL;
        echo PHP_EOL;
        echo 'Total de asserções   : ' . $this->asserts . PHP_EOL;
        echo 'Total de testes      : ' . count( $this->tests ) . PHP_EOL;
        echo PHP_EOL;

        foreach ( $this->totals as $context => $values )
        {
            $perc   = number_format( $values->coverage * 100 / $values->total, 1, ',', '' );
            echo str_pad( "Cobertura de testes em $context", 33 ) . ": {$values->coverage} / {$values->total} ($perc%)" . PHP_EOL;
        }

        if ( !$this->tests )
        {
            echo PHP_EOL . PHP_EOL . PHP_EOL;
            echo 'NENHUM TESTE ENCONTRADO' . PHP_EOL;
            exit;
        }

        echo PHP_EOL . PHP_EOL;
        echo `echo \e[42mTESTES BEM-SUCEDIDOS : {$this->assertions}\e[0m`;

        if ( $this->assertions && 0 )
        {
            foreach ( $this->tests as $id => $test )
            {
                if ( $test->result == 'OK' )
                {
                    echo PHP_EOL;
                    echo `echo    \e[1m{$id}\e[0m`;
                    echo `echo    {$test->desc}`;
                }
            }
            
            echo PHP_EOL;
        }

        echo PHP_EOL . PHP_EOL;
        echo `echo \e[41;5mTESTES MAL-SUCEDIDOS : {$this->failures}\e[0m`;

        if ( $this->failures )
        {
            foreach ( $this->tests as $id => $test )
            {
                if ( $test->result != 'OK' )
                {
                    echo PHP_EOL;
                    echo `echo    \e[1mMétodo      \e[0m: {$id}`;
                    echo `echo    \e[1mLin/Arq.    \e[0m: {$test->line} - {$test->file}`;
                    echo `echo    \e[1mDesc. Teste \e[0m: {$test->desc}`;
                    echo `echo    \e[1mMensagem    \e[0m: {$test->message}`;
                }
            }
        }
    }
}
