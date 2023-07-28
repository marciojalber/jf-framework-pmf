<?php

namespace JF;

use JF\HTTP\Router;
use JF\FileSystem\Dir;

use JF\Exceptions\ErrorException;

/**
 * Classe para gestão da configuração da aplicação.
 */
final class Config
{
    /**
     * Armazena as configurações já capturadas.
     */
    protected static $config    = [];

    /**
     * Indica o separador de contextos da configuração.
     */
    protected static $separator = '.';

    /**
     * Captura as configurações da aplicação.
     */
    public static function get( $path, $default = null, array $opts = array() )
    {
        if ( is_array( $path ) )
            return self::getEach( $path, $default, $opts );

        // Define as variáveis básicas
        $separator      = !empty( $opts[ 'separator' ] )
            ? $opts[ 'separator' ]
            : self::$separator;
        $path           = explode( $separator, $path );
        $context        = strtolower( array_shift( $path ) );

        // Captura as configurações não carregadas do arquivo correspondente
        $context_loaded = array_key_exists( $context, self::$config );
        $reload         = isset( $opts[ 'reload' ] ) && $opts[ 'reload' ];
        
        if ( !$context_loaded || $reload )
        {
            self::load( $context );
        }

        // Tenta retornar a configuração solicitada
        $config         = self::$config[ $context ];
        
        if ( is_null( $config ) )
        {
            return $default;
        }

        foreach ( $path as $key )
        {
            if ( !array_key_exists( $key, (array) $config ) )
            {
                return $default;
            }

            $config = $config->$key;
        }
        
        // Retorna as configurações
        return $config;
    }

    /**
     * Aplica a captura de configurações a cada um dos caminhos solicitados.
     */
    protected static function getEach( $paths, $default, $opts )
    {
        foreach ( $paths as $path )
        {
            $config = self::get( $path, null, $opts );

            if ( !is_null( $config ) )
            {
                return $config;
            }
        }

        return $default;
    }

    /**
     * Captura as configurações da aplicação em outro idioma.
     */
    public static function langGet( $path, $default = null, array $opts = array() )
    {
        $lang = Router::get( 'lang' );
        return self::get( "lang/{$lang}/{$path}" );
    }

    /**
     * Altera uma configuração.
     */
    public static function set(
        $path,
        $value      = null,
        $auto_save  = false,
        array $opts = array()
    ) {
        // Define as variáveis básicas
        $separator      = !empty( $opts[ 'separator' ] )
            ? $opts[ 'separator' ]
            : self::$separator;
        $path       = explode( $separator, $path );
        $context    = strtolower( array_shift( $path ) );

        // Se o contexto da configuração não existe
        if ( !array_key_exists( $context, self::$config ) )
            self::load( $context );

        if ( !self::$config )
            self::$config[ $context ] = (object) [];

        if ( !$path && !( is_array( $value ) || is_object( $value ) ) )
        {
            $msg = Message::get( 'config', 'invalid_config_format', $context );
            throw new ErrorException( $msg );
        }

        if ( !$path )
            self::$config[ $context ] = json_decode( json_encode( $value ) );

        if ( $path )
            self::setConfigInPath( $context, $path, $value );

        if ( $auto_save )
            self::save( $context );
    }

    /**
     * Altera o valor da configuração para de um caminho específico.
     */
    private static function setConfigInPath( $context, $path, $value )
    {
        $config     = self::$config[ $context ];
        $last_key   = null;
        
        foreach ( $path as $key )
        {
            if ( $last_key )
                $config = $config->$last_key;

            if ( !property_exists( $config, $key ) )
                $config->$key = (object) [];

            $last_key = $key;
        }

        $config->$last_key = json_decode( json_encode( $value ) );
    }

    /**
     * Salva as configurações atuais.
     */
    public static function save( $context )
    {
        if ( !array_key_exists( $context, self::$config ) )
        {
            $msg = Message::get( 'config', 'config_has_not_context', $context );
            throw new ErrorException( $msg );
        }

        $config_file = self::path( $context );
        $config_data = self::$config[ $context ];
        $config_data = json_decode( json_encode( $config_data ), true );
        $content     = Utils::var_export( $config_data, true );

        file_put_contents( $config_file, $content );
    }

    /**
     * Carrega as configurações de um arquivo.
     */
    private static function load( $context )
    {
        if ( !file_exists( DIR_CONFIG ) )
            Dir::makeDir( DIR_CONFIG );

        $file_config                = self::path( $context );
        self::$config[ $context ]   = file_exists( $file_config )
            ? json_decode( json_encode( include $file_config ) )
            : null;
    }

    /**
     * Carrega as configurações de um arquivo.
     */
    public static function path( $path )
    {
        return DIR_CONFIG . '/' . $path . '.php';
    }
}
