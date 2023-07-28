<?php

namespace JF\FileSystem;

use JF\Config;

/**
 * Classe que comunica com servidores FTP.
 */
class FTP
{
    /**
     * Propriedade que armazena uma conexão FTP.
     */
    protected $ftp = null;

    /**
     * Método construtor privatizado.
     */
    protected function __construct()
    {
        
    }

    /**
     * Método para criar uma instância do objeto.
     */
    public static function connect( $schema = 'main' )
    {
        $config  = Config::get( 'ftp.' . $schema );
        
        if ( !( $config && $config->hostname && $config->username && $config->password ) )
        {
            return null;
        }
        
        if ( !$ftp = ftp_connect( $config->hostname ) )
        {
            return null;
        }
        
        if ( !ftp_login( $ftp, $config->username, $config->password ) )
        {
            return null;
        }
        
        $instance           = new self();
        $instance->ftp      = $ftp;
        
        return $instance;
    }

    /**
     * Método para salvar um arquivo no servidor FTP.
     */
    public function put( $file_source, $path_target = '', $file_target_name = null )
    {
        // Certifica existência da pasta de destino
        if ( $path_target )
        {
            $folders        = ftp_nlist( $this->ftp, '' );
            
            if ( !in_array( $path_target, $folders ) )
            {
                if ( !ftp_mkdir( $ftp, $path_target ) )
                {
                    return false;
                }
            }
        }
        
        if ( !$file_target_name )
        {
            $file_target_name     = basename( $file_source );
        }

        $fileTarget         = $path_target . '/' . $file_target_name;
        $response           = ftp_put(
            $this->ftp,
            $fileTarget,
            $file_source,
            FTP_BINARY
        );
        
        return $response;
    }

}
