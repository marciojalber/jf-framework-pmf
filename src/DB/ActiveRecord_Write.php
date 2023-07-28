<?php

namespace JF\DB;

use JF\Exceptions\ErrorException;

/**
 * Classe que representa um registro no banco-de-dados.
 */
trait ActiveRecord_Write
{
    /**
     * Insere um novo registro no banco.
     */
    public static function insert( $data, array $opts = array() )
    {
        $record     = new static( $data );
        return self::insertOrAdd( $record->values, $opts );
    }

    /**
     * Insere o registro ativo no banco.
     */
    public function add( array $opts = array() )
    {
        return self::insertOrAdd( $this->values, $opts );
    }

    /**
     * Insere o registro ativo no banco.
     */
    protected static function insertOrAdd( $values, array $opts = array() )
    {
        // Insere o registro no banco
        $unsafe     = true;
        $id_record  = static::prepare( $opts )
            ->insert( $values, $unsafe );

        if ( !empty( $opts[ 'get_sql' ] ) || $id_record === false )
            return $id_record;

        if ( $id_record )
            return static::find( $id_record );

        return;

        self::checkConflit( $values );

        return $values[ static::primaryKey() ];
    }

    /**
     * Insere o registro ativo no banco.
     */
    private static function checkConflit( $data )
    {
        $sql            = static::prepare();
        $props          = static::props();
        $check_conflict = false;

        foreach ( $props as $key => $prop )
        {
            if ( !empty( $prop[ 'unique' ] ) && !empty( $data[ $key ] ) )
            {
                $sql->is( $key, $data[ $key ] );
                $check_conflict = true;
                break;
            }
        }

        if ( !$check_conflict )
            return;

        $has_conflit    = $sql->one();

        if ( $has_conflit )
            throw new ErrorException( 'Tentativa de inclusão de registro duplicado!' );
    }

    /**
     * Atualiza um registro no banco.
     */
    public static function update( $id, $data, array $opts = array() )
    {
        // Captura o registro para aplicar as regras de validação de dados
        $record = static::find( $id, $opts );

        if ( !empty( $opts[ 'get_sql' ] ) )
            return $record;

        if ( !$record )
            return null;

        // Aplica as regras de validação
        $record->set( $data );

        if ( !$record->modified() )
            return 0;

        // Tenta atualizar o registro e retorna o resultado
        $id_name    = static::primaryKey();
        $unsafe     = true;
        $updated    = static::prepare( $opts )
            ->is( $id_name, $id )
            ->set( $record->values )
            ->limit( 1 )
            ->update( $unsafe );

        return $updated;
    }
    
    /**
     * Salva o registro ativo no banco.
     * Cria um novo registro se não existir ou atualiza o registro ativo.
     */
    public function save( array $opts = array() )
    {
        $new_records_status = array( 'created', 'deleted' );
        $is_new_record      = in_array( $this->status, $new_records_status );
        
        if ( $is_new_record )
            return $this->add( $opts );

        $id_name        = static::primaryKey();
        $unsafe         = true;
        $diff_values    = array_intersect_key( $this->values, $this->saved_values );
        $updated        = static::prepare( $opts )
            ->is( $id_name, $this->values[ $id_name ] )
            ->set( $diff_values )
            ->limit( 1 )
            ->update( $unsafe );

        if ( !empty( $opts[ 'get_sql' ] ) )
            return $updated;

        if ( $updated )
            $this->saved_values = array();

        return $updated;
    }

    /**
     * Apaga um registro do banco.
     */
    public static function delete( $id, array $opts = array() )
    {
        $deleted = static::prepare( $opts )
            ->is( static::primaryKey(), $id )
            ->limit( 1 )
            ->delete();

        return $deleted;
    }

    /**
     * Apaga o registro ativo do banco.
     */
    public function remove( array $opts = array() )
    {
        // Certifica que o status do registro permite a atualização
        if ( $this->status === 'created' )
            throw new ErrorException( 'O registro ainda não foi salvo na tabela!' );

        if ( $this->status === 'deleted' )
        {
            $id_name    = static::primaryKey();
            $id         = $this->values[ $id_name ];
            $msg        = "O registro #{$id} já foi excluído da tabela!";
            throw new ErrorException( $msg );
        }

        $id_name    = static::primaryKey();
        $id_val     = $this->values[ $id_name ];
        $deleted    = static::delete( $id_val, $opts );

        // Se excluiu, atualiza o status do registro para excluído
        if ( $deleted )
        {
            $this->status = 'deleted';
        }

        return $deleted;
    }
}
