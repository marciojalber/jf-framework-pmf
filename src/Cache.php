<?php

namespace JF;

use JF\FileSystem\Dir;
use JF\Exceptions\ErrorException;
use JF\Messager;

/**
 * Classe que manipula cache de arquivos.
 * 
 * @example 
 *      $cache          = new Cache( 'caminho' );
 *      $cache->expires = 5; // 5 minutos
 *      $cache->source  = function() {};
 *      $cache->updateOnEmpty( true );
 *      $cache->updateOnChangeFile( __FILE__ );
 *      
 *      return $cache->contents();
 */
class Cache
{
    /**
     * Identificador do cache.
     */
    protected $id;

    /**
     * Indica o tempo de expiração do cache.
     */
    public $expires = null;

    /**
     * Função para ler os dados para criar o cache.
     */
    public $source = null;

    /**
     * Indica se o cache deve ser reprocessado se o conteúdo estiver vazio.
     */
    protected $updateOnEmpty = false;

    /**
     * Arquivo a ser observado cuja mudança provoca releitura dos dados de origem.
     */
    protected $listenChangeFile = null;

    /**
     * Armazena o conteúdo do cache.
     */
    protected $contents = null;

    /**
     * Indica se o cache já foi lido.
     */
    protected $ready = false;

    /**
     * Retona se o cache existe.
     */
    public function __construct( $id )
    {
        $this->id = $id;
    }

    /**
     * Indica se o cache deve ser atualizado se seu conteúdo estiver vazio.
     */
    public function updateOnEmpty( $update )
    {
        $this->updateOnEmpty = (bool) $update;
        return $this;
    }

    /**
     * Indica se o cache deve ser atualizado se houver modificação em algum arquivo.
     */
    public function updateOnChangeFile( $filename )
    {
        $this->listenChangeFile = $filename;
        return $this;
    }

    /**
     * Retorna o conteúdo do cache.
     */
    public function contents()
    {
        if ( !$this->ready )
        {
            $this->read();
        }

        return $this->contents;
    }

    /**
     * Retorna o conteúdo do cache.
     */
    protected function read()
    {
        $this->ready    = true;
        
        if ( !self::has( $this->id ) )
        {
            $reader         = $this->source;
            $this->contents = $reader();
            self::set( $this->id, $this->contents, $this->listenChangeFile );
            return;
        }

        $filecache      = self::path( $this->id );

        if ( $this->expires > 0 && time() - filemtime( $filecache ) >= $this->expires )
        {
            $reader         = $this->source;
            $this->contents = $reader();
            self::set( $this->id, $this->contents, $this->listenChangeFile );
            return;
        }

        $cache_encoded  = file_get_contents( $filecache );
        $cache          = unserialize( $cache_encoded );

        if ( empty( $cache[ 'data' ] ) && $this->updateOnEmpty )
        {
            $reader         = $this->source;
            $this->content  = $reader();
            self::set( $this->id, $this->content, $this->listenChangeFile );
            return;
        }

        // Não observa arquivo - nada mudou
        if ( !$this->listenChangeFile )
        {
            $this->contents = $cache[ 'data' ];
            return;
        }

        $filetime = file_exists( $this->listenChangeFile )
            ? filemtime( $this->listenChangeFile )
            : null;

        // Observa arquivo que não existia e continua não existindo - nada mudou
        if ( empty( $cache[ 'filetime' ] ) && !$filetime )
        {
            $this->contents = $cache[ 'data' ];
            return;
        }

        // Observa arquivo que não existia mas agora existe - MUDOU
        if ( empty( $cache[ 'filetime' ] ) && $filetime )
        {
            $reader         = $this->source;
            $this->contents = $reader();
            self::set( $this->id, $this->contents, $this->listenChangeFile );
            return;
        }

        // Observa arquivo que existia e agora não existe mais - MUDOU
        if ( isset( $cache[ 'filetime' ] ) && !$filetime )
        {
            $reader         = $this->source;
            $this->contents = $reader();
            self::set( $this->id, $this->contents, $this->listenChangeFile );
            return;
        }

        // Observa arquivo que foi atualizado - MUDOU
        if ( $cache[ 'filetime' ] != $filetime )
        {
            $reader         = $this->source;
            $this->contents = $reader();
            self::set( $this->id, $this->contents, $this->listenChangeFile );
            return;
        }

        // Observa arquivo que não foi atualizado - nada mudou
        $this->contents = $cache[ 'data' ];
    }

    /**
     * Retona se o cache existe.
     */
    public static function has( $key )
    {
        $cache_path = self::path( $key );
        return file_exists( $cache_path );
    }

    /**
     * Retona o momento da criação do cache.
     */
    public static function created( $key )
    {
        $cache_path = self::path( $key );

        if ( !file_exists( $cache_path ) )
        {
            return null;
        }

        return filemtime( $cache_path );
    }

    /**
     * Retona o cache conteúdo do cache.
     */
    public static function get( $key, $data_method_generator = null, $expires = 0 )
    {
        $expires    = self::turnExpiresToMinutes( $expires );
        $cache_path = self::path( $key );

        if ( !file_exists( $cache_path ) )
        {
            return $data_method_generator
                ? self::generateData( $key, $data_method_generator )
                : null;
        }
        
        $time           = time();
        $validate       = $expires > 0
            ? filemtime( $cache_path ) + $expires
            : $time + 1;
        $cache_expired  = $validate - $time <= 0;

        if ( $cache_expired && !$data_method_generator )
        {
            unlink( $cache_path );
            return null;
        }

        if ( $cache_expired && $data_method_generator )
        {
            return self::generateData( $key, $data_method_generator );
        }

        $cache_encoded  = file_get_contents( $cache_path );
        $cache          = unserialize( $cache_encoded );

        return $cache[ 'data' ];
    }
    
    /**
     * Altera a medida mínima do expires para 1 minuto.
     */
    private static function turnExpiresToMinutes( $expires )
    {
        return (int) $expires * 60;
    }
    
    /**
     * Cria ou sobrescreve um cache.
     */
    private static function generateData( $key, $data_method_generator )
    {
        if ( !is_callable( $data_method_generator ) )
        {
            $msg = Messager::get( 'cache', 'generator_is_not_callable' );
            throw new ErrorException( $msg );
        }

        $data = $data_method_generator();

        self::set( $key, $data );
        return self::get( $key );
    }
    
    /**
     * Cria ou sobrescreve um cache.
     */
    public static function set( $key, $data, $listen_filename = null )
    {
        $cache_path     = self::path( $key );
        $base_path      = dirname( $cache_path );

        if ( !file_exists( $base_path ) )
        {
            Dir::makeDir( $base_path );
        }
        
        $cache          = [];

        if ( $listen_filename )
        {
            $cache[ 'listen' ]      = $listen_filename;
            $cache[ 'filetime' ]    = file_exists( $listen_filename )
                ? filemtime( $listen_filename )
                : null;
        }
        
        $cache['data']  = $data;
        $cache_encoded  = serialize( $cache );
        $cache_created  = file_put_contents( $cache_path, $cache_encoded );
    }
    
    /**
     * Exclui um arquivo de cache.
     */
    public static function delete( $key )
    {
        $cache_path = self::path( $key );
        return unlink( $cache_path );
    }
    
    /**
     * Exclui os arquivos de cache antigos.
     */
    public static function getPath()
    {
        $path   = DIR_PRODUCTS . '/cache';

        if ( !file_exists( $path ) )
        {
            Dir::makeDir( $path );
        }

        return $path;
    }
    
    /**
     * Exclui os arquivos de cache antigos.
     */
    public static function clear()
    {
        self::clearPath( self::getPath(), true );
    }
    
    /**
     * Exclui os arquivos de cache de uma pasta de cache.
     */
    private static function clearPath( $path, $is_base_path = false )
    {
        $path           = str_replace( '\\', '/', $path );
        $path_obj       = new \FilesystemIterator( $path );
        $delete_path    = true;

        foreach ( $path_obj as $item )
        {
            if ( $item->isDir() )
            {
                self::clearPath( $item->getPathname() );
                continue;
            }

            $filename       = $item->getPathname();
            $filetime       = filemtime( $filename );
            $expired_cache  = time() - $filetime > self::timeExpires();

            if ( $expired_cache )
            {
                unlink( $filename );
                continue;
            }

            $delete_path = false;
        }

        if ( $delete_path && !$is_base_path )
        {
            rmdir( $path );
        }
    }

    /**
     * Retona o caminho do arquivo de um cache.
     */
    public static function path( $key )
    {
        $key        = strtolower( $key );
        $filename   = str_replace( '.', '/', $key ) . '.cache';
        $pathname   = self::getPath() . '/' . $filename;
        
        return $pathname;
    }

    /**
     * Retona o limite para limpeza dos caches.
     */
    public static function timeExpires()
    {
        return 1 * 60 * 60 * 24;
    }
}
