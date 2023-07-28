<?php

namespace JF;

/**
 * Classe principal do framework
 */
class Utils
{
    /**
     * Método para exportar uma variável.
     */
    public static function getMac()
    {
        return WIN
            ? self::getMacWindows()
            : self::getMacUnix();
    }

    /**
     * Define a pasta de produtos da aplicação.
     */
    public static function getMacWindows()
    {
        $info   = shell_exec( 'ipconfig /all' );
        preg_match( '/Endere.o F.sico.*: (.*)/', $info, $matches );
        $mac    = $matches[ 1 ];
        $mac    = strtolower( str_replace( '-', ':', $mac ) );

        return $mac;
    }

    /**
     * Define a pasta de produtos da aplicação.
     */
    public static function getMacUnix()
    {
        $info   = shell_exec( 'netstat -ie | egrep "..:..:..:..:..:.."' );
        $mac    = $info
            ? substr( $info, -17 )
            : null;

        return $mac;
    }

    /**
     * Método para exportar uma variável.
     */
    public static function var_export( $var = null, $to_php_file = false )
    {
        $var        = var_export( $var, true );
        $response   = preg_replace( '/array \(/', 'array(', $var );
        $response   = preg_replace( '/=> [\r\n] +array\(/', '=> array(', $response );
        $response   = $to_php_file
            ? '<?php' . N . N . 'return ' . $response . ';' . N
            : '';

        return $response;
    }

    /**
     * Remove os caracteres especiais de um texto.
     */
    public static function createDateRange( $active_date, $end, $function = null )
    {
        $dates      = [];
        $date_obj   = new \DateTime( $active_date );

        while ( $active_date <= $end )
        {
            $dates[]        = $function
                ? $function( $date_obj )
                : $active_date;
            $date_obj->add( new \DateInterval( 'P1D' ) );
            $active_date    = $date_obj->format( 'Y-m-d' );
        }

        return $dates;
    }

    /**
     * Remove os caracteres especiais de um texto.
     */
    public static function simpleText( $text )
    {
        $text   = preg_replace( '/[Ä]/', '',            $text );
        $text   = preg_replace( '/[ÀÁÂÃÄ]/',    'A',    $text );
        $text   = preg_replace( '/[ÈÉÊË]/',     'E',    $text );
        $text   = preg_replace( '/[ÌÍÎÏ]/',     'I',    $text );
        $text   = preg_replace( '/[ÒÓÔÕÖ]/',    'O',    $text );
        $text   = preg_replace( '/[ÙÚÛÜ]/',     'U',    $text );
        $text   = preg_replace( '/[Ç]/',        'C',    $text );

        $text   = preg_replace( '/[àáâãäº]/',   'a',    $text );
        $text   = preg_replace( '/[èéêë]/',     'e',    $text );
        $text   = preg_replace( '/[ìíîï]/',     'i',    $text );
        $text   = preg_replace( '/[òóôõöº]/',   'o',    $text );
        $text   = preg_replace( '/[ùúûü]/',     'u',    $text );
        $text   = preg_replace( '/[ç]/',        'c',    $text );

        return $text;
    }
}
