<?php

namespace JF\FileSystem;

use JF\Exceptions\ErrorException;
use JF\Messager;

/**
 * Classe que manipula arquivos CSV.
 */
class FileCSV extends \SplFileObject
{
    /**
     * Rótulos do arquivo.
     */
    protected $labels       = [];

    /**
     * Mapa de nomes dos rótulos.
     */
    protected $map          = [];

    /**
     * Indexação dos rótulos pelos nomes.
     */
    protected $mapIndex     = [];

    /**
     * Define os rótulos do arquivo.
     */
    public function setLabels()
    {
        if ( $this->labels )
            return;

        $this->labels = $this->fgetcsv();
    }

    /**
     * Retorna os rótulos do arquivo.
     */
    public function labels()
    {
        return $this->labels;
    }

    /**
     * Mapea os campos com novos nomes.
     */
    public function setMap( array | object $map = [] )
    {
        $this->map  = (object) $map;

        foreach ( $this->map as $name => $column )
        {
            $pos    = array_search( $column, $this->labels );

            if ( $pos === false )
                continue;

            $this->mapIndex[ $name ] = $pos;
        }
    }

    /**
     * Retorna o mapeamento de campos.
     */
    public function map()
    {
        return $this->map;
    }

    /**
     * Retorna a indexação do mapeamento de campos.
     */
    public function mapIndex()
    {
        return $this->mapIndex;
    }

    /**
     * Verifica se a linha capturada está em branco.
     */
    public function getLine()
    {
        $line   = $this->fgetcsv();
        
        if ( !isset( $line[1] ) && !$line[0] )
            return null;

        if ( !$this->map )
            return $line;

        $result = [];

        foreach ( $this->mapIndex as $name => $pos )
            $result[ $name ] = $line[ $pos ];

        return $result;
    }
}
