<?php

namespace JF;

require_once( __DIR__ . '/Autoloader.php' );
require_once( __DIR__ . '/FileSystem/Dir.php' );
require_once( __DIR__ . '/Config.php' );

use JF\Doc\DocParserFeature;
use JF\Domain\Tester;
use JF\FileSystem\Dir;
use JF\HTML\HTML_Responder;
use JF\HTTP\Request;
use JF\HTTP\Responder;
use JF\HTTP\Router;

/**
 * Classe principal do framework
 */
final class App
{
    /**
     * Inicia a aplicação.
     */
    public static function run( $dirbase = null )
    {
        self::setInitialHeaders();
        self::defines( $dirbase );
        self::configPHPEnv();
        self::defineProductPaths();
        Env::setEnv();
        Router::basicDefines();
        self::redirects();
        self::optimizeProcesses();

        // @todo restabelecer
        // self::previneDDoS();
        
        Router::defineRoute();

        HTML_Responder::send();
        
        // @todo hijacking desativado
        // Session::init();
        // DocParserFeature::parse();
        // Tester::execute();
        Responder::sendResponse();
    }

    /**
     * Define o cabeçalho de retorno inicial.
     */
    private static function setInitialHeaders()
    {
        header( 'Content-Type: text/plain; charset=UTF-8' );
        header( 'X-Powered-By: JF Framework/PHP 8.1 - https://github.com/marciojalber/jf-framework-php');
    }

    /**
     * Define as constantes.
     */
    private static function defines( $dirbase )
    {
        // Sistema operacional
        define( 'WIN',              substr( PHP_OS, 0, 3 ) == 'WIN' );

        // Pastas da aplicação
        define( 'DIR_CORE',             str_replace( '\\', '/', __DIR__ ) );
        define( 'DIR_GUIDE',            dirname( DIR_CORE ) . '/guide' );
        $len_rootpath   = strlen( $_SERVER[ 'DOCUMENT_ROOT' ] );
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

        $server         = isset( $_SERVER[ 'SERVER_NAME' ] )
            ? $_SERVER[ 'SERVER_NAME' ]
            : null;

        define( 'BASE_APP', substr( DIR_BASE, $len_rootpath ) );
        define( 'SERVER',       $server );
        define( 'REQUEST_URI',  $_SERVER[ 'REQUEST_URI' ] );
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
        ini_set( 'error_reporting',         E_ALL );
        ini_set( 'zlib.output_compression', 1 );

        // Registra manipuladores
        Autoloader::register();
        ErrorHandler::register();

        // Outras operações de inicialização
        date_default_timezone_set( 'America/Sao_Paulo' );
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

    /**
     * Redireciona as requisições feitas a LOCALHOST ou em HTTP inseguro,
     * se definido nas configurações.
     */
    private static function redirects()
    {
        $request_scheme = isset( $_SERVER[ 'REQUEST_SCHEME' ] )
            ? $_SERVER[ 'REQUEST_SCHEME' ]
            : null;

        if ( !HTTPS || !$request_scheme || $request_scheme == 'https' )
        {
            return;
        }

        $url = 'https://' . SERVER . REQUEST_URI;

        Request::redirect( $url, true );
    }

    /**
     * Processa as requisições da fila de espera.
     */
    private static function optimizeProcesses()
    {
        $log_path       = DIR_PRODUCTS . '/executions';
        $log_file       = $log_path . '/queue-processes.log';

        if ( !file_exists( $log_path ) )
        {
            Dir::makeDir( $log_path );
        }

        if ( !file_exists( $log_file ) )
        {
            file_put_contents( $log_file, null );
            return;
        }
        
        $expired_cach  = filemtime( $log_file ) + MIN > time();

        if ( !$expired_cach )
        {
            return;
        }

        file_put_contents( $log_file, date( 'Y-m-d H:i:s' ) );
        
        Cache::clear();
    }

    /**
     * Previne o servidor de ataques do tipo DDoS.
     * 
     * Utilizei a estratégia de gravar em arquivo a lista das últimas requisições
     * realizadas e, na primeira linha, o time em milisegundos da gravação do arquivo,
     * o qual é resetado toda vez que o time de uma requisição superar o time no arquivo.
     * 
     * Se a requis a requisição atual (IP + URL) constar na lista do arquivo,
     * será considerada um ataque DDoS
     */
    private static function previneDDoS()
    {
        // Prepara as variáveis básicas
        $filename   = DIR_PRODUCTS . '/ddos-list.txt';
        $now        = microtime( true );
        $line       = Request::ipClient() . ' ' . REQUEST_URI;

        // Se o arquivo não existe, grava novo arquivo
        if ( !file_exists( $filename ) )
        {
            return file_put_contents( $filename, $now . N . $line  );
        }

        // Captura dados para gravar dados no arquivo
        $file       = new \SplFileObject( $filename, 'a+' );
        $last       = $now - floatval( $file->fgets() );
        $interval   = Config::get( 'security.ddos_timeout' );
        
        // Se arquivo expirou, grava novo arquivo
        if ( $last > $interval )
        {
            return file_put_contents( $filename, $now . N . $line );
        }

        // Se a requisição não consta na lista das últimas requisições,
        // grava a requisição para prevenir o ataque DDoS
        $requests   = explode( N, $file->fread( filesize( $filename ) ) );
        if ( !in_array( $line, $requests ) )
        {
            return $file->fwrite( N . $line );
        }

        // A requisição atual consta na lista e trata-se de ataque DDoS
        Request::redirect( '/app/views/errors/403.html' );
    }
}
