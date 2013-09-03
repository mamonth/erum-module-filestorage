<?php
namespace FileStorage\Driver;

/**
 * FileStorage driver for local filesystems
 */
class Local extends \FileStorage\DriverAbstract
{
    protected $localBasePath;

    protected $httpBasePath;

    protected $directoryLevels = 2;

    /**
     * @param array $config
     * @throws \FileStorage\Exception
     */
    public function __construct( array $config )
    {
        parent::__construct( $config );

        // levels of subdirectories at storage
        if( isset( $config['directoryLevels'] ) )
        {
            $this->directoryLevels = (int)$config['directoryLevels'];
        }

        if( isset( $config['path'] ) )
        {
            $this->localBasePath = $config['path'];

            if( !$this->isAvailable() )
            {
                throw new \FileStorage\Exception('Specified path is not available.');
            }
        }
        else
        {
            throw new \FileStorage\Exception('Storage path must be specified at "path" key.');
        }

        if( isset( $config['url'] ) )
        {
            $this->httpBasePath = trim( $config['url'], '/' );
        }
    }

    public function isAvailable()
    {
        return file_exists( $this->localBasePath ) && is_writable( $this->localBasePath );
    }

    public function setFile( $hash, $filename )
    {
        $targetPath = $this->localBasePath . $this->getFileDirectory( $hash );

        $umaskOld = umask(0);


        if( !file_exists( $targetPath ) )
        {
            mkdir( $targetPath, 0777, true );
        }

        $targetPath .= $this->getFilename( $hash );

        copy( $filename, $targetPath );

        chmod( $targetPath, 0777 );

        umask( $umaskOld );

        return true;
    }

    public function setContent( $hash, $fileContent )
    {
        $targetPath = $this->localBasePath . $this->getFileDirectory( $hash );

        $umaskOld = umask(0);

        if( !file_exists( $targetPath ) )
        {
            mkdir( $targetPath, 0777, true );
        }

        $targetPath .= $this->getFilename( $hash );

        error_log( 'Save file to: ' . $this->getFilename( $hash ) . ' len:' . strlen( $fileContent ) . PHP_EOL, 3, '/tmp/upload.log' );

        file_put_contents( $targetPath, (string)$fileContent );

        chmod( $targetPath, 0777 );

        umask( $umaskOld );

        return true;
    }

    public function getFileUrl( $hash )
    {
        return $this->httpBasePath . $this->getFileDirectory( $hash ) . $this->getFilename( $hash );
    }

    public function getFileDirectory( $hash )
    {
        $path = DIRECTORY_SEPARATOR;

        for( $level = 0; $level < $this->directoryLevels; $level++ )
        {
            $path .= substr( $hash, $level * 2, 2 ) . DIRECTORY_SEPARATOR;
        }

        return $path;
    }

    public function getFilename( $hash )
    {
        $extension = \FileStorage::getInfoByHash( $hash, 'extension' );

        return $hash . ( $extension ? '.' . $extension : '' );
    }

    public function getContent( $hash )
    {
        $filePath = $this->localBasePath . $this->getFileDirectory( $hash ) . $this->getFilename( $hash );

        if( !file_exists( $filePath ) ) throw new \FileStorage\Exception('File ' . $hash . ' not found on storage "Local"');

        return file_get_contents( $filePath );
    }
}
