<?php

namespace JF\Domain;

use JF\Exceptions\ErrorException;
use JF\FileSystem\Dir;
use JF\HTTP\ControllerParser;

/**
 * Classe de execução dos testes unitários.
 */
class Tester
{
    /**
     * Executa os testes de integração.
     */
    public static function execute()
    {
        if ( !ENV_DEV )
        {
            return;
        }

        $controller     = ControllerParser::controller();
        $prefix         = 'Features';
        $len_prefix     = strlen( $prefix );

        if ( substr( $controller, 0, $len_prefix ) != $prefix )
        {
            return;
        }

        $reflection     = new \ReflectionClass( $controller );
        $namespace      = $reflection->getNamespaceName();
        $filename       = $reflection->getFilename();
        $pathname       = dirname( $filename ) . '/TestCases';

        if ( !file_exists( $pathname ) )
        {
            Dir::makeDir( $pathname );
        }

        $path_obj       = new \FilesystemIterator( $pathname );
        $test_model     = 'JF\\Domain\\TestCase';
        $tests          = [];
        $len_sufix      = strlen( '__TestCase.php' );

        foreach ( $path_obj as $item )
        {
            $test_name  = substr( $item->getFileName(), 0, -$len_sufix );
            $test_class = $namespace . '\\TestCases\\' . substr( $item->getFileName(), 0, -4 );

            if ( !class_exists( $test_class ) )
            {
                $msg    = "A classe do teste \"{$test_name}\" não foi implementada.";
                throw new ErrorException( $msg );
            }

            if ( !is_subclass_of( $test_class, $test_model ) )
            {
                $msg    = "O teste \"{$test_name}\" não estende à classe \"{$test_model}\".";
                throw new ErrorException( $msg );
            }

            $tests[]    = new $test_class();
        }

        foreach ( $tests as $test )
        {
            $test->execute();
        }
    }
}
