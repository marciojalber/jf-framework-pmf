<?php

namespace JF;

use JF\Exceptions\ErrorException;

/**
 * Classe responsavél por definir o ambiente da aplicação.
 */
class Env
{
    /**
     * Ambientes da aplicação.
     */
    protected static $envs  = [
        'dev',
        'tests',
        'accept',
        'prod',
    ];

    /**
     * Ambiente padrão da aplicação.
     */
    protected static $defaultEnv    = 'dev';

    /**
     * Ambiente padrão da aplicação.
     */
    protected static $defaultHTTPS  = false;

    /**
     * Define o ambiente da aplicação.
     */
    public static function setEnv()
    {
        static $running = false;

        if ( $running )
            return;

        $running        = true;

        // Define o ambiente da aplicação
        $default_env    = Config::get( 'servers.default.env', self::$defaultEnv );
        $default_https  = Config::get( 'servers.default.https', self::$defaultHTTPS );
        $servers        = Config::get( 'servers' );
        $server         = SERVER;

        // Configuração não encontrada para o servidor
        if ( !$server || !isset( $servers->$server ) || !isset( $servers->$server->env ) )
            return self::definesEnv( $default_env, $default_https );

        // Ambiente desconhecido
        if ( !in_array( $servers->$server->env, self::$envs ) )
        {
            $msg = Messager::get( 'env', 'env_unknown', $servers->$server->env );
            throw new ErrorException( $msg );
        }

        // Configuração definida corretamente
        $env   = $servers->$server->env;
        $https = !empty( $servers->$server->https );
        
        self::definesEnv( $env, $https );
    }

    /**
     * Define as constantes de ambiente da aplicação.
     */
    private static function definesEnv( $env, $https )
    {
        define( 'ENV',          $env );
        define( 'ENV_DEV',      ENV === 'dev' );
        define( 'ENV_TESTS',    ENV === 'tests' );
        define( 'ENV_ACCEPT',   ENV === 'accept' );
        define( 'ENV_PROD',     ENV === 'prod' );

        define( 'HTTPS',        $https );
    }

    /**
     * Retorna os ambientes do sistema.
     */
    public static function envs()
    {
        return self::$envs;
    }
}
