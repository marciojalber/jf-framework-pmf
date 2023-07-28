<?php

namespace JF;

/**
 * Classe responsável pela execução dos testes automatizados.
 */
abstract class Tester
{
    /**
     * Armazena os testes a serem executados.
     */
    protected $tests = [];

    /**
     * Executa os testes.
     */
    protected function addTest( $title, $class_domain, $method, $args, $test_callback )
    {
        $this->tests[ $title ] = (object) [
            'class_domain'  => $class_domain,
            'method'        => $method,
            'args'          => $args,
            'callback'      => $test_callback,
        ];
    }

    /**
     * Executa os testes.
     */
    public function execute()
    {
        $tests      = [];
        $ok_tests   = 0;
        $fail_tests = 0;
        
        foreach ( $this->tests as $title => $test )
        {
            $class_domain           = str_replace( '.', '\\', $test->class_domain );
            $class_domain           = "app\\domain\\{$class_domain}__Entity";
            $method                 = $test->method;

            if ( !class_exists( $class_domain ) )
            {
                $tests[ $title ]    = [
                    'falhou',
                    "A classe '$class_domain' não foi encontrada!",
                ];
                $fail_tests++;
                continue;
            }

            if ( !method_exists( $class_domain, $method ) )
            {
                $tests[ $title ]    = [
                    'falhou',
                    "O método '$method' não foi declarado na classe '$class_domain'!",
                ];
                $fail_tests++;
                continue;
            }

            try {
                $result             = call_user_func_array(
                    [$class_domain, $method],
                    $test->args
                );
            }
            catch ( \Exception $e )
            {
                $tests[ $title ]    = [
                    'falhou',
                    $e->getMessage(),
                ];
                $fail_tests++;
                continue;
            }
            
            $test_callback          = $test->callback;

            if ( !$test_callback( $result ) )
            {
                $tests[ $title ]    = [
                    'falhou',
                    'Não passou no teste de callback!',
                ];
                $fail_tests++;
                continue;
            }

            $tests[ $title ]        = [
                'OK',
                'passou no teste de callback!',
            ];
            $ok_tests++;
        }

        return (object) [
            'tests' => $tests,
            'ok'    => $ok_tests,
            'fail'  => $fail_tests,
        ];
    }
}
