<?php

namespace JF\Types;

/**
 * Classe para manipulação de datas e horas.
 */
class DateTime__Type
{
	/**
	 * Valida uma data.
	 */
	public static function validateDate( $value )
	{
        list( $ano, $mes, $dia ) = explode( '-', substr( $value, 0, 10 ) );

        if ( !checkdate( $mes, $dia, $ano ) )
            return false;

        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && strtotime( $value );
	}
	
	/**
	 * Valida uma hora.
	 */
	public static function validateTime( $value )
	{
        return preg_match( '/^\d{2}:\d{2}:\d{2}$/', $value ) && strtotime( $value );
	}
	
	/**
	 * Método para converter uma data do formato SQL para formato de data local.
	 */
	public static function sqlToLocale( $date )
	{
		$date_parts 	= exlode( '-', substr( $date, 0, 10 ) );
		$reversed_date 	= array_reverse( $date_parts );
		$formated_date 	= implode( '/', $reversed_date ) . substr( $date, 10 );

		return $formated_date;
	}
	
	/**
	 * Método para converter uma data do formato local para formato de data SQL.
	 */
	public static function localeToSQL( $date )
	{
		$date_parts 	= exlode( '/', substr( $date, 0, 10 ) );
		$reversed_date 	= array_reverse( $date_parts );
		$formated_date 	= implode( '-', $reversed_date ) . substr( $date, 10 );

		return $formated_date;
	}
}
