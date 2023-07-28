<?php

namespace JF;

use JF\Config;
use JF\Exceptions\ErrorException;
use JF\FileSystem\Dir;
use JF\Messager;

/**
 * Classe para manipulação de sessões.
 */
class Session
{
    /**
     * Prefixo da sessão.
     */
    protected static $prefix        = null;

    /**
     * Método para aplicar configuração ao iniciar a aplicação.
     */
    public static function init()
    {
        static $running = false;
        
        if ( $running )
        {
            return true;
        }
        
        $running = true;
        
        self::configSecutiry();
        self::configExpires();
        self::startSession();
        self::renameSession();
        self::previneHijackingAtack();
    }

    /**
     * Altera as configurações iniciais do PHP.
     */
    private static function configSecutiry()
    {
        // Deve salvar a sessão em arquivo
        ini_set( 'session.save_handler', 'files' );

        // Não pode usar ID ainda não iniciado / inexistente no servidor
        ini_set( 'session.use_strict_mode',     true );
        
        // ID de sessão não podem ser passados via URL
        ini_set( 'session.use_cookies',         true );
        ini_set( 'session.use_only_cookies',    true );
        ini_set( 'session.use_trans_sid',       false );

        // Previne ataques via XSS
        ini_set( 'session.cookie_httponly',     true );
    }

    /**
     * Altera configurações específicas de sessão.
     */
    private static function configExpires()
    {
        session_cache_limiter( 'private_no_expire' );
        session_cache_expire( Config::get( 'sessions.cache_expires', 60 ) );
        
        // Informa ao navegador para excluir o cookie da sessão ao ser fechado
        ini_set( 'session.cookie_lifetime',     '0' );

        // Desabilita o coletor de lixo baseado em probabilidade de requisições de usuário
        ini_set( 'session.gc_probability', 0 );
        ini_set( 'session.gc_divisor', 1000 );
        
        // Define o prazo de vida das sessões sem acesso
        $gc_maxlifetime = Config::get( 'sessions.gc_maxlifetime', 30 ) * 60;
        ini_set( 'session.gc_maxlifetime', $gc_maxlifetime );

        $filename   = DIR_PRODUCTS . '/executions/sessions-gc.log';
        $now        = date( 'Y-m-d H:i:s' );

        if ( !file_exists( $filename ) )
        {
            file_put_contents( $filename, $now );
            return;
        }
        
        if ( time() - filemtime( $filename ) < 60 )
        {
            return;
        }

        file_put_contents( $filename, $now );
        ini_set( 'session.gc_probability', 1 );
        ini_set( 'session.gc_divisor', 1 );
    }

    /**
     * Altera configurações específicas de sessão.
     */
    private static function startSession()
    {
        $session_path = self::sessionPath();
        
        if ( !file_exists( $session_path ) )
        {
            Dir::makeDir( $session_path );
        }

        if ( !is_writable( $session_path ) )
        {
            $msg    = Messager::get( 'sessions', 'path_is_not_writable' );
            throw new ErrorException( $msg );
        }

        session_save_path( $session_path );
        session_name( self::token() );
        session_start();
    }

    /**
     * Altera as configurações iniciais do PHP.
     */
    private static function renameSession()
    {
        $headers    = (object) getallheaders();

        if ( empty( $headers->Accept ) )
        {
            return;
        }

        if ( strpos( $headers->Accept, 'text/html' ) === false )
        {
            return;
        }

        $old_sess   = self::sessionPath() . '/sess_' . session_id();
        session_regenerate_id();

        if ( file_exists( $old_sess ) )
        {
            unlink( $old_sess );
        }
    }

    /**
     * Altera as configurações iniciais do PHP.
     */
    private static function previneHijackingAtack()
    {
        // Proteção contra ataque Hijacking
        $tokenName  = basename( DIR_BASE ) . '_token_app';
        $appToken   = self::token();

        if ( !array_key_exists( $tokenName, $_SESSION ) )
        {
            return $_SESSION[ $tokenName ] = $appToken;
        }

        if ( $_SESSION[ $tokenName ] !== $appToken )
        {
            $msg    = Messager::get( 'session', 'hijacking_attack' );
            throw new ErrorException( $msg );
        }
    }

    /**
     * Captura o token da sessão.
     */
    private static function token()
    {
        $prefix     = basename( DIR_BASE );
        $userAgent  = array_key_exists( 'HTTP_USER_AGENT', $_SERVER )
            ? $_SERVER[ 'HTTP_USER_AGENT' ]
            : '';
        $encoding   = array_key_exists( 'HTTP_ACCEPT_ENCODING', $_SERVER )
            ? $_SERVER[ 'HTTP_ACCEPT_ENCODING' ]
            : '';
        $language   = array_key_exists( 'HTTP_ACCEPT_LANGUAGE', $_SERVER )
            ? $_SERVER[ 'HTTP_ACCEPT_LANGUAGE' ]
            : '';
        $token  = md5( $prefix . $userAgent . $encoding . $language );
        
        return $token;
    }

    /**
     * Retorna o valor de sessao da chave informada ou um valor padrao, caso a chave nao exista.
     */
    public static function get( $key, $default = null )
    {
        $key        = self::prefix() . $key;
        $response   = array_key_exists( $key, $_SESSION )
            ? $_SESSION[ $key ]
            : $default;
        return $response;
    }

    /**
     * Retorna o valor de sessao da chave informada ou um valor padrao, caso a chave nao exista.
     */
    public static function set( $key, $value )
    {
        $_SESSION[ self::prefix() . $key ] = $value;
        
        return true;
    }

    /**
     * Exclui um indice no session.
     */
    public static function delete( $key )
    {
        unset( $_SESSION[ self::prefix() . $key ] );
    }
    
    /**
     * Captura o prefixo de cada sessão definida no arquivo de configurações.
     */
    public static function prefix()
    {
        if ( self::$prefix === null )
        {
            self::$prefix = basename( DIR_BASE ) . '_';
        }

        return self::$prefix;
    }

    /**
     * Retorna a pasta dos arquivos de sessão.
     */
    private static function sessionPath()
    {
        return DIR_PRODUCTS . '/sessions';
    }

    /**
     * Conta o número de sessões de usuário ativas.
     */
    public static function count( $timelife = 1 )
    {
        $dir_sessions   = self::sessionPath();
        $session_path   = new \FileSystemIterator( $dir_sessions );
        $sessions       = 0;

        foreach ( $session_path as $item )
        {
            $filename   = $item->getPathName();
            $sessions  += time() - filemtime( $filename ) <= 60
                ? 1
                : 0;
        }

        return $sessions;
    }
}

