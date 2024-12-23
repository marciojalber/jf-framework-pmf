<?php

PHPCompiler::run();

class PHPCompiler
{
	/**
	 * Arquivo de destino do PHAR e classe do stub.
	 */
	protected static $pharTarget = [
		['jfc', 'Terminal.php'],
		['jf-pmf', 'App.php']
	];

	/**
	 * Arquivo PHAR.
	 */
	protected $phars = [];

	/**
	 * Pasta de origem dos arquivos.
	 */
	protected $basepath;

	/**
	 * Pasta de destino dos arquivos.
	 */
	protected $targetpath;

	/**
	 * Executa a compilação
	 */
	public static function run()
	{
		$ini 				= date( 'd/m/Y H:i:s' );
		$antes 				= microtime(1);
		$instance 			= new self();
		
		$instance->prepareProcess();
		$instance->clearPath( __DIR__ . '/dist' );
		$instance->addPath( $instance->basepath );

		foreach ( self::$pharTarget as $pharTarget )
		{
			$file_prefix 	= $pharTarget[0];
			$stub 			= $pharTarget[1];
			$filename 		= $file_prefix . '-' . $instance->now . '.phar';
			$phar 			= new \Phar( __DIR__ . '/dist/' . $filename, 0 );
			
			$instance->compile( $phar, $stub );
		}

		$instance->clearPath( $instance->targetpath, 1 );

		$fim 				= date( 'd/m/Y H:i:s' );
		$duracao 			= round( microtime(1) - $antes, 2 );

		header( 'Content-Type: application/json' );
		echo 'ini     : ' . $ini . PHP_EOL;
		echo 'fim     : ' . $fim . PHP_EOL;
		echo "duracao : $duracao segundo(s)";
	}

	/**
	 * Prepara o processo.
	 */
	public function prepareProcess()
	{
        date_default_timezone_set( 'America/Sao_Paulo' );
		
		$this->now 			= date( 'Ymd-His' );
		$this->basepath 	= str_replace( '\\', '/', __DIR__ . '/src' );
		$this->targetpath 	= str_replace( '\\', '/', __DIR__ . '/_src' );

		if ( !file_exists( $this->basepath ) )
			throw new \Exception( "Pasta [$this->basepath] não encontrada." );
	}

	/**
	 * Executa a compilação.
	 */
	public function compile( $phar, $stub )
	{
		$phar->canCompress( 1 );
		$phar->compressFiles( \Phar::GZ );
		$phar->startBuffering();
		$phar->buildFromDirectory( $this->targetpath );
		$phar->stopBuffering();

		$def_stub = $phar->createDefaultStub( $stub );
		$phar->setStub( $def_stub );
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
	protected function clearPath( $path, $remove_dir = 0 )
	{
		$dir = new \FilesystemIterator( $path );

		foreach ( $dir as $item )
		{
			$item->isDir()
				? $this->clearPath( $item->getPathname(), 1 )
				: unlink( $item->getPathname() );
		}

		$remove_dir && rmdir( $path );
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
