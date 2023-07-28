<?php

namespace JF;

use JF\Domain\Perfil;

/**
 * Classe responsável pela execução dos testes automatizados.
 */
class User
{
    /**
     * Armazena o usuário solicitante.
     */
    protected static $user;

    /**
     * Define o usuário solicitante.
     */
    public static function set( $user )
    {
        static::$user = $user;
    }

    /**
     * Define o usuário solicitante.
     */
    public static function get()
    {
        return static::$user;
    }

    /**
     * Checa se o usuário possui uma determinada permissão.
     */
    public static function hasPermission( $search )
    {
        foreach ( $user->getPermissions() as $permission )
        {
            if ( $permission == $search )
            {
                return true;
            }
        }

        foreach ( $user->getPerfils() as $perfil_name )
        {
            $perfil = Perfil::get( $perfil_name );

            if ( $perfil->hasPermission( $search ) )
            {
                return true;
            }
        }

        return false;
    }
}
