<?php
namespace FileStorage;

abstract class DriverAbstract
{
    /**
     * @var integer
     */
    protected $storageId;

    /**
     * @var bool
     */
    protected $enabled = true;

    /**
     *
     * @param array $config
     * @throws Exception
     */
    public function __construct( array $config )
    {
        if( !isset( $config['id'] ) )
        {
            throw new Exception('Config must contain unique storage id.');
        }

        if( !is_integer( $config['id'] ) || $config['id'] < 1 )
        {
            throw new Exception('Storage id must be unique unsigned not zero integer.');
        }

        $this->storageId = $config['id'];

        if( isset( $config['enabled'] ) )
        {
            $this->enabled = (boolean)$config['enabled'];
        }
    }

    /**
     * @param array $config
     * @return DriverAbstract
     * @throws Exception
     */
    public static function factory( array $config )
    {
        if( !isset( $config['driver'] ) )
        {
            throw new Exception('Config must contain storage driver.');
        }

        $className = __NAMESPACE__ . '\\Driver\\' . ucfirst( $config['driver'] );

        if( !class_exists( $className ) )
        {
            throw new Exception('Storage driver "' . $className . '" is not exists.');
        }

        return new $className( $config );
    }

    public function getId()
    {
        return $this->storageId;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    abstract public function isAvailable();

    abstract public function setFile( $hash, $filename );

    abstract public function setContent( $hash, $fileContent );
}
