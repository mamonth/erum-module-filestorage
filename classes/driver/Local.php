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

        if( !file_exists( $targetPath ) )
        {
            mkdir( $targetPath, 0777, true );
        }

        $targetPath .= $this->getFilename( $hash );

        copy( $filename, $targetPath );

        return true;
    }

    public function setContent( $hash, $fileContent )
    {

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
}
