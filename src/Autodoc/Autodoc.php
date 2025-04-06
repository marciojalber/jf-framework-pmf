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
     * Lista dos contextos a processar a documentação.
     */
    protected $contexts         = [];

    /**
     * Não excluir os arquivos, apenas sobrescrever.
     */
    protected $onlyReplaceFiles = 0;

    /**
     * Lista dos serviços encontrados.
     */
    protected $services         = [];

    /**
     * Lista as rotinas encontradas.
     */
    protected $routines         = [];

    /**
     * Conteúdo da documentação dos serviços.
     */
    protected $doc              = [];

    /**
     * Módulos dos serviços.
     */
    protected $modules          = [];

    /**
     * Conteúdo da documentação das páginas.
     */
    protected $pages            = [];

    /**
     * Módulos das páginas.
     */
    protected $pageModules      = [];

    /**
     * Inicia uma instância do Autodoc.
     */
    public function __construct()
    {
        $this->lenbase  = strlen( DIR_BASE ) + 1;
        $this->lenserv  = strlen( DIR_SERVICES );
        $this->lenviews = strlen( DIR_VIEWS );
    }

    /**
     * Inicia uma instância do Autodoc.
     */
    public static function init( $args )
    {
        $inst = new self();
        
        if ( property_exists( $args, '-h' ) )
            return $inst->help();
        
        if ( property_exists( $args, '-r' ) )
            $inst->onlyReplaceFiles = 1;
        
        if ( !empty( $args->{'-c'} ) )
            $inst->contexts = preg_split( '@ *, *@', $args->{'-c'} );

        if ( empty( $inst->contexts ) )
        {
            echo PHP_EOL;
            echo "Nenhum contexto informado." . PHP_EOL;
            echo "Para mais informações, use \e[33mphp cmd\autodoc.php -h\e[0m.";
            echo PHP_EOL;
            exit;
        }

        return $inst;
    }

    /**
     * Roda o Autodoc.
     */
    public function run()
    {
        $this->config();
        $this->clearPaths();

        if ( in_array( 'models', $this->contexts ) )
            $this->parseModels();

        if ( in_array( 'pages', $this->contexts ) )
        {
            $total = $this->parsePages( DIR_VIEWS );
            $this->saveContentPages();
            $this->savePageModules();
            $this->savePageTotals( $total );
            $this->sendLog( 'Conclusão da captura das páginas' );
        }

        if ( in_array( 'routines', $this->contexts ) )
        {
            $this->parseRoutines( DIR_ROUTINES );
            $this->saveRoutines();
        }

        if ( in_array( 'domain', $this->contexts ) )
        {
            $this->parseServices( DIR_SERVICES );
            $this->sendLog( 'Conclusão dos services' );
            $this->saveContentFiles();
            $this->saveDomain();
            $this->saveModules();
        }
        
        $this->sendLog( null, 1 );
    }

    /**
     * Define as configurações iniciais.
     */
    private function config()
    {
        $this->start    = new \DateTime();
        $this->last     = $this->start;
        $paths          = [
            DIR_BASE . '/doc',
            DIR_BASE . '/doc/models',
            DIR_BASE . '/doc/pages',
            DIR_BASE . '/doc/pages/modules',
            DIR_BASE . '/doc/routines',
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
        if ( $this->onlyReplaceFiles )
            return;

        if ( in_array( 'domain', $this->contexts ) )
            $this->clearDocPath( DIR_BASE . '/doc/services/modules' );

        if ( in_array( 'models', $this->contexts ) )
            $this->clearDocPath( DIR_BASE . '/doc/models' );

        if ( in_array( 'pages', $this->contexts ) )
            $this->clearDocPath( DIR_BASE . '/doc/pages/modules' );

        if ( in_array( 'routines', $this->contexts ) )
            $this->clearDocPath( DIR_BASE . '/doc/routines' );

        $this->sendLog( 'Limpeza das pastas realizada' );
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
     * Captura os modelos representativos das tabelas.
     */
    private function parseModels()
    {
        $dir            = new \FileSystemIterator( DIR_APP . '/DTO' );

        foreach ( $dir as $schema )
        {
            if ( !$schema->isDir() )
                continue;

            $scname         = $schema->getFilename();
            $this->parseSchemaPath( $scname );
        }

        $this->sendLog( 'Conclusão da modelagem dos DBModel' );
    }

    /**
     * Captura os modelos representativos de um esquema de conexão específico.
     */
    public function parseSchemaPath( $scname )
    {
        $schema_path    = DIR_APP . '/DTO/' . $scname;
        $schema_dir     = new \FilesystemIterator( $schema_path );
        $model_sufix    = '__Model.php';
        $model_len      = strlen( $model_sufix );
        $groups         = [];
        $binds          = [];
        $uml            = "";
        /*
        $puml           = "@startuml
!define DARKBLUE
!includeurl https://raw.githubusercontent.com/Drakemor/RedDress-PlantUML/master/style.puml" . PHP_EOL . PHP_EOL;
        */
        $entities       = [];

        foreach ( $schema_dir as $dto )
        {
            if ( !$dto->isDir() )
                continue;
            
            $dto            = new \FileSystemIterator( $dto );

            foreach ( $dto as $item )
            {
                $filename   = $item->getFilename();
                $pathname   = $item->getPathname();

                if ( substr( $filename, -$model_len ) != $model_sufix )
                    continue;

                if ( substr( $filename, 0, 1 ) == '_' )
                    continue;
                
                // $entity     = $this->parseEntityPlantUml( $filename, $pathname );
                $entity                 = $this->parseEntityGleekIO(
                    $filename,
                    $pathname,
                    $groups
                );
                $tbname                 = $entity->tbname;
                $entities[ $tbname ]    = $entity->content;
                $binds                  = array_merge( $binds, $entity->binds );
            }
        }

        foreach ( $groups as $group => $items )
        {
            $uml    .= '/g ' . $group . PHP_EOL;

            foreach ( $items as $entity )
            {
                $uml .= preg_replace( '@^@m', '    ', $entities[ $entity ] ) . PHP_EOL;
                unset( $entities[ $entity ] );
            }
        }

        $uml        .= $entities
            ? implode( PHP_EOL, $entities ) . PHP_EOL
            : '';
        $uml        .= $binds
            ? implode( PHP_EOL, $binds ) . PHP_EOL . PHP_EOL
            : '';

        $uml        = trim( $uml ) . PHP_EOL;
        $content    = null;
        /*
        $uml        .= '@enduml';
        $content    = $uml;
        */
        
        /*
        $encoded    = $this->encode( $content );
        $url        = "https://www.plantuml.com/plantuml/svg/{$encoded}";
        $content    = file_get_contents( $url );
        $filepath   = $content
            ? DIR_BASE . '/doc/models/' . $scname . '.svg'
            : DIR_BASE . '/doc/models/' . $scname . '.uml';
        */
        $filepath   = DIR_BASE . '/doc/models/' . $scname . '.uml';

        $content
            ? file_put_contents( $filepath, $content )
            : file_put_contents( $filepath, $uml );
    }

    /**
     * Captura os dados da entidade para o Gleek.io.
     */
    private function parseEntityGleekIO( $filename, $pathname, &$groups )
    {
        $entityname     = substr( $filename, 0, -11 );
        $classname      = substr( $pathname, $this->lenbase, -4 );
        $classname      = str_replace( '/', '\\', $classname );
        $ref            = new \ReflectionClass( $classname );
        $attrs          = $ref->getAttributes();
        $props          = $ref->getProperties();
        $methods        = $ref->getMethods();
        $cols           = [];
        $binds          = [];
        $props_parse    = [ 'type', 'desc' ];

        $comment        = $ref->getDocComment();
        $comment        = ClassDocParser::getDoc( $comment );
        $entitydesc     = $comment->desc;
        $tbname         = $entityname;
        $doc_group      = null;

        foreach ( $attrs as $attr )
        {
            $name   = preg_replace( '@.*\\\@', '', $attr->getName() );
            
            if ( $name != 'table' )
                continue;

            $tbname = $attr->getArguments()[0];
            break;
        }

        $filetrait      = str_replace( '__Model', '__Tasks', $pathname );
        $traitname      = str_replace( '__Model', '__Tasks', $classname );

        if ( file_exists( $filetrait ) )
        {
            $ref_trait      = new \ReflectionClass( $traitname );
            $trait_attrs    = $ref_trait->getAttributes();

            foreach ( $trait_attrs as $attr )
            {
                $name       = preg_replace( '@.*\\\@', '', $attr->getName() );
                
                if ( $name != 'docGroup' )
                    continue;

                $doc_group  = $attr->getArguments()[0];
                break;
            }

            if ( $doc_group && !isset( $groups[ $doc_group ] ) )
                $groups[ $doc_group ] = [];

            if ( $doc_group )
                $groups[ $doc_group ][] = $tbname;
        }

        foreach ( $props as $prop )
        {
            if ( $prop->isStatic() && $prop->isProtected() && $prop->getName() == 'fks' )
            {
                $fks = $prop->getDefaultValue();

                foreach ( $fks as $fk => $ref )
                    $binds[] = "$tbname  - $fk:{$ref[ 'col' ]} -> {$ref[ 'table' ]}";
            }

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
                    {
                        $pk = ' <PK>';
                        continue;
                    }

                    if ( !in_array( $name, $props_parse ) )
                        continue;

                    ${'col' . $name} = $val;
                }
            }

            $col        = "    $colname: $coltype$pk";
            $cols[]     = $col;
        }

        foreach ( $methods as $i => &$method )
        {
            if ( $method->class == 'JF\\DB\\DTO' )
            {
                unset( $methods[ $i ] );
                continue;
            }

            $returntype = (string) $method->getReturnType();
            $ctnmethod  = $method->isPublic()
                ? '    + '
                : '    - ';
            $ctnmethod  .= $method->name . '()';
            $ctnmethod  .= $returntype
                ? ': ' . $returntype
                : '';
            $method     = $ctnmethod;
        }

        $content    = $tbname . ':db' . PHP_EOL;
        $content    .= implode( PHP_EOL, $cols ) . PHP_EOL;
        $content    .= $methods
            ? implode( PHP_EOL, $methods ) . PHP_EOL
            : '';

        $res            = (object) [
            'tbname'    => $tbname,
            'content'   => $content,
            'binds'     => $binds,
        ];

        return $res;
    }

    /**
     * Captura os dados da entidade para o PlantUML.
     */
    private function parseEntityPlantUml( $filename, $pathname )
    {
        $entityname     = substr( $filename, 0, -11 );
        $classname      = substr( $pathname, $this->lenbase, -4 );
        $classname      = str_replace( '/', '\\', $classname );
        $ref            = new \ReflectionClass( $classname );
        $attrs          = $ref->getAttributes();
        $props          = $ref->getProperties();
        $methods        = $ref->getMethods();
        $cols           = [];
        $binds          = [];
        $props_parse    = [ 'type', 'desc' ];

        $comment        = $ref->getDocComment();
        $comment        = ClassDocParser::getDoc( $comment );
        $entitydesc     = $comment->desc;
        $tbname         = $entityname;

        foreach ( $attrs as $attr )
        {
            $name   = preg_replace( '@.*\\\@', '', $attr->getName() );
            if ( $name != 'table' )
                continue;

            $tbname = $attr->getArguments()[0];
            break;
        }

        foreach ( $props as $prop )
        {
            if ( $prop->isStatic() && $prop->isProtected() && $prop->getName() == 'fks' )
            {
                $fks = $prop->getDefaultValue();

                foreach ( $fks as $fk => $ref )
                    $binds[] = $tbname . ' }o--|| ' . $ref[ 'table' ];
            }

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
                    {
                        $pk = ' <<PK>>';
                        continue;
                    }

                    if ( !in_array( $name, $props_parse ) )
                        continue;

                    ${'col' . $name} = $val;
                }
            }

            $col        = "  + $colname: $coltype \"$coldesc\"$pk";
            $cols[]     = $col;
        }

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

        $content    = "entity \"$entityname\" as $tbname {" . PHP_EOL;
        $content    .= '  ' . $entitydesc . PHP_EOL;
        $content    .= '  --' . PHP_EOL;
        $content    .= implode( PHP_EOL, $cols ) . PHP_EOL;
        $content    .= $methods
            ? '  --' . PHP_EOL . implode( PHP_EOL . '  --' . PHP_EOL, $methods ) . PHP_EOL
            : '';
        $content    .= "}" . PHP_EOL;

        return (object) [
            'tbname'    => $tbname,
            'content'   => $content,
            'binds'     => $binds,
        ];
    }

    /**
     * Captura as rotinas de execução em background.
     */
    private function parsePages( $path )
    {
        static $totals      = null;
        static $basepages   = DIR_BASE . '/doc/pages/';

        if ( !$totals )
        {
            $totals      = (object) [
                'totalModules'  => 0,
                'pages'         => 0,
                'helps'         => 0,
                'autodoc'       => 0,
            ];
        }

        $dir            = new \FileSystemIterator( $path );

        foreach ( $dir as $item )
        {
            $subpath    = $item->getPathname();
            $filename   = $item->getFilename();
            $route      = substr( $subpath, $this->lenviews + 1 );
            $route      = str_replace( '\\', '/', $route );
            
            if ( $item->isDir() )
            {
                $this->parsePages( $subpath );
                continue;
            }

            if ( $filename == 'module.ini' )
            {
                $route  = substr( $route, 0, -15 );
                $total  = count( $this->pageModules ) + 1;
                $name   = 'module' . $total;
                $path   = $basepages . '/modules/' . $name;

                if ( !file_exists( $path ) )
                    mkdir( $path );

                $content_ini        = json_decode( json_encode( parse_ini_file( $subpath ) ) );
                $this->pageModules[ $name ] = (object) [
                    'name'          => $name,
                    'title'         => $content_ini,
                    'route'         => $route,
                    'totalPages'    => 0,
                ];
                continue;
            }

            if ( $filename != 'view.php' )
                continue;

            ++$totals->pages;
            $subpathbase            = dirname( $subpath );
            $pagehelp               = $subpathbase . '/pagehelp.php';
            $pageini                = $subpathbase . '/view.ini';
            $pagedoc                = $subpathbase . '/_view.autodoc';
            $namesdoc               = $subpathbase . '/_view.docnames';
            $ini                    = json_decode( json_encode( parse_ini_file( $pageini, 1 ) ) );
            $content                = (object) [];
            $content->route         = substr( $route, 0, -9 );
            $content->help          = '';
            $content->parts         = '';
            $content->names         = (object) [];

            if ( file_exists( $pagehelp ) )
            {
                $content->help      = file_get_contents( $pagehelp );
                ++$totals->helps;
            }

            if ( file_exists( $pagedoc ) )
            {
                $content->parts     = file_get_contents( $pagedoc );
                ++$totals->autodoc;
            }

            if ( file_exists( $namesdoc ) )
                $content->names     = json_decode( file_get_contents( $namesdoc ) );

            $content->permissions   = isset( $ini->PERMISSIONS )
                ? $ini->PERMISSIONS
                : [];
            $content->data          = isset( $ini->DATA )
                ? $ini->DATA
                : [];
            
            $route                  = substr( $route, 0, -9 );
            $this->pages[ $route ]  = $content;
        }

        return $totals;
    }

    /**
     * Salva a documentação das página.
     */
    private function saveContentPages()
    {
        static $docpages    = DIR_BASE . '/doc/pages';
        $tot_modules        = count( $this->pageModules );

        foreach ( $this->pages as $route => $content )
        {
            $route_file = str_replace( '/', '.', $route );
            $filetarget = "$docpages/modules/$route_file.json";
            $content    = $this->jsonEncode( $content );
            $mod_index  = 0;

            foreach ( array_reverse( $this->pageModules ) as $name => $module )
            {
                if ( !str_starts_with( $route, $module->route ) )
                    continue;

                ++$module->totalPages;
                $filetarget = "$docpages/modules/{$name}/$route_file.json";
                break;
            }

            file_put_contents( $filetarget, $content );
        }
    }

    /**
     * Salva os módulos de página.
     */
    private function savePageModules()
    {
        $basepages  = DIR_BASE . '/doc/pages/';
        $content    = $this->jsonEncode( $this->pageModules );
        $filename   = $basepages . 'modules-list.json';

        file_put_contents( $filename, $content );
    }

    /**
     * Salva os totais das páginas.
     */
    private function savePageTotals( $totals )
    {
        $totals->totalModules = count( $this->pageModules );
        $content    = $this->jsonEncode( $totals );
        $filename   = DIR_BASE . '/doc/pages/totals.json';
        
        file_put_contents( $filename, $content );
    }

    /**
     * Captura as rotinas de execução em background.
     */
    private function parseRoutines( $path )
    {
        $dir            = new \FileSystemIterator( $path );
        $sufix          = '__Routine.php';
        $lensufix       = strlen( $sufix );

        foreach ( $dir as $item )
        {
            $subpath    = $item->getPathname();
            $filename   = $item->getFilename();
            
            if ( $item->isDir() )
            {
                $this->parseRoutines( $subpath );
                continue;
            }

            if ( substr( $subpath, -$lensufix ) != $sufix )
                continue;

            $route          = substr( $subpath, $this->lenserv + 1 );
            $classname      = 'App\\Routines\\' . substr( $route, 0, -4 );
            $route          = substr( $route, 0, -$lensufix );
            $route          = str_replace( '\\', '/', $route );
            $ref            = new \ReflectionClass( $classname );
            $comment        = $ref->getDocComment();
            $props_class    = $ref->getProperties();
            $props          = [];
            $props_get      = [
                'active',
                'min',
                'hr',
                'date',
                'day',
                'month',
            ];
            
            foreach ( $props_class as $prop )
                if ( in_array( $prop->name, $props_get ) )
                    $props[ $prop->name ] = $prop->getDefaultValue();

            $this->routines[]   = array_merge( [
                'routine'       => $route,
                'desc'          => ClassDocParser::getDoc( $comment )->desc,
            ], $props );
        }
    }

    /**
     * Salva o conteúdo das rotinas.
     */
    private function saveRoutines()
    {
        $content    = $this->jsonEncode( $this->routines );
        $filename   = DIR_BASE . '/doc/routines/routines.json';
        file_put_contents( $filename, $content );
        $this->sendLog( 'Conclusão da captura das rotinas' );
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

            if ( $filename == 'module.ini' )
            {
                $total      = count( $this->modules ) + 1;
                $name       = 'module' . $total;
                $content    = json_decode( json_encode( file_get_contents( $subpath ) ) );
                $title      = preg_replace( '@\[|\]@', '', $title );
                $this->modules[ $name ] = (object) [
                    'name'              => $name,
                    'title'             => $content->name,
                    'route'             => $route,
                    'text'              => implode( PHP_EOL, $content->features ),
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
        {
            $path = DIR_BASE . '/doc/services/modules/' . $name;
            file_exists( $path ) || mkdir( DIR_BASE . '/doc/services/modules/' . $name );
        }

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
     * Salva a modelagem do domínio.
     */
    private function saveDomain()
    {
        $domain_uml = "@startuml
!define DARKBLUE
!includeurl https://raw.githubusercontent.com/Drakemor/RedDress-PlantUML/master/style.puml" . PHP_EOL . PHP_EOL;

        foreach ( $this->modules as $item )
        {
            $domain_uml .= PHP_EOL . "entity \"{$item->title}\" as $item->route {" . PHP_EOL;
            $domain_uml .= '  ' . implode( PHP_EOL . '  - ', $item->text ) . PHP_EOL . '}' . PHP_EOL;
        }

        $domain_uml .= PHP_EOL . '@enduml';
        $filepath   = DIR_BASE . '/doc/services/domain.svg';

        $encoded    = $this->encode( $domain_uml );
        $url        = "https://www.plantuml.com/plantuml/svg/{$encoded}";
        $domain_uml = file_get_contents( $url );
        
        if ( $domain_uml )
            file_put_contents( $filepath, $domain_uml );
        
        $this->sendLog( 'Modelagem do domínio concluída' );
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
    private function sendLog( $content = '', $in_line = 0 )
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
        
        if ( !$in_line )
            echo PHP_EOL;

        if ( $fim )
            echo "-- FIM --". PHP_EOL;
    }

    /**
     * Envia ajuda de uso da ferramenta para o cliente.
     */
    private function help()
    {
        $res = <<<RES

Ajuda de uso do JF-AUTODOC:
===========================

Este recurso cria documentação automática para a aplicação.
Modo de uso: \e[33mphp cmd/autodoc.php\e[0m [-r] -c:CONTEXTS

\e[33m-r\e[0m   ONLY REPLACE
     Sobrescreve os arquivos existentes com os novos gerados e preserva o restante.

\e[33m-c\e[0m   CONTEXTS
     Monta documentação apenas dos contextos informados.
     Deve-se informar os contextos separados por ",". Ex: \e[33m-c:domain,models\e[0m
     Segue lista dos contextos permitidos:

     \e[36mdomain\e[0m   - captura os domínios de negócio e serviços do backend
     \e[36mmodels\e[0m   - captura os modelos representativos dos bancos-de-dados e suas tabelas
     \e[36mpages\e[0m    - captura o conteúdo e estrutura das páginas
     \e[36mroutines\e[0m - captura os dados das rotinas

Exemplo de uso: \e[33mphp cmd/autodoc.php -r -c:\e[36mdomain,models\e[0m

RES;
        echo $res;
        exit;
    }
}
