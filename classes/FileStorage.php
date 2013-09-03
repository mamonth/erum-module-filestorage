<?php

/**
 * File storage module.
 * Supports multiple storage ( up to 4 storage per file ).
 *
 * @author Andrew Tereshko <andrew.tereshko@gmail.com>
 *
 * @method static \FileStorage factory( $configAlias = null )
 *
 */
class FileStorage extends \Erum\ModuleAbstract
{
    protected static $extensionMap;

    protected static $extensionToCode;

    public static function init()
    {
        // load extension map
        self::$extensionMap = json_decode( file_get_contents( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'extensionMap.json' ) );

        self::$extensionToCode = array_flip( self::$extensionMap );
    }

    /**
     * @var \FileStorage\DriverAbstract[]
     */
    protected $storageList = array();

    /**
     * @param array $config
     * @throws FileStorage\Exception
     */
    public function __construct( array $config )
    {
        if( !isset( $config['storageList'] ) )
        {
            throw new \FileStorage\Exception('Storage list is undefined.');
        }

        foreach( $config['storageList'] as $storageConfig )
        {
            $storage = \FileStorage\DriverAbstract::factory( $storageConfig );
            $this->storageList[ $storage->getId() ] = $storage;
        }

    }

    /**
     * @param $filename
     * @param null $extension
     * @param bool $move
     * @internal param $fileName
     * @return string
     */
    public function setFile( $filename, $extension = null, $move = false )
    {
        if( empty( $extension ) )
        {
            $extPos = strripos( $filename, '.' );

            if( $extPos )
            {
                $extension = substr( $filename, $extPos );
            }
        }

        $extension      = trim( $extension, '. ' );
        $storageList    = array();

        foreach( $this->storageList as $storage )
        {
            if( $storage->isEnabled() && $storage->isAvailable() )
            {
                $storageList[ $storage->getId() ] = $storage;
            }
        }

        $fileHash = self::getFileHash( $filename, $extension, array_keys( $storageList ) );

        foreach( $storageList as $storage )
        {
            $storage->setFile( $fileHash, $filename );
        }

        return $fileHash;
    }

    /**
     * @param $fileContent
     * @param $extension
     * @internal param $fileName
     * @return string
     */
    public function setContent( $fileContent, $extension )
    {
        $extension      = trim( $extension, '. ' );
        $storageList    = array();

        foreach( $this->storageList as $storage )
        {
            if( $storage->isEnabled() && $storage->isAvailable() )
            {
                $storageList[ $storage->getId() ] = $storage;
            }
        }

        $fileHash = self::getContentHash( $fileContent, $extension, array_keys( $storageList ) );

        foreach( $storageList as $storage )
        {
            $storage->setContent( $fileHash, $fileContent );
        }

        return $fileHash;
    }

    public function getFileUrl( $hash )
    {
        //find out file storages
        $storages = self::getInfoByHash( $hash, 'storages' );

        // pick first available
        foreach( $storages as $storageId )
        {
            if( !isset( $this->storageList[ $storageId ] ) ) continue;

            if( !$this->storageList[ $storageId ]->isEnabled() || !$this->storageList[ $storageId ]->isAvailable()  ) continue;

            $storage = $this->storageList[ $storageId ];

            break;
        }

        return $storage->getFileUrl( $hash );
    }

    public function getFilePath( $hash )
    {

    }

    public function getFileContent( $hash )
    {
        //find out file storages
        $storages = self::getInfoByHash( $hash, 'storages' );

        // pick first available
        foreach( $storages as $storageId )
        {
            if( !isset( $this->storageList[ $storageId ] ) ) continue;

            if( !$this->storageList[ $storageId ]->isEnabled() || !$this->storageList[ $storageId ]->isAvailable()  ) continue;

            $storage = $this->storageList[ $storageId ];

            break;
        }

        return $storage->getContent( $hash );
    }

    /**
     * Generate unique file hash
     *
     * HashMap (pos:length):
     * 0:32 - unique file hash ( md5 string )
     * 32:8 - crc32 from md5_file
     * 40:8 - file storage ids ( 2hex per storage, first available is primary )
     * 48:2 - file extension (hex)
     *
     * total length of hash: 50
     *
     * @param string $filename
     * @param string $extension
     * @param array $storageIds
     *
     * @throws FileStorage\Exception
     * @return string
     */
    protected static function getFileHash( $filename, $extension, array $storageIds )
    {
        if( empty( $storageIds ) )
        {
            throw new \FileStorage\Exception('Storage ids can not be empty');
        }

        if( !file_exists( $filename ) )
        {
            throw new \FileStorage\Exception('File "' . $filename . '" not exists or unavailable for reading.');
        }

        $hash       = '';
        $storageIds = array_values( $storageIds );

        // unique file hash, 32ch
        $hash .= md5( microtime( true ) . mt_rand( 0, mt_getrandmax() ) );

        // file crc, 8ch
        $hash .= str_pad( hash_file( 'crc32', $filename ), 8, 0, STR_PAD_LEFT );

        // storage ids in 16bit string 8ch
        // Support up to 4 storage per file for now
        for( $i = 0; $i < 4; $i++ )
        {
            $hash .= isset( $storageIds[ $i ] ) ? str_pad( dechex( $storageIds[ $i ] ), 2, '0', STR_PAD_LEFT ) : '00';
        }

        // extension code in 16bit string. 2ch
        $hash .= str_pad( dechex( self::getExtensionCode( $extension ) ), 2, '0', STR_PAD_LEFT );

        if( strlen( $hash ) !== 50 )
        {
            throw new \FileStorage\Exception( 'Something went wrong, incorrect hash length ' . strlen( $hash ) . ' instead of 50. Hash "' . $hash . '".' );
        }

        return $hash;
    }

    /**
     * Generate unique file hash
     *
     * HashMap (pos:length):
     * 0:32 - unique file hash ( md5 string )
     * 32:8 - crc32 from md5_file
     * 40:8 - file storage ids ( 2hex per storage, first available is primary )
     * 48:2 - file extension (hex)
     *
     * total length of hash: 50
     *
     * @param string $fileContent
     * @param string $extension
     * @param array $storageIds
     *
     * @throws FileStorage\Exception
     * @return string
     */
    protected static function getContentHash( $fileContent, $extension, array $storageIds )
    {
        if( empty( $storageIds ) )
        {
            throw new \FileStorage\Exception('Storage ids can not be empty');
        }

        $hash       = '';
        $storageIds = array_values( $storageIds );

        // unique file hash, 32ch
        $hash .= md5( microtime( true ) . mt_rand( 0, mt_getrandmax() ) );

        // file crc, 8ch
        $hash .= str_pad( hash( 'crc32', $fileContent ), 8, 0, STR_PAD_LEFT );

        // storage ids in 16bit string 8ch
        // Support up to 4 storage per file for now
        for( $i = 0; $i < 4; $i++ )
        {
            $hash .= isset( $storageIds[ $i ] ) ? str_pad( dechex( $storageIds[ $i ] ), 2, '0', STR_PAD_LEFT ) : '00';
        }

        // extension code in 16bit string. 2ch
        $hash .= str_pad( dechex( self::getExtensionCode( $extension ) ), 2, '0', STR_PAD_LEFT );

        if( strlen( $hash ) !== 50 )
        {
            throw new \FileStorage\Exception( 'Something went wrong, incorrect hash length ' . strlen( $hash ) . ' instead of 50. Hash "' . $hash . '".' );
        }

        return $hash;
    }

    /**
     * Extract information from file hash
     *
     * @param string $hash
     * @param string|null $section
     * @return mixed
     * @throws \FileStorage\Exception
     */
    public static function getInfoByHash( $hash, $section = null )
    {
        if( strlen( $hash ) !== 50 )
        {
            throw new \FileStorage\Exception( 'Incorrect hash length ' . strlen( $hash ) . ' instead of 50' );
        }

        if( null !== $section )
        {
            $section = array_flip( (array)$section );
        }

        $info = array();

        if( null === $section || isset( $section[ 'hash' ] ) )
        {
            $info['hash'] = substr( $hash, 0, 32 );
        }

        if( null === $section || isset( $section['crc'] ) )
        {
            $info['crc'] = substr( $hash, 32, 8 );
        }

        if( null === $section || isset( $section['storages'] ) )
        {
            $info['storages'] = array_filter( array_map( 'hexdec', str_split( substr( $hash, 40, 8 ), 2 ) ) );
        }

        if( null === $section || isset( $section['extension'] ) )
        {
            $info['extension'] = self::getExtension( hexdec( (float)substr( $hash, 48, 2 ) ) );
        }

        return sizeof( $info ) === 1 ? current( $info ) : $info;
    }

    /**
     * @param $extension
     * @return string
     */
    protected static function getExtensionCode( $extension )
    {
        $extension      = strtolower( $extension );
        $extensionCode  = 0;

        if( isset( self::$extensionToCode[ $extension ] ) )
        {
            $extensionCode = self::$extensionToCode[ $extension ];
        }

        return $extensionCode;
    }

    protected static function getExtension( $code )
    {
        $code = (int)$code;
        $ext = null;

        if( $code && isset( self::$extensionMap[ $code ] ) )
        {
            $ext = self::$extensionMap[ $code ];
        }

        return $ext;
    }
}
