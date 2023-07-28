<?php

namespace JF\HTTP;

use JF\Exceptions\ErrorException;
use JF\FileSystem\Dir;
use JF\FileSystem\File;
use JF\HTTP\Router;
use JF\Messager;

/**
 * Classe que recupera dados enviados ao servidor via GET ou POST.
 */
class Input
{
    /**
     * Argumentos da requisição.
     */
    protected static $args = [];

    /**
     * Os argumentos da requisição já foram informados?
     */
    protected static $has_args = false;

    /**
     * Mensagens de erro ocorrido no upload de arquivo.
     */
    public static $uploadErrors = array(
        UPLOAD_ERR_OK           =>
            '',
        UPLOAD_ERR_INI_SIZE     =>
            'O arquivo enviado excede o limite definido no servidor de aplicação',
        UPLOAD_ERR_FORM_SIZE    =>
            'O arquivo excede o limite definido em MAX_FILE_SIZE no formulário HTML',
        UPLOAD_ERR_PARTIAL      =>
            'O upload do arquivo foi feito parcialmente',
        UPLOAD_ERR_NO_FILE      =>
            'Nenhum arquivo foi enviado',
        UPLOAD_ERR_NO_TMP_DIR   =>
            'Pasta temporária ausênte.',
        UPLOAD_ERR_CANT_WRITE   =>
            'Falha em escrever o arquivo em disco',
        UPLOAD_ERR_EXTENSION    =>
            'Uma extensão do servidor de aplicação interrompeu o upload do arquivo',
        'INVALID_IMAGE'         =>
            'O arquivo enviado não é uma imagem válida!',
    );
    

    /****************************************************************************************************
     *
     * INPUT FILE
     *
     ****************************************************************************************************/

    /**
     * Método para realizar upload de arquivos.
     */
    public static function file( $name, $dir = null )
    {
        // Testa se existe o índice de arquivo solicitado
        if ( empty( $_FILES[ $name ] ) )
        {
            return null;
        }

        if ( !$dir )
        {
            $dir = DIR_STORAGE;
        }

        if ( !file_exists( $dir ) )
        {
            Dir::makeDir( $dir );
        }

        if ( !is_writable( $dir ) )
        {
            $msg = Messager::get( 'upload', 'path_is_no_writable' );
            throw new ErrorException( $msg );            
        }

        // Ajusta para sempre trabalhar com array
        $files                      = $_FILES[ $name ];
        
        if ( gettype( $files[ 'name' ] ) !== 'array' )
        {
            $files[ 'name' ]        = [ $files[ 'name' ] ];
            $files[ 'type' ]        = [ $files[ 'type' ] ];
            $files[ 'tmp_name' ]    = [ $files[ 'tmp_name' ] ];
            $files[ 'error' ]       = [ $files[ 'error' ] ];
            $files[ 'size' ]        = [ $files[ 'size' ] ];
        }

        // Inicia variáveis
        $files_count                = count( $files[ 'name' ] );
        $files_ajusted              = array();
        $files                      = (object) $files;

        // Itera com os arquivos
        for ( $counter = 0; $counter < $files_count; $counter++ )
        {
            $files_ajusted[]        = self::formatFileItem( $files, $counter, $dir );
        }

        // Retorna os dados dos arquivos
        return $files_ajusted;
    }    

    /**
     * Método para ajustar o retorno do upload.
     */
    private static function formatFileItem( $files, $counter, $dir )
    {
        // Preenche variável de erro
        $errorCode                      = $files->error[ $counter ];
        
        // Valida um arquivo
        list( $width, $height ) = getimagesize( $files->tmp_name[ $counter ] );
        
        if ( !$width && !$height )
        {
            $errorCode = 'INVALID_IMAGE';
        }
        
        $errorCode                      = $files->error[ $counter ];
        $files->error_message[ $counter ]   = self::$uploadErrors[ $errorCode ];
        
        // Preenche variável de tamanho
        $files->human_size[ $counter ]  = 0;

        $size                           = $files->size[ $counter ];
        $files->human_size[ $counter ]  = File::humanizeFilesize( $size );

        // Preenche variável de exteñsão
        $dotPosition = strrpos( $files->name[ $counter ], '.' );
        $extension   = '';
        
        if ( $dotPosition !== false )
        {
            $extension                  = substr(
                $files->name[ $counter ],
                $dotPosition
            );
        }

        $files->extension[ $counter ]   = substr( $extension, 1 );

        // Preenche variável de arquivo salvo
        $files->saved_path[ $counter ]  = '';
        $files->saved_name[ $counter ]  = '';
        
        $filename                       = uniqid('') . $extension;
        $filesource                     = $files->tmp_name[ $counter ];
        $filetarget                     = $dir . '/' . $filename;

        if ( $dir && move_uploaded_file( $filesource, $filetarget ) )
        {
            $files->saved_path[ $counter ]  = $dir . '/' . $filename;
            $files->saved_name[ $counter ]  = $filename;
        }

        // Preenche array de arquivos
        $files_ajusted              = (object) [
            'error'                 => $files->error[ $counter ],
            'error_message'         => $files->error_message[ $counter ],
            'size'                  => $files->size[ $counter ],
            'human_size'            => $files->human_size[ $counter ],
            'name'                  => $files->name[ $counter ],
            'tmp_name'              => $files->tmp_name[ $counter ],
            'saved_path'            => $files->saved_path[ $counter ],
            'saved_name'            => $files->saved_name[ $counter ],
            'extension'             => $files->extension[ $counter ],
            'type'                  => $files->type[ $counter ],
        ];

        return $files_ajusted;
    }

    /**
     * Método para obter os dados de uma requisição via REQUEST.
     */
    public static function unlink( $files )
    {
        foreach ( $files as $file )
            if ( !$file->error )
                unlink( $file->saved_path );
    }    

    

    /****************************************************************************************************
     *
     * INPUTS PADRÃO
     *
     ****************************************************************************************************/
    
    /**
     * Método para obter os argumentos de uma requisição ou um argumento específico.
     */
    public static function args( $index = null, $default = null, $filter  = null )
    {
        $args = Router::get( 'args' );
        
        if ( !$index )
        {
            return $args;
        }

        if ( !isset( $args[ $index ] ) )
        {
            return $default;
        }

        return $args[ $index ];
    }
    
    /**
     * Método para obter os dados de uma requisição via GET.
     */
    public static function get( $index = null, $default = null, $filter  = null )
    {
        return self::getData( 'get', $index, $default );
    }
    
    /**
     * Método para obter os dados de uma requisição via POST.
     */
    public static function post( $index = null, $default = null, $filter  = null )
    {
        return self::getData( 'post', $index, $default );
    }

    /**
     * Método para obter os dados de uma requisição via REQUEST.
     */
    public static function request( $index = null, $default = null, $filter  = null )
    {
        return self::getData( 'request', $index, $default );
    }

    /**
     * Método para obter os dados da solicitação de uma requisição.
     */
    protected static function getData(
        $method,
        $index      = null,
        $default    = null,
        $filter     = null
    ) {
        switch ( $method )
        {
            case 'post':
                $var        = $_POST;
                $post_json  = json_decode( file_get_contents( 'php://input' ), true );
        
                if ( $post_json )
                    $var    = array_merge( $var, $post_json );
        
                break;
            
            case 'get':
                $var = $_GET;
                break;
            
            case 'request':
                $var = $_REQUEST;
                break;
        }
        
        if ( !$index )
        {
            return $var;
        }
        
        if ( !isset( $var[ $index ] ) )
        {
            return $default;
        }
        
        $response = $var[ $index ];
        
        return $filter
            ? filter_var( $response, $filter )
            : $response;
    }
}
