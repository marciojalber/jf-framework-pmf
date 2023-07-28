<?php

namespace JF\Domain;

/**
 * Monta a documentação da funcionalidade.
 */
class FeatureDocWriter
{
    /**
     * Nome da feature.
     */
    public $feature;

    /**
     * Descrição da funcionalidade.
     */
    public $info        = null;

    /**
     * Descrição da funcionalidade.
     */
    public $desc        = [];

    /**
     * Paâmetros da funcionalidade.
     */
    public $params      = [];

    /**
     * Fluxo da execução.
     */
    public $workflow    = [];

    /**
     * Regras de negócio.
     */
    public $rules       = [];

    /**
     * Casos de teste.
     */
    public $tests       = [];

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
     * Solicita a montagem da documentação.
     */
    public function make()
    {
        $this->getDocFile();
        $this->getServiceInfo();
        $this->getDescParamsAndWorkflow();
        $this->getRulesDescription();
        $this->getTestCasesContent();
        $this->makeDoc();
    }

    /**
     * Obtém o nome do arquivo da documentação.
     */
    public function getDocFile()
    {
        $classpath      = \JF\Autoloader::getClassFilename( $this->feature );
        $classpath      = dirname( $classpath );
        $this->docfile  = $classpath . '/_feature.document';
    }

    /**
     * Obtém as informações do serviço HTTP.
     */
    public function getServiceInfo()
    {
        $traits     = class_uses( $this->feature );
        
        if ( !isset( $traits[ 'JF\\HTTP\\HTTP_Service_Trait' ] ) )
            return;

        $url        = strtolower( $this->feature );
        $url        = str_replace( '\\', '/', $url );
        $url        = substr( $url, 0, -8 );

        $this->info = (object) [
            'methods'   => implode( ', ', $this->feature::acceptHTTPMethods() ),
            'url'       => $url,
        ];
    }

    /**
     * Obtém a descrição da documentação.
     */
    public function getDescParamsAndWorkflow()
    {
        $reflection     = new \ReflectionClass( $this->feature );
        $comment        = $reflection->getDocComment();
        $doc            = \JF\Reflection\DocBlockParser::parse( $comment );
        $this->desc     = $doc->getDescription();

        foreach ( $reflection->getProperties() as $prop )
        {
            if ( $prop->isStatic() || !$prop->isPublic() )
                continue;

            $name       = $prop->getName();
            $comment    = $prop->getDocComment();
            $doc        = \JF\Reflection\DocBlockParser::parse( $comment );
            $this->params[ $name ] = $doc->getDescription();
        }

        $feature        = new $this->feature();
        $this->workflow = $feature->getSteps();
    }

    /**
     * Obtém a descrição das regras de negócio.
     */
    public function getRulesDescription()
    {
        $rules_path     = dirname( $this->docfile ) . '/Rules';

        if ( !file_exists( $rules_path ) )
            return;

        $dir_rules      = new \FileSystemIterator( $rules_path );

        foreach ( $dir_rules as $rule_file )
        {
            $rule_file      = str_replace( '/', '\\', $rule_file->getPathname() );
            $rule_file      = substr( $rule_file, 0, -4 );
            $rule_class     = preg_replace( '@.*Features@', 'Features', $rule_file );
            $reflection     = new \ReflectionClass( $rule_class );
            $comment        = $reflection->getDocComment();
            $doc            = \JF\Reflection\DocBlockParser::parse( $comment );
            $this->rules[]  = $doc->getDescription();
        }
    }

    /**
     * Obtém os casos de teste.
     */
    public function getTestCasesContent()
    {
        $tests_path     = dirname( $this->docfile ) . '/TestCases';

        if ( !file_exists( $tests_path ) )
            return;

        $dir_tests      = new \FileSystemIterator( $tests_path );

        foreach ( $dir_tests as $test_file )
        {
            $test_file      = str_replace( '/', '\\', $test_file->getPathname() );
            $test_file      = substr( $test_file, 0, -4 );
            $test_class     = preg_replace( '@.*Features@', 'Features', $test_file );
            $reflection     = new \ReflectionClass( $test_class );
            $comment        = $reflection->getDocComment();
            $doc            = \JF\Reflection\DocBlockParser::parse( $comment );
            $this->tests[]  = $doc->getDescription();
            $tags           = $doc->getTags();

            if ( isset( $tags[ 'when' ] ) )
                $this->tests[]  = '    Quando ' . implode( PHP_EOL . '    E ', $tags[ 'when' ] );

            if ( isset( $tags[ 'then' ] ) )
                $this->tests[]  = '    Então ' . implode( PHP_EOL . '    E ', $tags[ 'then' ] );
            
            $this->tests[]  = '';
        }
    }

    /**
     * Monta a documentação.
     */
    public function makeDoc()
    {
        $feature    = substr( $this->feature, 9, -8 );
        $feature    = str_replace( '\\', '.', $feature );
        $content[]  = 'Funcionalidade ' . $feature;
        $content[]  = str_repeat( '=', strlen( 'Funcionalidade ' . $feature ) );

        if ( $this->info )
        {
            $content[]  = '';
            $content[]  = '';
            $content[]  = 'Informações do Serviço';
            $content[]  = '----------------------';
            $content[]  = '';
            $content[]  = '**URL:** ' . $this->info->url;
            $content[]  = '**Métodos:** ' . $this->info->methods;
        }

        $content[]  = '';
        $content[]  = '';
        $content[]  = 'Descrição';
        $content[]  = '---------';
        $content[]  = '';
        $content[]  = $this->desc;

        if ( $this->params )
        {
            $content[]  = '';
            $content[]  = '';
            $content[]  = 'Parâmetros da funcionalidade';
            $content[]  = '----------------------------';
            $content[]  = '';
            $i          = 0;

            foreach ( $this->params as $name => $param )
            {
                $i++;
                $content[] = $i . '. ' . $name . ' - ' . $param;
            }
        }

        if ( $this->workflow )
        {
            $content[]  = '';
            $content[]  = '';
            $content[]  = 'Fluxo da Execução';
            $content[]  = '-----------------';
            $content[]  = '';
            $i          = 0;

            foreach ( $this->workflow as $step )
            {
                $i++;
                $content[] = $i . '. ' . $step;
            }
        }

        if ( $this->rules )
        {
            $content[]  = '';
            $content[]  = '';
            $content[]  = 'Regras de Negócio';
            $content[]  = '-----------------';
            $content[]  = '';

            foreach ( $this->rules as $i => $rule )
                $content[] = ( $i + 1 ) . '. ' . $rule;
        }

        if ( $this->tests )
        {
            $content[]  = '';
            $content[]  = '';
            $content[]  = 'Casos de Teste';
            $content[]  = '--------------';
            $content[]  = '';

            foreach ( $this->tests as $test )
                $content[] = $test;
        }

        $content            = implode( PHP_EOL, $content );
        file_put_contents( $this->docfile, $content );
    }
}
