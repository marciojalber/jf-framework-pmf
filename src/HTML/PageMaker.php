<?php

namespace JF\HTML;

use JF\Config;
use JF\FileSystem\Dir;
use JF\HTTP\Router;
use JF\Messager;
use JF\Exceptions\ErrorException;

/**
 * Monta páginas HTML.
 */
final class PageMaker extends \StdClass
{
    use PageMakerCSS;
    use PageMakerJS;
    use PageMakerPartial;

    /**
     * Configurações da página.
     */
    protected $config               = [];

    /**
     * Configurações da página.
     */
    protected $permissions          = [];

    /**
     * Plugins da página.
     */
    protected $plugins              = [];

    /**
     * Rota da página.
     */
    protected $data                 = null;

    /**
     * Rota da página.
     */
    protected $route                = null;

    /**
     * Ação da rota.
     */
    protected $action               = null;

    /**
     * Conteúdo da página.
     */
    protected $html                 = null;

    /**
     * Partes que compõem a página.
     */
    protected $partsPoint           = [];

    /**
     * Partes que compõem a página.
     */
    protected $parts                = [];

    /**
     * Documentador ativado.
     */
    protected $documentator         = false;

    /**
     * Dependências da view.
     */
    protected $depends              = [];

    /**
     * Permissões utilizadas na página.
     */
    protected $webcomponents        = [];

    /**
     * Permissões utilizadas na página.
     */
    protected $usedWebcomponents    = [];

    /**
     * Permissões utilizadas na página.
     */
    protected $wcToken              = null;

    /**
     * Permissões utilizadas na página.
     */
    protected $including            = null;

    /**
     * Inicia uma instância do objeto página.
     */
    public function __construct( $route_content, $documentator = false )
    {
        if ( $documentator )
        {
            $this->data         = (object) Config::get( 'doc.data' );
            $this->config       = (object) [
                'layout'    => Config::get( 'doc.default_layout', 'layout' ),
            ];
            $this->permissions  = [];
            $this->html         = $route_content;
            return;
        }

        $data               = [
            'route'         => $route_content,
            'url'           => [
                'base'      => URL_BASE,
                'ui'        => URL_UI,
                'pages'     => URL_PAGES,
                'route'     => URL_PAGES . '/' . $route_content,
            ],
        ];

        $this->route        = $route_content;
        $this->config       = [ 'layout' => Config::get( 'ui.default_layout', 'main' ) ];
        $this->permissions  = [];
        $this->data         = array_merge( $data, (array) Config::get( 'ui.data' ) );
    }
    
    /**
     * Monta uma página HTML.
     */
    public function makePage()
    {
        if ( !file_exists( DIR_LAYOUTS ) )
            Dir::makeDir( DIR_LAYOUTS );

        if ( !file_exists( DIR_VIEWS ) )
            Dir::makeDir( DIR_VIEWS );

        $view_path          = $this->getViewPath();
        $view_name          = substr( $view_path, strlen( DIR_BASE ) + 1 );

        if ( !file_exists( $view_path ) )
        {
            $msg            = Messager::get( 'html', 'page_not_created', $view_path );
            throw new ErrorException( $msg );
        }

        $this->depends[ $view_name ] = filemtime( $view_path );
        
        $view_ini           = DIR_VIEWS . '/' . $this->route . '/view.ini';
        
        if ( file_exists( $view_ini ) )
        {
            $ini                = parse_ini_file( $view_ini, true );
            $this->config       = isset( $ini[ 'CONFIG' ] )
                ? array_merge( $this->config, $ini[ 'CONFIG' ] )
                : $this->config;
            $this->permissions  = isset( $ini[ 'PERMISSIONS' ] )
                ? array_merge( $this->permissions, $ini[ 'PERMISSIONS' ] )
                : $this->permissions;
            $this->plugins      = isset( $ini[ 'PLUGINS' ] )
                ? array_merge( $this->plugins, $ini[ 'PLUGINS' ] )
                : $this->plugins;
            $this->data         = isset( $ini[ 'DATA' ] )
                ? array_merge( (array) $this->data, $ini[ 'DATA' ] )
                : $this->data;
        }

        foreach ( $this->permissions as &$item )
            $item           = preg_split( '@ *, *@', $item );

        $this->config       = json_decode( json_encode( $this->config ) );
        $this->permissions  = json_decode( json_encode( $this->permissions ) );
        $this->plugins      = json_decode( json_encode( $this->plugins ) );
        $this->data         = json_decode( json_encode( $this->data ) );
        $this->dateTime     = date( 'Y-m-d H:i:s' );
        $this->partsPoint[] = 'view.php';
        $this->parts        = [ 'view.php' => [] ];

        ob_start();
        include $view_path;
        $this->html         = ob_get_clean();
        $this->partsPoint   = [];

        $layout_filename    = DIR_LAYOUTS . '/' . $this->config->layout . '.php';
        $layout_source      = substr( $layout_filename, strlen( DIR_BASE ) + 1 );

        if ( file_exists( $layout_filename ) )
        {
            $this->partsPoint                   = [ $layout_source ];
            $this->parts                        = [ $layout_source => $this->parts ];
            $this->depends[ $layout_source ]    = filemtime( $layout_filename );
            ob_start();
            include $layout_filename;
            $this->html                         = ob_get_clean();
            $this->partsPoint                   = [];
        }
        
        $this->parseWebComponents();

        $response           = [
            'depends'       => $this->depends,
            'html'          => $this->html,
            'parts'         => $this->parts,
        ];

        return (object) $response;
    }
    
    /**
     * Monta uma página HTML.
     */
    public function makeDoc()
    {
        $layout_filename    = DIR_TEMPLATES . '/doc/' . $this->config->layout . '.php';

        ob_start();
        include $layout_filename;
        $this->html         = ob_get_clean();

        $response           = [ 'html' => $this->html ];

        return (object) $response;
    }

    /**
     * Inclue o conteúdo da página.
     */
    public function content()
    {
        return $this->html;
    }
    
    /**
     * Cria o arquivo minificado e seu observador.
     */
    protected function makeMin( $type, $filename, $files, $file_monitor )
    {
        $filepath   = DIR_UI . '/design/css/' . $filename . '.min.css';
        $minified   = $type
            ? CSSMinifer::minify( $files )
            : JSMinifer::minify( $files );
        $content    = $minified->content;
        $updates    = Utils::var_export( $minified->updates, true );
        file_put_contents( $filepath, $minified->content );
        file_put_contents( $file_monitor, $updates );
    }

    /**
     * Inclue um link para um arquivo público.
     */
    public function ui( $filepath = '' )
    {
        if ( JFTOOL )
            return '../ui/' . $filepath;

        $route      = explode( '/', Router::get( 'route' ) );
        $num_route  = count( $route );
        $server     = $_SERVER[ 'SERVER_NAME' ];
        
        return str_repeat( '../', $num_route ) . $filepath;
    }

    /**
     * Inclue um link para uma página.
     */
    public function page( $filepath = '' )
    {
        return $this->ui( 'pages/' . $filepath );
    }

    /**
     * Inclue os dados do controller na página.
     */
    public function data( $data_name )
    {
        $data = json_encode( $this->data );
        return "<script>var {$data_name} = {$data}</script>";
    }

    /**
     * Retorna o caminho para o arquivo de página.
     */
    public function getViewPath()
    {
        return DIR_VIEWS . "/{$this->route}/view.php";
    }
    
    /**
     * Define o valor de uma variável.
     */
    public function set( $key, $value )
    {
        $this->data->$key = $value;
    }
    
    /**
     * Define o layout da página.
     */
    public function setLayout( $layout )
    {
        $this->config->layout = $layout;
    }
    
    /**
     * Define o layout da página.
     */
    public function modelProps( $path, $unsafe = false )
    {
        $class_model    = 'App\\Domain\\' . str_replace( '.', '\\', $path );
        $file_model     = str_replace( '.', '/', $path );
        $file_model     = 'App/Domain/' . $file_model . '.php';
        $file_class     =  DIR_BASE . '/' . $file_model;
        $this->depends[ $file_model ] = filemtime( $file_class );

        return $class_model::getLayout( $unsafe );
    }
    
    /**
     * Obtém o caminho absoluto do arquivo.
     */
    private function getRealPath( $relative_path, $use_route_path )
    {
        $absolute_path      = preg_replace( '@\.\./+@', '', $relative_path );
        $diff_levels        = strlen( $relative_path ) - strlen( $absolute_path );
        $level_len          = 3; // strlen( '../' )
        $levels_up          = $diff_levels
            ? $diff_levels / $level_len
            : 0;

        $local_route        = $this->route
            ? $this->route . '/'
            : $this->route;

        if ( $levels_up )
        {
            $route_parts    = explode( '/', $this->route );
            $route_parts    = array_splice( $route_parts, 0, -$levels_up );
            $local_route    = implode( '/', $route_parts );
            $local_route   .= $local_route
                ? '/'
                : '';
        }

        $real_path          = $use_route_path
            ? $local_route . $absolute_path
            : $relative_path;
        
        return $real_path;
    }

    /**
     * Imprime o script com os WebComponents.
     */
    public function webComponents()
    {
        if ( $this->wcToken )
            return;

        $this->wcToken  = uniqid( '', true );
        echo "{{wc_$this->wcToken}}";
    }

    /**
     * Imprime o script com os WebComponents.
     */
    protected function parseWebComponents()
    {
        if ( !$this->wcToken )
            return;

        $parsed         = true;
        $path           = new \FileSystemIterator( DIR_TEMPLATES . '/html/webcomponents' );
        $wc_content     = [];
        $base_path      = DIR_BASE . '/templates/html/webcomponents/';

        foreach ( $path as $item )
            $this->discoverWebComponents( $base_path, $item );

        foreach ( $this->webcomponents as $tag => $wc_path )
        {
            $content        = $this->declareUsedComponents( $base_path, $tag, $wc_path, $this->html );

            if ( !$content )
                continue;

            $wc_content[]   = $content;
        }

        if ( !$this->usedWebcomponents )
            return;
        
        sort( $this->usedWebcomponents );
        $used_wc        = implode( "\n   ", $this->usedWebcomponents );
        $wc_content     = implode( N, $wc_content );
        $wc_content     = "/*\nUSED WEB COMPONENTS:\n   {$used_wc}\n*/" . N . $wc_content;
        $wc_path        = DIR_UI . '/pages/' . $this->route;

        Dir::makeDir( $wc_path );
        
        $wc_file        = $wc_path . '/webcomponents.js';
        $wc_link        = basename( $this->route ) . '/webcomponents.js';
        file_put_contents( $wc_file, $wc_content );

        $this->html     = str_replace( "{{wc_$this->wcToken}}", "<script src='$wc_link'></script>", $this->html );
    }

    /**
     * Descobre os componentes da aplicação.
     */
    protected function discoverWebComponents( $base_path, $item )
    {
        $base_len = strlen( $base_path );

        if ( $item->isFile() && $item->getFilename() == 'component.js' )
        {
            $pathname           = str_replace( '\\', '/', $item->getPathName() );
            $wc_path            = dirname( $pathname );
            $tag                = basename( $wc_path );
            
            $this->webcomponents[ $tag ] = substr( $wc_path, $base_len );
        }

        if ( !$item->isDir() )
            return;

        $path   = new \FileSystemIterator( $item->getPathName() );

        foreach ( $path as $item )
            $this->discoverWebComponents( $base_path, $path );
    }

    /**
     * Obtém o conteúdo dos componentes invocados na página.
     */
    protected function declareUsedComponents( $base_path, $tag, $wc_path, $source, $deep = false )
    {
        $file_component = $base_path . $wc_path . '/component.js';
        $file_template  = $base_path . $wc_path . '/template.html';
        $base_len       = strlen( DIR_BASE ) + 1;
        $depend_js      = substr( $file_component, $base_len );
        $depend_html    = substr( $file_template, $base_len );
        $response       = [];

        if ( !preg_match( "@<$tag@", $source ) || in_array( $tag, $this->usedWebcomponents ) )
            return;

        $wc_content     = file_get_contents( $file_component );
        $this->depends[ $depend_js ]        = filemtime( $file_component );
        $this->usedWebcomponents[]          = $tag;

        if ( file_exists( $file_template ) )
        {
            $template                       = file_get_contents( $file_template );
            $template                       = \App\App::minifyHTML( $template );
            $wc_content                     = str_replace( '{$template}', $template, $wc_content );
            $this->depends[ $depend_html ]  = filemtime( $file_template );
        }

        if ( !$deep )
        {
            foreach ( $this->webcomponents as $tag_deep => $wc_path_deep )
            {
                $content    = $this->declareUsedComponents( $base_path, $tag_deep, $wc_path_deep, $wc_content, true );

                if ( !$content )
                    continue;

                $response[] = $content;
            }
        }

        $js_name        = preg_replace_callback( '@-(.)@', function( $matches ) {
            return strtoupper( $matches[ 1 ] );
        }, $tag );

        $wc_content = \App\App::registerWebComponent( $tag, $js_name, $wc_content );
        $wc_content = \App\App::minifyJS( $wc_content );
        $response[] = $wc_content;
        
        return implode( N, $response );
    }
}
