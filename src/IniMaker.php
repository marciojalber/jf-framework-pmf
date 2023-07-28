<?php

namespace JF;

/**
 * Cria conteúdo para arquivos .ini.
 */
class IniMaker
{
    /**
     * Conteúdo do arquivo.
     */
    protected $content  = [];

    /**
     * Quantidade padrão de caracteres das chaves.
     */
    protected $keySize  = 15;

    /**
     * Instancia a classe.
     */
    public static function instance()
    {
        $instance = new static();

        return $instance;
    }

    /**
     * Adiciona uma sessão.
     */
    public function addSection( $section )
    {
        $this->content[] = [ 'section', $section ];
    }

    /**
     * Adiciona uma linha.
     */
    public function addLine( $key, $text )
    {
        $this->content[] = [ 'text', $key, $text ];
    }

    /**
     * Retorna o conteúdo em formato de texto.
     */
    public function content()
    {
        $response = [];

        foreach ( $this->content as $content )
        {
            $is_section     = $content[ 0 ] == 'section';
            
            if ( $is_section && !empty( $response ) )
            {
                $response[] = '';
                $response[] = '';
            }

            if ( $is_section )
            {
                $response[] = '[' . $content[ 1 ] . ']';
                $response[] = '';
                continue;
            }

            $key        = str_pad( $content[ 1 ], $this->keySize, ' ' );
            $text       = is_numeric( $content[ 2 ] )
                ? $content[ 2 ]
                : '"' . addslashes( $content[ 2 ] ) . '"';
            $response[] = $key . ' = ' . $text;
        }

        return implode( PHP_EOL, $response ) . PHP_EOL;
    }
}
