<?php

namespace JF;

/**
 * Classe para manipulação de erros.
 */
final class Autoloader
{
    /**
     * Trilha manual de arquivos solicitados.
     */
    protected static $loadedClasses = [];

    /**
     * Disparado quando ocorrer erros fatais.
     */
    public static function register()
    {
        static $running = false;

        if ( $running )
            return;
        
        $running = true;

        spl_autoload_register([ __CLASS__, 'autoload' ]);
    }
    
    /**
     * Autocarregamento de classes.
     */
    private static function autoload( $class )
    {
        $filename   = self::getClassFilename( $class );
        $filename   = preg_replace( '@phar://.*?.phar@', __DIR__, $filename );

        if ( file_exists( $filename ) )
        {
            self::$loadedClasses[ $class ] = $filename;

            include $filename;
            return;
        }

        $file_vendor = DIR_VENDORS . '/' . str_replace( '\\', '/', $class ) . '.php';
        
        if ( file_exists( $file_vendor ) )
        {
            self::$loadedClasses[ $class ] = $filename;

            include $file_vendor;
            return;
        }
        
        // Se não achou o arquivo, procura dentro das bibliotecas cadastradas
        $vendors = Config::get( 'vendors' );
        
        if ( !$vendors )
            return;
        
        // Itera sobre as bibliotecas cadastradas
        foreach ( $vendors as $class_vendor => $file_vendor )
        {
            $found = self::tryLoadVendor( $class, $file_vendor, $class_vendor );

            if ( $found )
                break;
        }
    }

    /**
     * Autocarregamento de classes.
     */
    private static function tryLoadVendor( $class, $file_vendor, $class_vendor )
    {
        $class_vendor = str_replace( '.', '\\', $class_vendor );
        
        if ( !$file_vendor || $class_vendor !== $class )
            return false;

        $vendor_path    = DIR_VENDORS . '/' . $file_vendor;
        $auload_fns     = spl_autoload_functions();
        
        // Buffer utilizado para evitar que um autoload errado informado equivocadamente
        // polua o envio de informações à página
        ob_start();
        include $vendor_path;
        ob_get_clean();
        
        // Se achou a classe
        if ( class_exists( $class ) )
        {
            self::$loadedClasses[ $class ] = $vendor_path;

            return true;
        }
        
        // Se a classe não estiver diretamente declarada no arquivo, chama novamente o autoload
        $new_auload_fns = spl_autoload_functions();
        
        if ( count( $new_auload_fns )  > count ( $auload_fns ) )
            spl_autoload_call( $class );
    }

    /**
     * Retorna o nome do arquivo correspondente à classe.
     */
    public static function getClassFilename( $classname )
    {
        /*
        $namespaces     = Config::get( 'namespaces', [] );
        $new_classname  = $classname;

        foreach ( $namespaces as $namespace => $path )
        {
            if ( strpos( $classname, $namespace ) !== 0 )
                continue;

            $new_classname = $path . substr( $classname, strlen( $namespace ) );
            break;
        }

        $class_path     = str_replace( '\\', '/', $new_classname );
        */
        $class_path     = str_replace( '\\', '/', $classname );
        $filename       = substr( $class_path, 0, 3 ) != 'JF/'
            ? DIR_BASE . '/' . $class_path . '.php'
            : DIR_CORE . '/' . substr( $class_path, 3 ) . '.php';

        return $filename;
    }

    /**
     * Retorna as classes carregadas pelo autoload.
     */
    public static function loadedClasses( $printify = 0 )
    {
        if ( !$printify )
            return self::$loadedClasses;

        $res    = ['Loaded classes:'];
        $total  = 0;

        foreach ( self::$loadedClasses as $class => $filename )
        {
            $msg    = str_replace( DIR_BASE, 'APP', $filename );
            $msg    = str_replace( DIR_CORE, 'JF', $msg );
            $res[]  = "#{$total} $msg: $class";
            $total++;
        }

        $res    = implode( PHP_EOL, $res );

        return $res;
    }
}
