<?php

namespace Hautelook\Solarium\Cache;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class CacheProfile
{
    private $key;
    private $lifetime;
    private $keyPriorities = array();
    private $logQueries = false;
    private $logFilePath = null;

    /**
     * CacheProfile constructor.
     * @param $key
     * @param $lifetime
     * @param string[]|null $keyPriorities Priorities with bot key being first entry and auth user key the last
     */
    public function __construct(
        $key,
        $lifetime,
        $keyPriorities,
        $logQueries = false,
        $logFilePath = null)
    {
        if (null === $lifetime) {
            throw new \InvalidArgumentException('You need to give a lifetime for the cache.');
        }

        if ($logQueries && empty($logFilePath)) {
            throw new \InvalidArgumentException('Please provide the log file path when logging is enabled.');
        }

        $this->key = $key;
        $this->lifetime = $lifetime;
        $this->keyPriorities = empty($keyPriorities) ? array() : $keyPriorities;
        $this->logQueries = $logQueries;
        $this->logFilePath = $logFilePath;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function getLifetime()
    {
        return $this->lifetime;
    }

    /**
     * @return array|string[]
     */
    public function getKeyPriorities()
    {
        return $this->keyPriorities;
    }

    /**
     * @return mixed|bool
     */
    public function getLogQueries()
    {
        return $this->logQueries;
    }

    /**
     * @return mixed|null
     */
    public function getLogFilePath()
    {
        return $this->logFilePath;
    }
}
