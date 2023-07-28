<?php

namespace JF\HTML\Elements;

/**
 * Elemento HTML básico.
 */
trait Element_Trait
{
    /**
     * Conteúdo do elemento.
     */
    public $content;

    /**
     * Estilo CSS.
     */
    public $css = [];

    /**
     * Método construtor.
     */
    public static function make( ...$content )
    {
        $instance = new static();
        $instance->content = $content
            ? implode( '', $content )
            : '';

        return $instance;
    }

    /**
     * Define o estilo CSS.
     */
    public function css( ...$css )
    {
        $this->css += $css;

        return $this;
    }

    /**
     * Adiciona propriedades específicas do elemento na montagem do HTML.
     */
    protected function mount()
    {
        return [];
    }

    /**
     * Monta a TAG de abertura do elemento.
     */
    public function open()
    {
        $tag    = static::$tag;
        $css    = [];

        foreach ( $this->css as $style )
            $css[] = CSS::get( $style );

        $css    = $css
            ? ["css='" . implode( ';', $css ) . "'"]
            : [];

        $props  = implode( ' ', array_merge( $css, $this->mount() ) );
        $html   = $props
            ? "<$tag $props>"
            : "<$tag>";
        
        return $html;
    }

    /**
     * Renderiza o elemento HTML.
     */
    public function html()
    {
        return $this->open() . $this->content . $this->close();
    }

    /**
     * Monta a TAG de fechamento do HTML.
     */
    public function close()
    {
        $tag    = static::$tag;
        
        return "</$tag>";
    }
}
