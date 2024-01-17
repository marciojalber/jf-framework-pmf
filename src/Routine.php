<?php

namespace JF;

use JF\Config;
use JF\DB\DB;
use JF\Exceptions\WarningException as Warning;

/**
 * Classe que cuida da execução de rotinas.
 */
class Routine extends \StdClass
{
    /**
     * Rotina ativa.
     */
    protected $active = true;

    /**
     * Minuto da execução.
     */
    protected $min  = '0';

    /**
     * Hora da execução.
     */
    protected $hr   = null;

    /**
     * Dia do mês da execução.
     */
    protected $date = null;

    /**
     * Dia da semana da execução.
     */
    protected $day  = null;

    /**
     * Mês da execução.
     */
    protected $month   = null;

    /**
     * Verifica se a execução da rotina está expirada.
     */
    public function expired( \DateTime $last_exec = null )
    {
        if ( !$last_exec )
            return 'empty';

        $now            = new \DateTime();
        $last_date      = (clone $now)
            ->setDate( $now->format( 'Y' ), $now->format( 'm' ) + 1, 0 )
            ->format( 'd' );
        $range_month    = self::parseRange( $this->month, 1, 12, 'mês' );
        $range_date     = self::parseRange( $this->date, 1, $last_date, 'dia do mês' );
        $range_day      = self::parseRange( $this->day, 1, 7, 'dia da semana' );
        $range_hr       = self::parseRange( $this->hr, 0, 23, 'horas' );
        $range_min      = self::parseRange( $this->min, 0, 59, 'minutos' );

        if ( !in_array( $now->format( 'm' ), $range_month ) )
            return false;

        if ( !in_array( $now->format( 'd' ), $range_date ) )
            return false;

        if ( !in_array( $now->format( 'N' ), $range_day ) )
            return false;

        if ( !in_array( $now->format( 'H' ), $range_hr ) )
            return false;

        if ( $now->format( 'm' ) != $last_exec->format( 'm' ) )
            return 'm';

        if ( $now->format( 'd' ) != $last_exec->format( 'd' ) )
            return 'd';

        if ( $now->format( 'N' ) != $last_exec->format( 'N' ) )
            return 'N';

        if ( $now->format( 'H' ) != $last_exec->format( 'H' ) )
            return 'H';

        foreach ( $range_min as $min )
        {
            if ( $now->format( 'i' ) < $min )
                break;

            if ( $last_exec->format( 'i' ) < $min )
                return 'i';
        }

        return false;
    }

    /**
     * Executa a rotina.
     */
    protected function parseRange( $arg, $min_num, $max_num, $interval )
    {
        $range  = [];

        if ( is_null( $arg ) || $arg == '*' )
            return range( $min_num, $max_num );

        $arg    = explode( ',', $arg );

        foreach ( $arg as $item )
        {
            if ( !str_contains( $item, '-' ) )
            {
                if ( !is_numeric( $item ) )
                    throw new Warning( "O número $item informado para $interval não é um número válido." );

                if ( $item < $min_num )
                    throw new Warning( "O número $item informado para $interval é menor que $min_num." );

                if ( $item > $max_num )
                    throw new Warning( "O número $item informado para $interval é maior que $max_num." );

                $range[] = $item;
                continue;
            }

            $numbers = explode( '-', $item );

            if ( isset( $numbers[2] ) )
                throw new Warning( "Definição de intervalo $item inválido para $interval." );

            if ( !is_numeric( $numbers[0] ) )
                throw new Warning( "O número do início do intervalo $item informado para $interval não é um número válido." );

            if ( $numbers[0] < $min_num )
                throw new Warning( "O número do início do intervalo $item informado para $interval é menor que $min_num." );

            if ( $numbers[0] > $max_num )
                throw new Warning( "O número do início do intervalo $item informado para $interval é maior que $max_num." );

            if ( !is_numeric( $numbers[1] ) )
                throw new Warning( "O número do fim do intervalo $item informado para $interval não é um número válido." );

            if ( $numbers[1] < $min_num )
                throw new Warning( "O número do fim do intervalo $item informado para $interval é menor que $min_num." );

            if ( $numbers[1] > $max_num )
                throw new Warning( "O número do fim do intervalo $item informado para $interval é maior que $max_num." );

            if ( $numbers[0] >= $numbers[1] )
                throw new Warning( "O número do início do intervalo $item informado para $interval deve ser menor que o número do fim." );

            $range = array_merge( $range, range( $numbers[0], $numbers[1] ) );
        }
        
        $range = array_unique( $range );
        sort($range);

        return $range;
    }

    /**
     * Retorna se a rotina está ativa.
     */
    public function active()
    {
        return $this->active;
    }

    /**
     * Executa a rotina.
     */
    public function props()
    {
        return [
            'active'    => $this->active,
            'min'       => $this->min,
            'hr'        => $this->hr,
            'date'      => $this->date,
            'day'       => $this->day,
            'month'     => $this->month,
        ];
    }

    /**
     * Executa a rotina.
     */
    public function process()
    {
        $this->startDbInstance();
        $this->clearForcer();
        $this->registerStep( 'start', ['env' => ENV] );
        $this->execute();
        $this->registerStep( 'end', ['env' => ENV] );
    }

    /**
     * Executa a rotina.
     */
    protected function execute()
    {

    }

    /**
     * Executa a rotina.
     */
    protected function startDbInstance( $step, $extra = [] )
    {
        $schema                     = Config::get( 'logs.executions.schema' );
        $this->registerStart        = date( 'Y-m-d H:i:s' );
        $this->registerDbInstance   = DB::instance( $schema );
    }

    /**
     * Executa a rotina.
     */
    protected function clearForcer()
    {
        $table  = Config::get( 'logs.executions.force' );
        $sql    = "
            DELETE FROM `$table`
            WHERE       `routine` = :routine
        ";
        $data   = [ 'routine' => $this->routineID() ];
        $result = $this->registerDbInstance
            ->execute( $sql, $data )
            ->count();
    }

    /**
     * Executa a rotina.
     */
    protected function registerStep( $step, $extra = [] )
    {
        $routine        = $this->routineID();
        $table          = Config::get( 'logs.executions.table' );
        $now            = new \DateTime();
        $data           = [
            'routine'   => $routine,
            'step'      => $step,
            'date'      => $now->format( 'Y-m-d' ),
            'time'      => $now->format( 'H:i:s' ),
            'duration'  => microtime( true ) - $_SERVER[ 'REQUEST_TIME_FLOAT' ],
            'extra'     => json_encode( (array) $extra ),
        ];
        $sql            = "
            INSERT INTO `$table`(
                `routine`, `step`, `date`, `time`, `duration`, `extra`
            ) VALUES (
                :routine, :step, :date, :time, :duration, :extra
            )
        ";
        $result         = $this->registerDbInstance
            ->execute( $sql, $data )
            ->count();
    }

    /**
     * Retorna o nome da rotina para logs.
     */
    protected function routineID()
    {
        $namespaces = Config::get( 'namespaces' );
        $namespace  = array_search( 'App/Routines', (array) $namespaces ) ?? 'App\\Routines';

        $routine    = preg_replace( "@^{$namespace}\\\(.*?)__Routine$@", '$1', static::CLASS );
        $routine    = str_replace( '\\', '.', $routine );

        return $routine;
    }
}
