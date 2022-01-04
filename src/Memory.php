<?php
/*
 * This file is a part of "comely-io/cache" package.
 * https://github.com/comely-io/cache
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/comely-io/cache/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Comely\Cache;

use Comely\Cache\Exception\CacheException;
use Comely\Cache\Memory\MemoryQuery;

/**
 * Class Memory
 * @package Comely\Cache\Memory
 */
class Memory
{
    /** @var array */
    private array $objects = [];
    /** @var int */
    private int $count = 0;
    /** @var Cache|null */
    private ?Cache $cache = null;
    /** @var \Closure|null */
    private ?\Closure $onCacheException = null;
    /** @var bool */
    private bool $cloneObjectOnCache = true;

    /**
     * @return array
     */
    public function __serialize(): array
    {
        throw new \BadMethodCallException('Memory object cannot be serialized');
    }

    /**
     * @return void
     */
    public function __clone(): void
    {
        throw new \BadMethodCallException('Memory object cannot be cloned');
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            get_called_class(),
            $this->count
        ];
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @param bool|null $cloneObjectOnCache
     * @return $this
     */
    public function options(?bool $cloneObjectOnCache = null): self
    {
        if (is_bool($cloneObjectOnCache)) {
            $this->cloneObjectOnCache = $cloneObjectOnCache;
        }

        return $this;
    }

    /**
     * @param \Closure $closure
     * @return $this
     */
    public function onCacheException(\Closure $closure): self
    {
        $this->onCacheException = $closure;
        return $this;
    }

    /**
     * @param string $key
     * @param string $instanceOf
     * @return MemoryQuery
     */
    public function query(string $key, string $instanceOf): MemoryQuery
    {
        return new MemoryQuery($this, $key, $instanceOf);
    }

    /**
     * @param Cache $cache
     * @return $this
     */
    public function useCache(Cache $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->objects = [];
    }

    /**
     * @param MemoryQuery $query
     * @return mixed returns an object
     */
    public function fetch(MemoryQuery $query): mixed
    {
        // Check in run-time memory
        $object = $this->objects[$query->key] ?? null;
        if (is_object($object) && is_a($object, $query->instanceOf)) {
            return $object;
        }

        // Check in Cache
        if ($this->cache && $query->cache) {
            try {
                $cached = $this->cache->get($query->key);
                if (is_object($cached) && is_a($cached, $query->instanceOf)) {
                    $this->objects[$query->key] = $cached; // Store in run-time memory
                    return $cached;
                }
            } catch (CacheException $e) {
                if ($this->onCacheException) {
                    call_user_func($this->onCacheException, $e);
                }
            }
        }

        // Not found, proceed with callback (if any)
        if (is_callable($query->callback)) {
            $object = call_user_func($query->callback);
            if (is_object($object)) {
                $this->set($query->key, $object, $query->cache, $query->cacheTTL);
                return $object;
            }
        }

        return null;
    }

    /**
     * @param string $key
     * @param object $obj
     * @param bool $cache
     * @param int $ttl
     */
    public function set(string $key, object $obj, bool $cache, int $ttl = 0): void
    {
        // Is a instance?
        if (!is_object($obj)) {
            throw new \UnexpectedValueException('Memory component may only store instances');
        }

        // Store in run-time memory
        $this->objects[$key] = $obj;
        $this->count++;

        // Store in cache?
        if ($this->cache && $cache) {
            try {
                $this->cache->set($key, $this->cloneObjectOnCache ? clone $obj : $obj, $ttl);
            } catch (CacheException $e) {
                if ($this->onCacheException) {
                    call_user_func($this->onCacheException, $e);
                }
            }
        }
    }
}
