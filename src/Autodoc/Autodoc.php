<?php

namespace JF\Autodoc;

use JF\Doc\ClassDocParser;
use JF\Exceptions\WarningException as Warning;

/**
 * Produz a documentação da aplicação.
 */
class Autodoc extends \StdClass
{
    /**
     * Conteúdo do documento.
     */
    protected $doc = [];

    /**
     * Inicia uma instância do Autodoc.
     */
    public static function init()
    {
        return new self();
    }

    /**
     * Roda o Autodoc.
     */
    public function run()
    {
        $this->lenbase = strlen( DIR_SERVICES );
        $this->parseServices( DIR_SERVICES );

        $docfile    = DIR_SERVICES . '/user-stories.jf';
        $separator  = str_repeat( '=', 100 );
        $content    = implode( PHP_EOL . PHP_EOL . $separator . PHP_EOL . PHP_EOL, $this->doc );
        file_put_contents( $docfile, $content );
    }

    /**
     * Roda o Autodoc.
     */
    public function parseServices( $path )
    {
        $dir = new \FileSystemIterator( $path );

        foreach ( $dir as $item )
        {
            $subpath    = $item->getPathname();
            $filename   = $item->getFilename();
            
            if ( $item->isDir() )
            {
                $this->parseServices( $subpath );
                continue;
            }

            if ( $filename != 'Service.php' )
                continue;
            
            $route      = substr( $subpath, $this->lenbase + 1, -4 );
            $classname  = 'App\\Services\\' . $route;
            $route      = '#' . substr( $route, 0, -8 );
            $route      = str_replace( '\\', '.', $route );
            $route      = str_replace( '_', '-', $route );
            $route      = strtoupper( $route );

            if ( $classname == 'App\\Services\\Bi\\Dashboard\\Desempenho\\Totalizar\\Service' )
                continue;

            $ref    = new \ReflectionClass( $classname );
            $attrs  = $ref->getAttributes();

            foreach ( $attrs as $attr )
            {
                $name = preg_replace( '@.*\\\@', '', $attr->getName() );

                if ( $name != 'userStory' )
                    continue;

                $comment    = $ref->getDocComment();
                $comment    = ClassDocParser::getDoc( $comment );
                $content    = [];
                $content[]  = "[$route]";
                $content[]  = '';
                $content[]  = preg_replace( '@^@m', '   ', $comment->desc );
                $content[]  = '';
                $content[]  = "   REGRAS DE NEGÓCIO";

                $rules_path = str_replace( 'Service.php', 'Rules', $subpath );
                $has_rules  = 0;
                
                if ( file_exists( $rules_path ) )
                {
                    $dir_rules  = new \FilesystemIterator( $rules_path );
                    $rulemodel  = 'JF\\Domain\\Rule';

                    foreach ( $dir_rules as $rule )
                    {
                        $rulepath   = $rule->getPathname();
                        $rulename   = $rule->getFilename();
                        
                        if ( substr( $rulepath, -10 ) != '__Rule.php' )
                            continue;
                        
                        $ruleclass  = preg_replace( '@Service$@', 'Rules\\' . $rulename, $classname );
                        $ruleclass  = substr( $ruleclass, 0, -4 );

                        if ( !is_subclass_of( $ruleclass, $rulemodel ) )
                            throw new Warning( "$ruleclass não estende à classe $rulemodel." );
                        
                        $has_rules  = 0;
                        $ruleref    = new \ReflectionClass( $ruleclass );
                        $docrule    = $ruleref->getDocComment();
                        $docrule    = ClassDocParser::getDoc( $docrule );
                        $content[]  = '   - ' . preg_replace( '@[\r\n\t\s]+@m', ' ', $docrule->desc );
                    }
                }

                if ( !$has_rules )
                    $content[]  = '   Nenhuma regra de negócio definida.';

                $content        = implode( PHP_EOL, $content );
                $this->doc[]    = $content;
                $filectn        = substr( $subpath, 0, -11 ) . 'user-story.jf';
                
                file_put_contents( $filectn, $content );
            }
        }
    }
}
