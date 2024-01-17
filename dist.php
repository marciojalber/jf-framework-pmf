<?php

class PHPCompiler
{
	/**
	 * Arquivo de destino do PHAR e classe do stub.
	 */
	protected $pharTarget;

	/**
	 * Arquivo PHAR.
	 */
	protected $phar;

	/**
	 * Arquivo PHAR.
	 */
	protected $basepath;

	/**
	 * Arquivo PHAR.
	 */
	protected $targetpath;

	/**
	 * Executa a compilação
	 */
	public static function init()
	{
		$instance 				= new self();
		$instance->pharTarget 	= $_SERVER[ 'QUERY_STRING' ] == 'jfc'
			? ['jfc.phar', 'Terminal.php']
			: ['jf-php.phar', 'App.php'];
		$phar_filename 			= $instance->pharTarget[0];

		file_exists( $phar_filename ) && unlink( $phar_filename );

		$instance->phar 		= new \Phar( $phar_filename, 0 );
		
		$instance->phar->canCompress( 1 );
		$instance->phar->compressFiles( \Phar::GZ );

		return $instance;
	}

	/**
	 * Executa a compilação
	 */
	public function compile()
	{
		$this->basepath 	= str_replace( '\\', '/', __DIR__ . '/src' );
		$this->targetpath 	= str_replace( '\\', '/', __DIR__ . '/_src' );

		if ( !file_exists( $this->basepath ) )
			throw new \Exception( "Pasta [$this->basepath] não encontrada." );

		$this->phar->startBuffering();
		$this->addPath( $this->basepath );
		$this->phar->buildFromDirectory( $this->targetpath );
		$this->clearPath( $this->targetpath );

		$this->phar->stopBuffering();

		$def_stub = $this->phar->createDefaultStub( $this->pharTarget[1] );
		$this->phar->setStub( $def_stub );

		header( 'Content-Type: application/json' );
		echo 'fim: ' . date( 'd/m/Y H:i:s' );
	}

	/**
	 * Adiciona arquivos compilados de uma pasta.
	 */
	protected function addPath( $path )
	{
		$targetpath = str_replace( $this->basepath, $this->targetpath, $path );

		if ( !file_exists( $targetpath ) )
			mkdir( $targetpath );
		
		$dir = new \FilesystemIterator( $path );

		foreach ( $dir as $item )
		{
			$source = $item->getPathname();

			if ( $item->isDir() )
			{
				$this->addPath( $source );
				continue;
			}

			if ( !$item->isFile() || substr( $source, -4 ) !== '.php' )
				continue;

			$new_filename 	= $targetpath . '/' . $item->getFilename();
			$new_content 	= $this->compress( $source );

			file_put_contents( $new_filename, $new_content );
		}
	}

	/**
	 * Adiciona arquivos compilados de uma pasta.
	 */
	protected function clearPath( $path )
	{
		$dir = new \FilesystemIterator( $path );

		foreach ( $dir as $item )
		{
			$item->isDir()
				? $this->clearPath( $item->getPathname() )
				: unlink( $item->getPathname() );
		}

		rmdir( $path );
	}

	/**
	 * Adiciona arquivos compilados de uma pasta.
	 */
	protected function compress( $src )
	{
	    static $IW = [
	        T_CONCAT_EQUAL,             // .=
	        T_DOUBLE_ARROW,             // =>
	        T_BOOLEAN_AND,              // &&
	        T_BOOLEAN_OR,               // ||
	        T_IS_EQUAL,                 // ==
	        T_IS_NOT_EQUAL,             // != or <>
	        T_IS_SMALLER_OR_EQUAL,      // <=
	        T_IS_GREATER_OR_EQUAL,      // >=
	        T_INC,                      // ++
	        T_DEC,                      // --
	        T_PLUS_EQUAL,               // +=
	        T_MINUS_EQUAL,              // -=
	        T_MUL_EQUAL,                // *=
	        T_DIV_EQUAL,                // /=
	        T_IS_IDENTICAL,             // ===
	        T_IS_NOT_IDENTICAL,         // !==
	        T_DOUBLE_COLON,             // ::
	        T_PAAMAYIM_NEKUDOTAYIM,     // ::
	        T_OBJECT_OPERATOR,          // ->
	        T_DOLLAR_OPEN_CURLY_BRACES, // ${
	        T_AND_EQUAL,                // &=
	        T_MOD_EQUAL,                // %=
	        T_XOR_EQUAL,                // ^=
	        T_OR_EQUAL,                 // |=
	        T_SL,                       // <<
	        T_SR,                       // >>
	        T_SL_EQUAL,                 // <<=
	        T_SR_EQUAL,                 // >>=
	    ];

	    if ( is_file( $src ) )
	    {
	    	$src = file_get_contents( $src );

	        if ( !$src )
	            return false;
	    }

	    $tokens = token_get_all($src);
	    
	    $new 	= "";
	    $c 		= sizeof( $tokens );
	    $iw 	= false; // ignore whitespace
	    $ih 	= false; // in HEREDOC
	    $ls 	= "";    // last sign
	    $ot 	= null;  // open tag
	    
	    for($i = 0; $i < $c; $i++)
	    {
	        $token = $tokens[$i];

	        if ( !is_array( $token ) )
	        {
	            if ( ( $token != ";" && $token != ":" ) || $ls != $token )
	            {
	                $new .= $token;
	                $ls = $token;
	            }

	            $iw = true;
	        	continue;
	        }

            list($tn, $ts) = $token; // tokens: number, string, line
            
            $ls 	= "";
            $tname 	= token_name($tn);

            if ( $tn == T_INLINE_HTML )
            {
                $new .= $ts;
                $iw = false;
                continue;
            }

            if ( $tn == T_OPEN_TAG )
            {
                if(strpos($ts, " ") || strpos($ts, "\n") || strpos($ts, "\t") || strpos($ts, "\r")) {
                    $ts = rtrim($ts);
                }
                $ts .= " ";
                $new .= $ts;
                $ot = T_OPEN_TAG;
                $iw = true;
                continue;
            }

            if($tn == T_OPEN_TAG_WITH_ECHO)
            {
                $new .= $ts;
                $ot = T_OPEN_TAG_WITH_ECHO;
                $iw = true;
                continue;
            }

            if ( $tn == T_CLOSE_TAG )
            {
                if ( $ot == T_OPEN_TAG_WITH_ECHO ) {
                    $new = rtrim($new, "; ");
                } else {
                    $ts = " ".$ts;
                }

                $new .= $ts;
                $ot = null;
                $iw = false;
                continue;
            }

            if ( in_array( $tn, $IW ) )
            {
                $new .= $ts;
                $iw = true;
                continue;
            }

            if ( $tn == T_CONSTANT_ENCAPSED_STRING || $tn == T_ENCAPSED_AND_WHITESPACE )
            {
                if($ts[0] == '"') {
                    $ts = addcslashes($ts, "\n\t\r");
                }
                $new .= $ts;
                $iw = true;
                continue;
            }

            if ( $tn == T_WHITESPACE )
            {
                $nt = @$tokens[$i+1];
                if(!$iw && (!is_string($nt) || $nt == '$') && !in_array($nt[0], $IW)) {
                    $new .= " ";
                }
                $iw = false;
                continue;
            }

            if ( $tn == T_START_HEREDOC )
            {
                $new .= "<<<S\n";
                $iw = false;
                $ih = true; // in HEREDOC
                continue;
            }

            if ( $tn == T_END_HEREDOC )
            {
                $new .= "S;";
                $iw = true;
                $ih = false; // in HEREDOC

                for ( $j = $i+1; $j < $c; $j++ )
                {
                    if (is_string( $tokens[$j] ) && $tokens[$j] == ";" ) {
                        $i = $j;
                        break;
                    } else if($tokens[$j][0] == T_CLOSE_TAG) {
                        break;
                    }
                }

                continue;
            }

            if ( $tn == T_COMMENT || $tn == T_DOC_COMMENT )
            {
                $iw = true;
                continue;
            }

            if ( !$ih )
                $ts = $ts;

            $new .= $ts;
            $iw = false;
	    }

	    return $new;
	}
}

PHPCompiler::init()->compile();
