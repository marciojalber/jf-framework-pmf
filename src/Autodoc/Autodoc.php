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
     * Conteúdo da documentação.
     */
    protected $doc = [];

    /**
     * Módulos informados.
     */
    protected $modules = [];

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
        $this->lenbase  = strlen( DIR_SERVICES );
        $pathdoc        = DIR_BASE . '/doc';
        
        file_exists( $pathdoc ) || mkdir( $pathdoc );
        $this->clearDocPath( DIR_BASE . '/doc' );
        
        mkdir( DIR_BASE . '/doc/modules' );
        
        $this->parseServices( DIR_SERVICES );
        $this->saveContentFiles();
        $this->saveModules();
    }

    /**
     * Roda o Autodoc.
     */
    private function clearDocPath( $path )
    {
        $dir = new \FileSystemIterator( $path );

        foreach ( $dir as $item )
        {
            $subpath = $dir->getPathname();
            
            if ( $dir->isDir() )
            {
                $this->clearDocPath( $subpath );
                rmdir( $subpath );
                continue;
            }

            unlink( $subpath );
        }
    }

    /**
     * Roda o Autodoc.
     */
    private function parseServices( $path )
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

            $route      = substr( $subpath, $this->lenbase + 1 );
            $classname  = 'App\\Services\\' . substr( $route, 0, -4 );
            $docpath    = DIR_BASE . '/doc/modules/' . str_replace( '\\', '.', $route );
            $route      = '/' . str_replace( '\\', '/', $route );
            $route      = str_replace( '_', '-', $route );
            $route      = explode( '/', $route );
            array_pop( $route );
            $route      = implode( '/', $route );
            $route      = strtolower( $route );

            if ( $filename == 'autodoc-module' )
            {
                $total      = count( $this->modules ) + 1;
                $name       = 'module' . $total;
                $content    = trim( file_get_contents( $subpath ) );
                $content    = preg_split( '@[\n\r]+@', $content );
                $title      = array_shift( $content );
                $title      = preg_replace( '@\[|\]@', '', $title );
                $this->modules[ $name ] = (object) [
                    'name'          => $name,
                    'title'         => $title,
                    'route'         => $route,
                    'text'          => $content,
                    'totalServices' => 0,
                    'testsCover'    => 0,
                ];
                continue;
            }

            if ( substr( $filename, -10 ) == '__Test.php' )
            {
                $this->addDoc( $route );
                $this->doc[ $route ]->hasTests = 1;
                $this->doc[ $route ]->tests++;
                continue;
            }

            if ( $filename != 'Service.php' )
                continue;
            
            if ( $classname == 'App\\Services\\Bi\\Dashboard\\Desempenho\\Totalizar\\Service' )
                continue;

            $ref    = new \ReflectionClass( $classname );
            $attrs  = $ref->getAttributes();

            foreach ( $attrs as $attr )
            {
                $name = preg_replace( '@.*\\\@', '', $attr->getName() );

                if ( $name != 'userStory' )
                    continue;

                $comment        = $ref->getDocComment();
                $comment        = ClassDocParser::getDoc( $comment );
                $this->addDoc( $route, $comment->desc );
                $content        = &$this->doc[ $route ];

                $rules_path = str_replace( 'Service.php', 'Rules', $subpath );
                $has_rules  = 0;
                
                if ( file_exists( $rules_path ) )
                {
                    $dir_rules  = new \FilesystemIterator( $rules_path );
                    $rulemodel  = 'JF\\Domain\\Rule';

                    foreach ( $dir_rules as $i => $rule )
                    {
                        $rulepath   = $rule->getPathname();
                        $rulename   = $rule->getFilename();
                        
                        if ( substr( $rulepath, -10 ) != '__Rule.php' )
                            continue;
                        
                        $ruleclass  = preg_replace( '@Service$@', 'Rules\\' . $rulename, $classname );
                        $ruleclass  = substr( $ruleclass, 0, -4 );

                        if ( !is_subclass_of( $ruleclass, $rulemodel ) )
                            throw new Warning( "$ruleclass não estende à classe $rulemodel." );
                        
                        $has_rules  = 1;
                        $ruleref    = new \ReflectionClass( $ruleclass );
                        $docrule    = $ruleref->getDocComment();
                        $docrule    = ClassDocParser::getDoc( $docrule );
                        $ruleline   = preg_replace( '@[\r\n\t\s]+@m', ' ', $docrule->desc );
                        $content->rules[]  = $ruleline;
                    }
                }

                $this->doc[ $route ] = $content;
            }
        }
    }

    /**
     * Adiciona um serviço à documentação.
     */
    private function addDoc( $route, $desc = '' )
    {
        if ( isset( $this->doc[ $route ] ) )
            return;

        $this->doc[ $route ]    = (object) [
            'url'               => $route,
            'desc'              => $desc,
            'rules'             => [],
            'hasTests'          => 0,
            'tests'             => 0,
        ];
    }

    /**
     * Salva os arquivos com a documentação.
     */
    private function saveContentFiles()
    {
        foreach ( $this->modules as $name => $module )
            mkdir( DIR_BASE . '/doc/modules/' . $name );

        $tot_modules = count( $this->modules );

        foreach ( $this->doc as $path => $content )
        {
            $modpath    = DIR_BASE . '/doc/modules';
            $index      = $tot_modules;
            $discount   = 0;

            foreach ( array_reverse( $this->modules ) as $name => $module )
            {
                if ( !str_starts_with( $path, $module->route ) )
                    continue;

                $module->totalServices++;
                $module->testsCover     += $content->hasTests;
                $discount               = strlen( $module->route );
                $modpath                .= '/' . $name;
                break;
            }

            // $modpath .= '/' . substr( str_replace( '/', '-', substr( $path, 1 ) ), $discount );
            $modpath .= '/' . str_replace( '/', '-', substr( $path, 1 ) ) . '.json';
            file_put_contents( $modpath, $this->jsonEncode( $content ) );
        }
    }

    /**
     * Salva a lista de módulos.
     */
    private function saveModules()
    {
        $content    = $this->jsonEncode( $this->modules );
        $file       = DIR_BASE . '/doc/modules-list.json';
        
        file_put_contents( $file, $content );
    }

    /**
     * Codifica o conteúdo em formato JSON.
     */
    private function jsonEncode( $content )
    {
        return json_encode(
            $content,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
    }
}
