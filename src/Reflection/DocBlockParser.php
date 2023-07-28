<?php

namespace JF\Reflection;

/**
 * Classe para gerar documentação da aplicação.
 */
class DocBlockParser
{
    /**
     * Armazena as linhas da documentação.
     */
    protected $lines = [];

    /**
     * Armazena a descrição extraída dos comentários.
     */
    protected $description = null;

    /**
     * Armazena as TAGs extraídas dos comentários.
     */
    protected $tags = null;

    /**
     * TAG ativa em análise.
     */
    protected $tag = null;

    /**
     * Analisa as linhas de uma documentação.
     */
    public static function parse( $doc_comment )
    {
        $clear_pattern      = '@^/\*\*[\n\s\t]*|\*/[\n\s\t]*@';
        $line_pattern       = '@^[\n\s\t]*\*[\s\t]*@';
        $doc_comment        = $doc_comment;
        $doc_comment        = preg_replace( $clear_pattern, '', $doc_comment );
        $lines              = explode( "\n", $doc_comment );

        foreach ( $lines as &$line )
        {
            $line           = trim( preg_replace( $line_pattern, '', $line ) );
        }

        $instance           = new static();
        $instance->lines    = $lines;
        
        return $instance;
    }

    /**
     * Obter a descrição na documentação.
     */
    public function getDescription()
    {
        if ( $this->description !== null )
            return $this->description;

        $desc = [];

        foreach ( $this->lines as $line )
        {
            if ( !$line )
                continue;
            
            if ( $this->lineStartTag( $line ) )
                break;
            
            $desc[] = trim( $line );
        }

        $this->description = implode( ' ', $desc );
        
        return $this->description;
    }

    /**
     * Obter as tags na documentação.
     */
    public function getTAGs( $map = [] )
    {
        if ( is_null( $this->tags ) )
            $this->parseTags();

        if ( !$map )
            return $this->tags;

        $tags = [];

        foreach ( $map as $tag_name => $tag_plural )
        {
            $tag = !empty( $this->tags[ $tag_name ] )
                ? $this->tags[ $tag_name ]
                : null;

            if ( $tag )
            {
                $tags[ $tag_name ] = $tag_plural
                    ? $tag
                    : $tag[ 0 ];
            }

            if ( !$tag )
            {
                $tags[ $tag_name ] = $tag_plural
                    ? []
                    : null;
            }
        }

        return $tags;
    }

    /**
     * Captura as TAGs da documentação.
     */
    protected function parseTags()
    {
        $this->tags = [];

        foreach ( $this->lines as $line )
        {
            if ( !$line )
                continue;
            
            if ( $this->lineStartTag( $line ) )
            {
                if ( $this->tag )
                    $this->addTagContent();

                $name       = preg_replace( '/^@|[\s\t].*/', '', $line );
                $content    = preg_replace( '/^(@[\w\d-]+)?.*?[\s\t]*/', '', $line );
                $this->tag      = [
                    'name'      => $name,
                    'content'   => !is_null( $content ) && $content !== ''
                        ? trim( $content )
                        : $name,
                ];
                continue;
            }
            
            if ( !$this->tag )
                continue;

            $this->tag[ 'content' ]  .= ' ' . trim( $line );
        }

        if ( $this->tag )
            $this->addTagContent();
    }

    /**
     * Adiciona o conteúdo de uma TAG às TAGs.
     */
    protected function addTagContent()
    {
        $name                       = $this->tag[ 'name' ];
        $content                    = $this->tag[ 'content' ];

        if ( empty( $this->tags[ $name ] ) )
            $this->tags[ $name ]    = [];

        $this->tags[ $name ][]      = $content;
    }

    /**
     * Indica se a linha inicia uma nova TAG.
     */
    protected function lineStartTag( $line )
    {
        return substr( $line, 0, 1 ) === '@';
    }
}
