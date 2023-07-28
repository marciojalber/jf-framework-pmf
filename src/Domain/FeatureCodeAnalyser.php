<?php

namespace JF\Domain;

/**
 * Analisa a qualidade do código de uma funcionalidade.
 */
class FeatureCodeAnalyser
{
    /**
     * Nome da feature.
     */
    public $feature;

    /**
     * Números dos métodos.
     */
    private $errors         = [];

    /**
     * .
     */
    private $source;

    /**
     * .
     */
    private $lines;

    /**
     * .
     */
    private $docfile;

    /**
     * .
     */
    private $classReflection;

    /**
     * .
     */
    private $classMethods;

    /**
     * Método construtor.
     */
    public function __construct( $feature )
    {
        $this->feature      = $feature;
    }

    /**
     * Invoca o metodo construtor.
     */
    public static function instance( $feature )
    {
        return new self( $feature );
    }

    /**
     * Solicita a análise do código da funcionalidade.
     */
    public function analyse()
    {
        $this->getAnalyseFile();
        $this->checkClassCode();
        $this->checkMethodsCode();
        $this->makeAnalyse();
    }

    /**
     * Obtém o nome do arquivo da documentação.
     */
    public function getAnalyseFile()
    {
        $classpath      = \JF\Autoloader::getClassFilename( $this->feature );
        $this->source   = file_get_contents( $classpath );
        $this->lines    = explode( PHP_EOL, $this->source );
        $this->docfile  = dirname( $classpath ) . '/_feature.analyse';
    }

    /**
     * Obtém os números da classe.
     */
    public function checkClassCode()
    {
        $this->classReflection  = new \ReflectionClass( $this->feature );
        $this->classMethods     = [];

        $class_methods          = $this->classReflection->getMethods();
        $use_service            = in_array( 'JF\\HTTP\\HTTP_Service_Trait', class_uses( $this->feature ) );

        foreach ( $class_methods as $method )
        {
            $method_name        = $method->getName();
            
            if ( $method->getDeclaringClass()->getName() != $this->feature )
                continue;
             
            if ( $use_service && method_exists( 'JF\\HTTP\\HTTP_Service_Trait', $method_name ) )
                continue;
            
            $this->classMethods[] = $method;
        }

        if ( $this->classReflection->getEndLine() > 300 )
            $this->errors[]     = '<Feature> Classe com mais de 300 linhas';

        if ( count( $this->classMethods ) > 30 )
            $this->errors[]     = '<Feature> Classe com mais de 30 métodos';

        if ( preg_match( '@\?>@', $this->source ) )
            $this->errors[]     = '<Feature> A tag de fechamento (?>) deve ser removida';

        foreach ( $this->lines as $line => $content )
        {
            if ( preg_match( '@^\s*\t+\s*@', $content, $match ) )
                $this->errors[] = "<Feature:$line> identação com TAB - substitua por 4 espaços";
        }
    }

    /**
     * Obtém os números dos métodos.
     */
    public function checkMethodsCode()
    {
        foreach ( $this->classMethods as $method )
        {
            $method_name    = $method->getName();
            $docblock       = preg_replace( '@/\*\*[\s\t]*(.*)[\s\t]*\*/@', '$1', $method->getDocComment() );
            $lines          = $this->getMethodLines( $method );

            if ( !isset( $method_name[ 7 - 1 ] ) )
                $this->errors[]     = "[$method_name] Método com menos de 7 caracteres";

            if ( isset( $method_name[ 30 ] ) )
                $this->errors[]     = "[$method_name] Método com mais de 30 caracteres";

            if ( !$docblock )
                $this->errors[]     = "[$method_name] Método sem DockBlock";

            if ( isset( $lines[ 30 ] ) )
                $this->errors[]     = "[$method_name] Método tem mais de 30 linhas";

            foreach ( $lines as $line => $content )
            {
                $content       = preg_replace( '@(?:)(\t|\s{4})@', '    ', $content );
                preg_match( '@^\s*@', $content, $match );
                $tabs       = $match[ 0 ]
                    ? strlen( $match[ 0 ] ) / 4
                    : 0;

                $line += $method->getStartLine();

                if ( preg_match( '@;.*?;@', $content ) )
                    $this->errors[] = "[$method_name:$line] mais de uma expressão por linha";
    
                if ( preg_match( '@(::|->)[\s\t]*[\w_\d]+[\s\t]*\(.*?\)[\s\t]*->@', $content ) )
                    $this->errors[] = "[$method_name:$line] mais de um método por linha";
    
                if ( isset( $content[ 100 ] ) )
                    $this->errors[] = "[$method_name:$line] mais de 100 colunas";

                if ( $tabs - 1 > 3 )
                    $this->errors[] = "[$method_name:$line] mais de 3 níveis de identação";
            }
        }
    }

    /**
     * Obtém as linhas de um método.
     */
    public function getMethodLines( $method )
    {
        $len_lines      = $method->getEndLine() - $method->getStartLine() + 1;
        $lines          = array_slice( $this->lines, $method->getStartLine() - 1, $len_lines );
        
        foreach ( $lines as $line => $content )
        {
            unset( $lines[ $line ] );
            
            if ( preg_match( '@{@', $content ) )
                break;
        }
        
        if ( !$lines )
            return [];
        
        $last_line  = max( array_keys( $lines ) );

        if ( trim( $lines[ $last_line ] ) == '}' )
            array_pop( $lines );

        return $lines;
    }

    /**
     * Grava os erros encontrados na qualidade do código.
     */
    public function makeAnalyse()
    {
        $data   = 'Análise realizada em ' . date( 'd/m/Y H:i:s' ) . '.' . PHP_EOL .PHP_EOL;
        $data  .= $this->errors
            ? implode( PHP_EOL, $this->errors )
            : 'TUDO CERTO POR AQUI';

        file_put_contents( $this->docfile, $data );
    }
}
