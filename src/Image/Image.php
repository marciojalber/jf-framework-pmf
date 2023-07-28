<?php

namespace JF\Image;

/**
 * Classe para manipulação de imagens.
 */
class Image
{
    /**
     * Nome do arquivo de origem.
     */
    protected $source;

    /**
     * Transformações a serem aplicadas na imagem.
     */
    protected $options  = [];

    /**
     * Tamanho da imagem.
     */
    protected $size     = null;

    /**
     * Cria uma imagem a partir de um base64.
     */
    public static function instance( $source, $base64 = false )
    {
        $instance           = new self();
        $instance->source   = $source;
        /*
        $instance->source   = !$base64
            ? $source
            : 'data://' . substr( $source, 5 );
        */

        return $instance;
    }

    /**
     * Retorna a largura da imagem.
     */
    public function width()
    {
        $size = $this->size();
        
        return $size[ 0 ];
    }

    /**
     * Retorna a altura da imagem.
     */
    public function height()
    {
        $size = $this->size();
        
        return $size[ 1 ];
    }

    /**
     * Valida uma imagem.
     */
    public function size()
    {
        if ( !$this->size )
        {
            $this->size = getimagesize( $this->source );
        }

        return $this->size;
    }

    /**
     * Define os limites de tamanho da imagem.
     */
    public function limitSize( $size )
    {
        $this->options[ 'maxWidth' ]   = $size;
        $this->options[ 'maxHeight' ]  = $size;

        return $this;
    }

    /**
     * Retorna o fator de redução de uma dimensão.
     */
    protected function getReduce()
    {
        $reduce_width   = $this->getReduceDimension( 'width' );
        $reduce_height  = $this->getReduceDimension( 'height' );
        $reduce         = 1;
        $reduce         = is_null( $reduce_width ) || $reduce_width >= $reduce
            ? $reduce
            : $reduce_width;
        $reduce         = is_null( $reduce_height ) || $reduce_height >= $reduce
            ? $reduce
            : $reduce_height;

        return $reduce;
    }

    /**
     * Retorna o fator de redução de uma dimensão.
     */
    protected function getReduceDimension( $dimension )
    {
        $prop   = 'max' . ucfirst( $dimension );

        if ( !isset( $this->options[ $prop ] ) )
        {
            return null;
        }

        $max    = $this->options[ $prop ];
        $size   = $this->$dimension();
        $reduce = $size > $max
            ? round( $max / $size, 2 )
            : 1;

        return $reduce;
    }

    /**
     * Define .
     */
    public function apply()
    {
        $reduce         = $this->getReduce();
        $width          = $this->width();
        $newwidth       = $width * $reduce;
        $height         = $this->height();
        $newheight      = $height * $reduce;
        $thumb          = imageCreateTruecolor( $newwidth, $newheight );
        $source         = imageCreateFromJpeg( $this->source );

        imageCopyResized( $thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height );
        ob_start();
        imagejpeg( $thumb, null, 100 );

        $this->source   = 'data://image/jpeg;base64,' . base64_encode( ob_get_clean() );
    }

    /**
     * Define .
     */
    public function toString()
    {
        return 'data:' . substr( $this->source, 7 );
    }
}


