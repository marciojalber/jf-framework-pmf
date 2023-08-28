<?php

namespace JF;

require_once( __DIR__ . '/Autoloader.php' );
require_once( __DIR__ . '/Config.php' );

use JF\Command;
use JF\Env;
use JF\Utils;

/**
 * Classe principal do framework
 */
final class Terminal
{
    /**
     * Inicia a aplicação.
     */
    public static function run( $args, $dirbase = null, $document_root = null )
    {
        self::defines( $dirbase, $document_root );
        self::configPHPEnv();
        self::defineIpServer();
        self::defineProductPaths();
        Env::setEnv();

        $console_elements   = Command::parseArgs( $args );
        $class_command      = $console_elements->class;
        $args_command       = $console_elements->args;

        Command::validateClass( $class_command );
        
        $command            = new $class_command();
        $command->execute( $args_command );
    }

    /**
     * Define as constantes.
     */
    private static function defines( $dirbase, $document_root )
    {
        // Sistema operacional
        define( 'WIN',              substr( PHP_OS, 0, 3 ) == 'WIN' );

        // Pastas da aplicação
        define( 'DIR_CORE',             str_replace( '\\', '/', __DIR__ ) );
        $document_root  = $document_root
            ? $document_root
            : $_SERVER[ 'DOCUMENT_ROOT' ];
        $len_rootpath   = strlen( $document_root );
        $dirbase        = $dirbase
            ? str_replace( '\\', '/', $dirbase )
            : str_replace( '\\', '/', dirname( dirname( DIR_CORE ) ) );
        define( 'DIR_BASE',             $dirbase );
        define( 'DIR_APP',              DIR_BASE . '/App' );
            define( 'DIR_CONTROLLERS',      DIR_APP  . '/Controllers' );
            define( 'DIR_DOMAIN',           DIR_APP  . '/Domain' );
                define( 'DIR_FEATURES',     DIR_DOMAIN . '/Features' );
                define( 'DIR_RULES',        DIR_DOMAIN . '/Rules' );
            define( 'DIR_ROUTINES',         DIR_APP  . '/Routines' );
        define( 'DIR_CONFIG',               DIR_BASE . '/config' );
        define( 'DIR_MODELS',           DIR_APP  . '/Models' );
        define( 'DIR_TEMPLATES',        DIR_BASE . '/templates' );
            define( 'DIR_LAYOUTS',          DIR_TEMPLATES . '/html/layouts' );
            define( 'DIR_VIEWS',            DIR_TEMPLATES . '/html/pages' );
            define( 'DIR_PARTIALS',         DIR_TEMPLATES . '/html/partials' );
        define( 'DIR_UI',               DIR_BASE . '/ui' );
        define( 'DIR_PAGES',            DIR_UI   . '/pages' );
        define( 'DIR_VENDORS',          DIR_BASE . '/Vendors' );
        
        // HTML
        define( 'N',            "\n" );
        define( 'BR',           '<br />' );
        
        // Tempos
        define( 'SEG',          1 );
        define( 'MILISEG',      1000 );
        define( 'MIN',          SEG      * 60 );
        define( 'MILIMIN',      MILISEG  * 60 );
        define( 'HOUR',         MIN      * 60 );
        define( 'MILIHOUR',     MILIMIN  * 60 );
        define( 'DAY',          HOUR     * 24 );
        define( 'MILIDAY',      MILIHOUR * 24 );

        define( 'BASE_APP', substr( DIR_BASE, $len_rootpath ) );
    }

    /**
     * Prepara o ambiente PHP.
     */
    private static function configPHPEnv()
    {
        // Configurações iniciais do PHP
        ini_set( 'display_errors',          0 );
        ini_set( 'display_startup_errors',  0 );
        ini_set( 'log_errors',              0 );
        ini_set( 'error_reporting',         E_ALL ^ E_STRICT );
        ini_set( 'zlib.output_compression', 1 );

        // Registra manipuladores
        Autoloader::register();
        ErrorHandler::register();

        // Outras operações de inicialização
        date_default_timezone_set( 'America/Sao_Paulo' );
    }

    /**
     * Define o IP do servidor a partir do MAC Address.
     */
    private static function defineIpServer()
    {
        $mac    = Utils::getMac();
        $server = Config::get( "macs.$mac" );
        
        if ( method_exists( 'App\\App', 'terminalDefineIpServer' ) )
            $server = \App\App::terminalDefineIpServer( $server );

        define( 'SERVER', $server );
    }

    /**
     * Define a pasta de produtos da aplicação.
     */
    private static function defineProductPaths()
    {
        $products_path      = Config::get( 'products.path', 'products' );
        $products_path      = realpath( DIR_BASE . '/' . $products_path );
        $products_path      = $products_path
            ? $products_path
            : DIR_BASE . '/products';
        $products_path      = str_replace( '\\', '/', $products_path );

        define( 'DIR_PRODUCTS',     $products_path );
        define( 'DIR_BACKUPS',      DIR_PRODUCTS . '/backups' );
        define( 'DIR_LOGS',         DIR_PRODUCTS . '/logs' );
        define( 'DIR_STORAGE',      DIR_PRODUCTS . '/storage' );

        ini_set( 'upload_tmp_dir',  DIR_PRODUCTS . '/uploads' );
    }
}
