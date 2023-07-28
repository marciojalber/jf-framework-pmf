<?php

namespace JF\HTTP;

use JF\Exceptions\InfoException;
use JF\Exceptions\WarningException;
use JF\Exceptions\ErrorException;
use JF\Messager;

/**
 * Classe que manipula requisições HTTP.
 *
 * @author  Márcio Jalber [marciojalber@gmail.com]
 * @since   09/05/2017
 */
class Controller extends \StdClass
{
    /**
     * Define se o método de captura do input será via POST.
     */
    public static $post         = false;
    
    /**
     * Argumentos esperados na requisição.
     */
    public static $expect       = [];

    /**
     * Dados capturados do input.
     */
    public $input               = null;

    /**
     * Variáveis do controller.
     */
    public $data                = [];

    /**
     * Define o charset de retorno.
     */
    public $charset             = null;

    /**
     * Define o nome do arquivo.
     */
    public $filename            = null;

    /**
     * Define o caminho do arquivo.
     */
    public $filepath            = null;

    /**
     * Define o separador para extrações em CSV.
     */
    public $separator           = ',';

    /**
     * Define o encapsulador para extrações em CSV.
     */
    public $enclosure           = '"';
    
    /**
     * Define o mapa de rótulos do arquivo.
     */
    public $csvMap              = [];

    /**
     * Define o ID para respostas a requisições via Server Events.
     */
    public $eventId             = null;

    /**
     * Define o nome para respostas a requisições via Server Events.
     */
    public $eventName           = null;

    /**
     * Método executado antes de todas as ACTIONs.
     */
    public function before() {}

    /**
     * Método executado após a execução de todos os serviços.
     */
    public function after() {}

    /**
     * Cria uma instância da operação.
     */
    public static function run()
    {
        $instance = new static();
        
        return $instance->execute();
    }

    /**
     * Declara o valor para uma variável.
     */
    public function set( $name, $value )
    {
        // Define as variáveis básicas
        $path       = explode( '.', $name );
        $context    = array_shift( $path );
        $value      = json_decode( json_encode( $value ) );

        // Se o contexto da configuração não existe
        if ( !array_key_exists( $context, $this->data ) )
        {
            $this->data[ $context ] = (object) [];
        }

        // Se não informou pelo menos 1 chave de informação para,
        // o tipo de dado da configuração deve ser um array ou objeto
        if ( !$path )
        {
            return $this->data[ $context ] = $value;
        }

        // Define a variável apontada
        $data     = $this->data[ $context ];

        foreach ( $path as $key )
        {
            if ( $last_key )
            {
                $data = $data->$last_key;
            }
            
            if ( !property_exists( $key, $data ) )
            {
                $data->$key = (object) [];
            }
            
            $last_key = $key;
        }
        
        $data->$last_key = json_decode( json_encode( $value ) );
    }
    
    /**
     * Envia resposta de teste à requisição.
     */
    public function info( $msg, array $extra_data = [] )
    {
        $response   = [
            'type'  => 'info',
            'text'  => $msg,
        ];

        return array_merge( $response, $extra_data );
    }
    
    /**
     * Envia resposta de erro à requisição.
     */
    public function error( $msg, array $extra_data = [] )
    {
        $response   = [
            'type'  => 'error',
            'text'  => $msg,
        ];

        return array_merge( $response, $extra_data );
    }
    
    /**
     * Envia resposta de erro à requisição.
     */
    public function warning( $msg, array $extra_data = [] )
    {
        $response   = [
            'type'  => 'warning',
            'text'  => $msg,
        ];

        return array_merge( $response, $extra_data );
    }
    
    /**
     * Envia resposta de erro à requisição.
     */
    public function success( $msg, array $extra_data = [] )
    {
        $response   = [
            'type'  => 'success',
            'text'  => $msg,
        ];

        return array_merge( $response, $extra_data );
    }
    
    /**
     * Retorna o charset do controlador.
     */
    public function charset()
    {
        return $this->charset;
    }
    
    /**
     * Retorna o separador de campos nas respostas em CSV.
     */
    public function separator()
    {
        return $this->separator;
    }
    
    /**
     * Retorna o encapsulador dos campos nas respostas em CSV.
     */
    public function enclosure()
    {
        return $this->enclosure;
    }
    
    /**
     * Retorna o mapeamento dos campos para respostas em CSV.
     */
    public function csvMap()
    {
        return $this->csvMap;
    }
    
    /**
     * Executa a requisição e envia a resposta ou exceção lançada.
     */
    public function wrapper( callable $fn )
    {
        try {
            return $fn();
        }
        catch ( ErrorException $e )
        {
            return $this->error( $e->getMessage() );
        }
        catch ( WarningException $e )
        {
            return $this->warning( $e->getMessage() );
        }
        catch ( InfoException $e )
        {
            return $this->info( $e->getMessage() );
        }
    }
}
