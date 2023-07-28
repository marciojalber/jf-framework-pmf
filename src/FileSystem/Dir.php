<?php

namespace JF\FileSystem;

/**
 * Classe que manipula diretórios e seus arquivos.
 */
class Dir
{
    /**
     * Exclui todas as pastas, subpastas e arquivos dentro de uma pasta.
     */
    public static function clear( $path )
    {
        $path_is_filesystem_instance    = is_subclass_of( $path, '\\FilesystemIterator' );
        $path_is_splfileinfo_instance   = is_subclass_of( $path, '\\SplFileInfo' );

        if ( !$path_is_filesystem_instance && !$path_is_splfileinfo_instance )
        {
            $path                       = new \FilesystemIterator( $path );
        }

        foreach ( $path as $item )
        {
            if ( is_dir( $item ) )
            {
                $path = new \FilesystemIterator( $item->getPathname() );
                self::clear( $path );
                rmdir( $item );
                continue;
            }
            unlink( $item );
        }
    }

    /**
     * Método para retornar todos os arquivos, links e subpastas de uma pasta.
     */
    public static function getItems( $path, $recursive = false, $as_object = false )
    {
        // Define as variáveis básicas
        $dir    = new \FilesystemIterator( $path );
        $items  = array();

        // Percorre a pasta alvo
        foreach ( $dir as $item )
        {
            $pathname = str_replace( '\\', '/', $item->getPathName() );
            
            if ( $as_object )
            {
                if ( $recursive && $item->isDir() )
                {
                    $item->itens = self::getItems( $pathname, $recursive, $as_object );
                }
                $items[ $pathname ] = clone $item;
                continue;
            }
            
            if ( !$recursive )
            {
                $items[] = $item->getFileName();
                continue;
            }
            
            if ( $item->isDir() )
            {
                $items[ $pathname ] = self::getItems( $pathname, $recursive, $as_object );
                continue;
            }
            $items[ $pathname ] = $pathname;
        }

        // Retorna o resultado
        return $items;
    }
    
    /**
     * Método para obter todos os arquivos de uma pasta.
     */
    public static function getFiles( $path, $recursive = false, $as_object = false )
    {
        // Define as variáveis básicas
        $dir    = new \FilesystemIterator( $path );
        $files  = array();

        // Percorre a pasta alvo
        foreach ( $dir as $file )
        {
            $pathname = str_replace( '\\', '/', $file->getPathName() );
            if ( $as_object )
            {
                if ( $recursive )
                {
                    if ( $file->isDir() )
                    {
                        $files = array_merge(
                            $files,
                            self::getFiles( $pathname, $recursive, $as_object )
                        );
                        continue;
                    }
                    $files[ $pathname ] = clone $file;
                    continue;
                }
                
                if ( !$file->isFile() )
                {
                    continue;
                }
                
                $files[] = clone $file;
                continue;
            }
            
            if ( $recursive )
            {
                if ( $file->isDir() )
                {
                    $files = array_merge(
                        $files,
                        self::getFiles( $pathname, $recursive, $as_object )
                    );
                    continue;
                }
                
                $files[ $pathname ] = $file->getFileName();
                continue;
            }
            
            if ( !$file->isFile() )
            {
                continue;
            }
            
            $files[ $pathname ] = $file->getFileName();
        }

        // Retorna o resultado
        return $files;
    }
    
    /**
     * Método para retornar todas as subpastas de uma pasta.
     */
    public static function getDirs( $path, $as_object = false, $recursive = false )
    {
        $dir        = new \DirectoryIterator( $path );
        $dirs       = array();

        foreach ( $dir as $dir )
        {
            if ( in_array( $dir, array( '.','..' ) ) )
            {
                continue;
            }
            
            if ( !is_dir( $dir->getPathname() ) )
            {
                continue;
            }
            
            if ( $as_object )
            {
                if ( $recursive )
                {
                    $dirs[] = array(
                        'dir' => clone $dir,
                        'dirs' => self::getDirectories( $path . '/' . $dir, true, true ),
                    );
                    continue;
                }
                $dirs[] = clone $dir;
                continue;
            }
            
            if ( $recursive )
            {
                $dirs[] = array(
                    'dir' => $dir->getFileName(),
                    'dirs' => self::getDirectories( $path . '/' . $dir, true ),
                );
                continue;
            }
            
            $dirs[] = $dir->getFileName();
        }
        return $dirs;
    }
    
    /**
     * Método para criar uma pasta dentro da pasta da aplicação.
     */
    public static function makeDir( $path )
    {
        // Se caminho inválido, escapa
        $path = (string) $path;
        
        if ( !$path )
            return -1;

        // Corrige o caminho e separa as subpastas
        $path               = str_replace( '\\', '/', $path );
        $parts_path         = explode( '/', $path );
        $current_path       = str_replace( '\\', '/', DIR_BASE );
        $parts_cur_path     = explode( '/', $current_path );
        $count_parts        = count( $parts_path );
        $count_cur_parts    = count( $parts_path );

        for ( $i = 1; $i <= $count_parts; $i++ )
        {
            if ( $i > $count_cur_parts )
                break;

            $path_part  = implode( '/', array_slice( $parts_path, 0, $i ) );
            $cur_part   = implode( '/', array_slice( $parts_cur_path, 0, $i ) );
            
            if ( $path_part != $cur_part )
                break;
        }

        // Itera com as pastas
        $created_paths  = array();

        for ( $i; $i <= $count_parts; $i++ )
        {
            $path_part  = implode( '/', array_slice( $parts_path, 0, $i ) );
        
            // Se a pasta já existe prossegue
            if ( file_exists( $path_part ) )
                continue;

            // Se não conseguiu cria a pasta, remove as pastas criadas no loop anterior
            // e retorna -1
            if ( !mkdir( $path_part ) )
            {
                foreach ( $created_paths as $createdPath )
                    rmdir( $createdPath );

                return -1;
            }

            // Adiciona histórico de pastas criadas para posterior remoção, caso não consiga concluir
            array_unshift( $created_paths, $path_part );
        }

        // Indica a quantidade de pastas criadas
        return count( $created_paths );
    }

    /**
     * Método para copiar uma pasta de um caminho para outro.
     */
    public static function copy( $source, $target, $recursive = true )
    {
        // Se caminho inválido, escapa
        $source     = (string) $source;
        $source_mod = 
        $target     = (string) $target;
        
        if ( !$source || !$target )
            return;
        
        if ( !file_exists( $target ) )
            mkdir( $target );
        
        $content = self::getItems( $source, true );

        foreach ( $content as $item )
        {
            $itemName = $item->getFileName();
            
            if ( $item->isDir() )
            {
                self::copy( $source . '/' . $itemName, $target . '/' . $itemName );
                continue;
            }
            
            copy( $source . '/' . $itemName, $target . '/' . $itemName );
        }
    }

    /**
     * Método para copiar os arquivos de uma pasta
     */
    public static function copyFiles( $source, $target )
    {
        $source      = (string) $source;
        $target = (string) $target;
        
        if ( !$source || !$target )
        {
            return;
        }
        
        if ( !file_exists( $target ) )
        {
            mkdir( $target );
        }
        
        $files = self::getFiles( $source );

        foreach ( $files as $file )
        {
            copy( $source . '/' . $file, $target . '/' . $file );
        }
        
        return $files;
    }
}
