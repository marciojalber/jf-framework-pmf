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
        $inst->ns           = Config::get( 'namespaces' );

        foreach ( $inst->ns as &$item )
            $item           = str_replace( '/', '\\', $item );

        $inst->ns           = array_flip( (array) $inst->ns );
        
        $inst->parse( DIR_APP );
        $inst->timeEnd      = time();
        $inst->sendText();
    }

    /**
     * Executa os testes automatizados.
     */
    private function parse( $path )
    {
        $dir = new \FilesystemIterator( $path );

        foreach ( $dir as $item )
        {
            if ( $item->isDir() )
            {
                $subpath = $item->getPathname();
                $this->parse( $subpath );
                continue;
            }

            $path = $item->getPathname();

            if ( substr( $item->getPathname(), -8 ) != 'Test.php' )
                continue;
            
            $classname  = $this->getClassName( $path );
            $test       = new $classname();
            
            $refclass   = new \ReflectionClass( $test );
            $methods    = $refclass->getMethods();

            foreach ( $methods as $i => $method )
            {
                $name       = $method->getName();
                $id         = $classname . '::' . $name;
                $comment    = $method->getDocComment();
                $doc        = DocBlockParser::parse( $comment );
                
                if ( substr( $name, 0, 4 ) != 'test' )
                    continue;

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

        foreach ( $this->ns as $ns => $ns_class )
        {
            if ( $ns != substr( $classname, 0, strlen( $ns ) ) )
                continue;

            $classname = $ns_class . substr( $classname, strlen( $ns ) );
            break;
        }

        return $classname;
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

        if ( !$this->tests )
        {
            echo PHP_EOL . PHP_EOL . PHP_EOL;
            echo 'NENHUM TESTE ENCONTRADO' . PHP_EOL;
            exit;
        }

        echo PHP_EOL . PHP_EOL;
        echo `echo \e[42mTESTES BEM-SUCEDIDOS : {$this->assertions}\e[0m`;

        if ( $this->assertions )
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
