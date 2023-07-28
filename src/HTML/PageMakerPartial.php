<?php

namespace JF\HTML;

use JF\Exceptions\ErrorException as Error;
use JF\FileSystem\Dir;
use JF\Messager;

/**
 * Trait da operação para montar a tag script.
 */
trait PageMakerPartial
{
    /**
     * Inclue um fragmento de arquivo HTML.
     */
    public function partial( $filepath, $shared = false )
    {
        if ( !file_exists( DIR_PARTIALS ) )
            Dir::makeDir( DIR_PARTIALS );

        $filename       = strtolower( $filepath );
        $filename       = str_replace( '\\', '/', $filename ) . '.php';
        $context_path   = $shared
            ? DIR_PARTIALS
            : DIR_VIEWS;

        $filename       = $shared
            ? $filename
            : $this->getRealPath( $filename, true );
        $file_path      = $context_path . '/' . $filename;
        $file_partial   = preg_replace( '@.*?templates/html@', '', $file_path );
        $file_caller    = DIR_VIEWS . '/' . $this->route . '/view.php';

        if ( !file_exists( $file_path ) )
        {
            $files  = debug_backtrace();
            $result = [];

            foreach ( $files as $file )
            {
                if ( strpos( $file[ 'file' ], 'templates\html' ) < 1 )
                    continue;
                
                $file[ 'file' ] = str_replace( '\\', '/', $file[ 'file' ] );
                $result[] = preg_replace( '@.*?templates/html/@', '', $file[ 'file' ] ) . ':' . $file[ 'line' ];
            }

            $msg        = "Arquivo [$file_partial] não encontrado -> " . implode( ' | ', $result );
            throw new Error( $msg );
        }

        $this->testIncludindPartialRecursivly( $file_path, $file_caller );

        $this->including[ $file_path ]  = true;
        $this->depends[ $file_partial ] = filemtime( $file_path );
        $local_part                     = &$this->parts;

        foreach ( $this->partsPoint as $part ) {
            $local_part                     = &$local_part[ $part ];
        }

        $local_part[ $file_partial ]    = [];
        $this->partsPoint[]             = $file_partial;

        $path_comment = $shared
            ? 'PARTIALS/' . $filepath
            : 'ROUTE/' . $filepath;

        echo "<!-- PARTIAL START - {$path_comment} -->" . PHP_EOL;
        include $file_path;
        echo "<!-- PARTIAL END - {$path_comment} -->" . PHP_EOL;

        array_pop( $this->partsPoint );
        unset( $this->including[ $file_path ] );
    }

    /**
     * Testa se está tentando chamar o fragmento de página recursivamente.
     */
    public function testIncludindPartialRecursivly( $file_path, $file_caller )
    {
        if ( isset( $this->including[ $file_path ] ) )
        {
            $msg        = Messager::get(
                'html',
                'recursive_request_in_partial',
                $file_caller,
                $file_path
            );
            $msg        = 
                "Erro ao interpretar o arquivo '{$file_caller}': " .
                "solicitação recursiva em '$file_path'!";
            throw new Error( $msg );
        }
    }
}
