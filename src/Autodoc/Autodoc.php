<?php

namespace JF\Autodoc;

use JF\Doc\ClassDocParser;
use JF\Exceptions\WarningException as Warning;

/**
 * Produz a documentação da aplicação.
 */
class Autodoc extends \StdClass
{
    use PlantUML__Trait;

    /**
     * Lista dos esquemas e seus respectivos models.
     */
    protected $schemas = [];

    /**
     * Lista dos serviços encontrados.
     */
    protected $services = [];

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
        $this->config();
        $this->clearPaths();
        $this->parseDBModels();
        $this->parseServices( DIR_SERVICES );
        $this->sendLog( 'Conclusão dos services', 1 );
        $this->saveContentFiles();
        $this->saveModules();
        $this->sendLog();
    }

    /**
     * Define as configurações iniciais.
     */
    private function config()
    {
        $this->start    = new \DateTime();
        $this->last     = $this->start;
        $this->lenbase  = strlen( DIR_BASE ) + 1;
        $this->lenserv  = strlen( DIR_SERVICES );
        $paths          = [
            DIR_BASE . '/doc',
            DIR_BASE . '/doc/dbmodels',
            DIR_BASE . '/doc/services',
            DIR_BASE . '/doc/services/modules',
        ];
        
        foreach ( $paths as $path )
            file_exists( $path ) || mkdir( $path );

        echo PHP_EOL;
        echo '-- INI --' . PHP_EOL;
        echo 'Iniciado em : ' . $this->last->format( 'Y-m-d' ) . PHP_EOL;
        echo 'Iniciado às : ' . $this->last->format( 'H:i:s' ) . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Limpa as pastas.
     */
    private function clearPaths()
    {
        $this->clearDocPath( DIR_BASE . '/doc/dbmodels' );
        $this->clearDocPath( DIR_BASE . '/doc/services/modules' );
        $this->sendLog( 'Limpeza das pastas realizada', 1 );
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
    private function parseDBModels()
    {
        $dir            = new \FileSystemIterator( DIR_APP . '/DTO' );
        $model_sufix    = '__Model.php';
        $model_len      = strlen( $model_sufix );
        $this->schemas  = (object) [];

        foreach ( $dir as $schema )
        {
            if ( !$schema->isDir() )
                continue;

            $scname         = $schema->getFilename();
            $content        = "@startuml
!define DARKBLUE
!includeurl https://raw.githubusercontent.com/Drakemor/RedDress-PlantUML/master/style.puml" . PHP_EOL . PHP_EOL;
            $schema         = new \FileSystemIterator( $schema );
            $entities       = [];

            foreach ( $schema as $dto )
            {
                if ( !$dto->isDir() )
                    continue;
                
                $dto         = new \FileSystemIterator( $dto );

                foreach ( $dto as $item )
                {
                    $filename   = $item->getFilename();
                    $pathname   = $item->getPathname();

                    if ( substr( $filename, -$model_len ) != $model_sufix )
                        continue;

                    if ( substr( $filename, 0, 1 ) == '_' )
                        continue;
                    
                    $entities[] = $this->parseEntity( $filename, $pathname );
                }
            }

            $content    .= $entities
                ? implode( PHP_EOL, $entities ) . PHP_EOL
                : '';
            $content    .= '@enduml';
            $filedbmodel = DIR_BASE . '/doc/dbmodels/' . $scname . '.svg';
            
            $encoded    = $this->encode( $content );
            $url        = "https://www.plantuml.com/plantuml/svg/{$encoded}";
            $content     = file_get_contents( $url );

            file_put_contents( $filedbmodel, $content );
            $this->sendLog( 'Captura do DBModel ' . $scname );
        }

        $this->sendLog( 'Conclusão da captura dos DBModel', 1 );
    }

    /**
     * Captura os dados da entidade.
     */
    private function parseEntity( $filename, $pathname )
    {
        $entityname     = substr( $filename, 0, -11 );
        $classname      = substr( $pathname, $this->lenbase, -4 );
        $classname      = str_replace( '/', '\\', $classname );
        $ref            = new \ReflectionClass( $classname );
        $props          = $ref->getProperties();
        $cols           = [];
        $props_parse    = [ 'type', 'desc' ];

        $comment        = $ref->getDocComment();
        $comment        = ClassDocParser::getDoc( $comment );
        $entitydesc     = $comment->desc;
        $methods        = $ref->getMethods();
        $methods        = $ref->getMethods();

        foreach ( $methods as $i => &$method )
        {
            if ( $method->class == 'JF\\DB\\DTO' )
            {
                unset( $methods[ $i ] );
                continue;
            }

            $comment    = $method->getDocComment();
            $comment    = ClassDocParser::getDoc( $comment );
            $ctnmethod  = '  ' . $comment->desc . PHP_EOL;
            $returntype = (string) $method->getReturnType();
            $ctnmethod  .= $method->isPublic()
                ? '  + '
                : '  - ';
            $ctnmethod  .= $method->name . '()';
            $ctnmethod  .= $returntype
                ? ': ' . $returntype
                : '';
            $method     = $ctnmethod;
        }

        foreach ( $props as $prop )
        {
            if ( !$prop->isPublic() || $prop->isStatic() )
                continue;
            
            $colname    = $prop->name;
            $coltype    = 'TYPE';
            $coldesc    = 'DESC';
            $attrs      = $prop->getAttributes();
            $pk         = '';
            
            if ( $attrs )
            {
                foreach ( $attrs as $attr )
                {
                    $name       = preg_replace( '@.*\\\@', '', $attr->getName() );
                    $val        = $attr->getArguments()[0] ?? null;

                    if ( $name == 'priKey' )
                        $pk = ' <<PK>>';
                        continue;

                    if ( !in_array( $name, $props_parse ) )
                        continue;

                    ${'col' . $name} = $val;
                }
            }

            $col        = "  + $colname: $coltype \"$coldesc\"$pk";
            $cols[]     = $col;
        }

        $content    = "entity \"$entityname\" as $entityname {" . PHP_EOL;
        $content    .= '  ' . $entitydesc . PHP_EOL;
        $content    .= '  --' . PHP_EOL;
        $content    .= implode( PHP_EOL, $cols ) . PHP_EOL;
        $content    .= $methods
            ? '  --' . PHP_EOL . implode( PHP_EOL . '  --' . PHP_EOL, $methods ) . PHP_EOL
            : '';
        $content    .= "}" . PHP_EOL;

        return $content;
    }

    /**
     * Roda o Autodoc.
     */
    private function parseServices( $path )
    {
        $dir            = new \FileSystemIterator( $path );

        foreach ( $dir as $item )
        {
            $subpath    = $item->getPathname();
            $filename   = $item->getFilename();
            
            if ( $item->isDir() )
            {
                $this->parseServices( $subpath );
                continue;
            }

            $route      = substr( $subpath, $this->lenserv + 1 );
            $classname  = 'App\\Services\\' . substr( $route, 0, -4 );
            $docpath    = DIR_BASE . '/doc/services/modules/' . str_replace( '\\', '.', $route );
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
                    'name'              => $name,
                    'title'             => $title,
                    'route'             => $route,
                    'text'              => $content,
                    'totServices'       => 0,
                    'servicesWithDoc'   => 0,
                    'docCoverage'       => 0,
                    'servicesWithTests' => 0,
                    'testsCoverage'     => 0,
                    'totTodos'          => 0,
                ];
                continue;
            }

            if ( substr( $filename, -10 ) == '__Test.php' )
            {
                $this->addDoc( $route );
                $this->doc[ $route ]->hasTests = 1;
                $this->doc[ $route ]->totTests++;
                continue;
            }

            if ( $filename != 'Service.php' )
                continue;
            
            if ( $classname == 'App\\Services\\Bi\\Dashboard\\Desempenho\\Totalizar\\Service' )
                continue;


            $this->services[]   = $route;
            $ref                = new \ReflectionClass( $classname );
            $attrs              = $ref->getAttributes();

            foreach ( $attrs as $attr )
            {
                $name = preg_replace( '@.*\\\@', '', $attr->getName() );

                if ( $name != 'userStory' )
                    continue;

                $comment        = $ref->getDocComment();
                $comment        = ClassDocParser::getDoc( $comment );
                $this->addDoc( $route );
                $content        = &$this->doc[ $route ];
                $content->desc  = $comment->desc;
                $content->todos = $comment->tags[ 'todo' ] ?? [];
                $rules_path     = str_replace( 'Service.php', 'Rules', $subpath );
                $dochelp        = $content->dochelp;

                if ( $dochelp && method_exists( $dochelp, 'analyseService' ) )
                    $dochelp->analyseService( $ref );
                
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
                        
                        $ruleref    = new \ReflectionClass( $ruleclass );
                        $docrule    = $ruleref->getDocComment();
                        $docrule    = ClassDocParser::getDoc( $docrule );
                        $ruleline   = preg_replace( '@[\r\n\t\s]+@m', ' ', $docrule->desc );
                        $content->rules[]  = $ruleline;

                        if ( $dochelp && method_exists( $dochelp, 'analyseRule') )
                            $dochelp->analyseRule( $ruleref );
                    }
                }

                $this->doc[ $route ] = $content;
            }
        }
    }

    /**
     * Adiciona um serviço à documentação.
     */
    private function addDoc( $route )
    {
        if ( isset( $this->doc[ $route ] ) )
            return;

        $dochelp_class          = 'App\\Doc';
        $this->doc[ $route ]    = (object) [
            'url'               => $route,
            'desc'              => '',
            'rules'             => [],
            'hasTests'          => 0,
            'totTests'          => 0,
            'todos'             => [],
            'dochelp'           => class_exists( $dochelp_class )
                ? new $dochelp_class()
                : null,
        ];
    }

    /**
     * Salva os arquivos com a documentação.
     */
    private function saveContentFiles()
    {
        $totals                 = (object) [
            'totServices'       => count( $this->services ),
            'servicesWithDoc'   => 0,
            'docCoverage'       => 0,
            'servicesWithTests' => 0,
            'testsCoverage'     => 0,
            'totTodos'          => 0,
        ];

        foreach ( $this->modules as $name => $module )
            mkdir( DIR_BASE . '/doc/services/modules/' . $name );

        $tot_modules = count( $this->modules );

        foreach ( $this->services as $path )
        {
            foreach ( array_reverse( $this->modules ) as $name => $module )
            {
                if ( !str_starts_with( $path, $module->route ) )
                    continue;

                $module->totServices++;
                break;
            }
        }

        foreach ( $this->doc as $path => $content )
        {
            $tot_todos                  = count( $content->todos );
            $modpath                    = DIR_BASE . '/doc/services/modules';
            $index                      = $tot_modules;
            $discount                   = 0;
            $totals->servicesWithTests  += $content->hasTests;
            $totals->totTodos           += $tot_todos;
            $totals->servicesWithDoc++;

            foreach ( array_reverse( $this->modules ) as $name => $module )
            {
                if ( !str_starts_with( $path, $module->route ) )
                    continue;

                $module->servicesWithDoc++;
                $module->docCoverage      = round(
                    $module->servicesWithDoc * 100 / $module->totServices,
                    1
                );

                $module->servicesWithTests  += $content->hasTests;
                $module->testsCoverage      = !$module->servicesWithTests
                    ? 0
                    : round(
                        $module->servicesWithTests * 100 / $module->servicesWithDoc,
                        1
                    );

                $module->totTodos           += $tot_todos;
                $discount                   = strlen( $module->route );
                $modpath                    .= '/' . $name;
                break;
            }

            if ( $content->dochelp )
            {
                $extra_service  = $content->dochelp->extraService();
                $extra_rules    = $content->dochelp->extraRules();

                array_walk( $extra_service, function( $val, $key ) use( $content ) {
                    $content->$key = $val;
                });

                array_walk( $extra_rules, function( $val, $key ) use( $content ) {
                    $content->rules[] = $val;
                });
            }

            // $modpath .= '/' . substr( str_replace( '/', '-', substr( $path, 1 ) ), $discount );
            $modpath .= '/' . str_replace( '/', '-', substr( $path, 1 ) ) . '.json';
            unset( $content->dochelp );
            file_put_contents( $modpath, $this->jsonEncode( $content ) );
        }

        $totals->docCoverage    = $totals->servicesWithDoc * 100 / $totals->totServices;
        $totals->docCoverage    = round( $totals->docCoverage, 1 );
        $totals->testsCoverage  = $totals->servicesWithTests * 100 / $totals->servicesWithDoc;
        $totals->testsCoverage  = round( $totals->testsCoverage, 1 );
        $content    = $this->jsonEncode( $totals );
        $file       = DIR_BASE . '/doc/services/totals.json';
        file_put_contents( $file, $content );
    }

    /**
     * Salva a lista de módulos.
     */
    private function saveModules()
    {
        $content    = $this->jsonEncode( $this->modules );
        $file       = DIR_BASE . '/doc/services/modules-list.json';
        
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

    /**
     * Envia um log pra tela.
     */
    private function sendLog( $content = '', $sep = 0 )
    {
        $now        = new \DateTime();
        $start      = $this->last;
        $fim        = 0;

        if ( !$content )
        {
            $start      = $this->start;
            $content    = 'Conclusão do script';
            $fim        = 1;
        }

        $time       = $now->format( 'H:i:s' );
        $diff       = $now->diff( $start );
        $this->last = $now;
        $hr         = substr( '0' . strval( $diff->h ), -2 );
        $min        = substr( '0' . strval( $diff->i ), -2 );
        $seg        = substr( '0' . strval( $diff->s ), -2 );
        $mili       = round( $diff->f, 3 );
        $mili       = strval( $mili );
        $mili       = substr( $mili, 2 );
        $mili       = str_pad( $mili, 3, '0' );
        $last       = "$hr:$min:$seg.$mili";
        echo "$content: $time [$last]". PHP_EOL;
        
        if ( $sep )
            echo PHP_EOL;

        if ( $fim )
            echo "-- FIM --". PHP_EOL;
    }
}
