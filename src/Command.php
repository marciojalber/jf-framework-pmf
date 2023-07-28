<?php

namespace JF;

use JF\Exceptions\ErrorException;

/**
 * Classe que representa um comando enviado via linha de comandos.
 */
abstract class Command
{
    /**
     * Separa os elementos dos argumentos informados via linha de comando.
     */
    public static function parseArgs( $args )
    {
        $args           = array_slice( $args, 1 );
        $class_command  = str_replace( '.', '\\', array_shift( $args ) ) . '__Command';

        $args_cmd   = (object) array();
        
        foreach ( $args as $arg )
        {
            $arg            = explode( ':', $arg, 2 );
            $key            = $arg[ 0 ];
            $value          = $arg[ 1 ];
            $args_cmd->$key = $value;
        }

        $response   = (object) array(
            'class' => $class_command,
            'args'  => $args_cmd,
        );

        return $response;
    }

    /**
     * Valida uma classe de comando.
     */
    public static function validateClass( $class )
    {
        $jf_command = 'JF\\Command';

        if ( !class_exists( $class ) )
        {
            $msg = Messager::get( 'command', 'class_not_found', $class ) . N;
            die( $msg );
        }
        
        if ( !is_subclass_of( $class, $jf_command ) )
        {
            $msg = Messager::get( 'command', 'class_not_command', $class, $jf_command ) . N;
            die( $msg );
        }
     
        if ( !method_exists( $class, 'execute' ) )
        {
            $msg = Messager::get( 'command', 'command_not_has_execute', $class ) . N;
            die( $msg );
        }
    }

    /**
     * Imprime uma mensagem formatada na tela.
     */
    public function flash( $msg )
    {
        $msg = preg_replace( '/[Ä]/', '',       $msg );
        $msg = preg_replace( '/[ÀÁÂÃÄ]/', 'A', $msg );
        $msg = preg_replace( '/[ÈÉÊË]/',  'E', $msg );
        $msg = preg_replace( '/[ÌÍÎÏ]/',  'I', $msg );
        $msg = preg_replace( '/[ÒÓÔÕÖ]/', 'O', $msg );
        $msg = preg_replace( '/[ÙÚÛÜ]/',  'U', $msg );
        $msg = preg_replace( '/[Ç]/',     'C', $msg );

        $msg = preg_replace( '/[àáâãä]/', 'a', $msg );
        $msg = preg_replace( '/[èéêë]/',  'e', $msg );
        $msg = preg_replace( '/[ìíîï]/',  'i', $msg );
        $msg = preg_replace( '/[òóôõö]/', 'o', $msg );
        $msg = preg_replace( '/[ùúûü]/',  'u', $msg );
        $msg = preg_replace( '/[ç]/',     'c', $msg );
        echo date( 'Y-m-d H:i:s ' ) . $msg . N;
    }
}
