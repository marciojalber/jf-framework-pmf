<?php

namespace JF;

/**
 * Captura as mensagens que devem ser dadas no framework.
 */
final class Messager
{
    /**
     * Captura a mensagem indicada.
     */
    public static function get( $context, $msg )
    {
        $basename   = DIR_CORE . '/messages/';
        $filename   = $basename . $context . '.php';
        $messages   = include $filename;
        $args       = array_slice( func_get_args(), 2 );

        if ( !$args )
        {
            return $messages[ $msg ];
        }
        
        array_unshift( $args, $messages[ $msg ] );

        return call_user_func_array( 'sprintf', $args );
    }
}
