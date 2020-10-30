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

    /**
     * CacheProfile constructor.
     * @param $key
     * @param $lifetime
     * @param string[]|null $keyPriorities Priorities with bot key being first entry and auth user key the last
     */
    public function __construct($key, $lifetime, $keyPriorities)
    {
        if (null === $lifetime) {
            throw new \InvalidArgumentException('You need to give a lifetime for the cache.');
        }

        $this->key = $key;
        $this->lifetime = $lifetime;
        $this->keyPriorities = empty($keyPriorities) ? array() : $keyPriorities;
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
}
