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
        
        $this->clearDocPath( DIR_BASE . '/doc' );
        
        mkdir( DIR_BASE . '/doc/modules' );
        
        $this->parseServices( DIR_SERVICES );
        $this->saveModules();
        $this->saveContentFiles();
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
            $route      = $filename == 'module'
                ? substr( $route, 0, -7 )
                : substr( $route, 0, -12 );
            $docpath    = DIR_BASE . '/doc/modules/' . str_replace( '\\', '.', $route );
            $route      = '/' . str_replace( '\\', '/', $route );
            $route      = str_replace( '_', '-', $route );
            $route      = strtolower( $route );

            if ( $filename == 'module' )
            {
                $total  = count( $this->modules ) + 1;
                $name   = 'module' . $total;
                $this->modules[ $name ] = [$route, file_get_contents( $subpath )];
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

                $comment    = $ref->getDocComment();
                $comment    = ClassDocParser::getDoc( $comment );
                $content    = [];
                $content[]  = "URL    : $route";
                $content[]  = 'DESC   : '. $comment->desc;

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
                        $content[]  = !$i
                            ? 'REGRAS : - ' . $ruleline
                            : '       - ' . $ruleline;
                    }
                }

                if ( !$has_rules )
                    $content[]      = 'Nenhuma regra de negócio definida.';

                $content                = implode( PHP_EOL, $content );
                $this->doc[ $route ]    = $content;
            }
        }
    }

    /**
     * Salva a lista de módulos.
     */
    private function saveModules()
    {
        $content = json_encode( $this->modules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        file_put_contents( DIR_BASE . '/doc/modules-list.json', $content );

        foreach ( $this->modules as $name => $module )
            mkdir( DIR_BASE . '/doc/modules/' . $name );
    }

    /**
     * Salva os arquivos com a documentação.
     */
    private function saveContentFiles()
    {
        $tot_modules = count( $this->modules );

        foreach ( $this->doc as $path => $content )
        {
            $modpath    = DIR_BASE . '/doc/modules';
            $index      = $tot_modules;
            $discount   = 0;

            foreach ( array_reverse( $this->modules ) as $name => $module )
            {
                if ( !str_starts_with( $path, $module[0] ) )
                    continue;

                $discount = strlen( $module[0] );
                $modpath .= '/' . $name;
                break;
            }

            // $modpath .= '/' . substr( str_replace( '/', '-', substr( $path, 1 ) ), $discount );
            $modpath .= '/' . str_replace( '/', '-', substr( $path, 1 ) );
            file_put_contents( $modpath, $content );
        }
    }
}
