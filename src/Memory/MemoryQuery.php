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

namespace Comely\Cache\Memory;

use Comely\Cache\Memory;

/**
 * Class MemoryQuery
 * @package Comely\Cache\Memory
 */
class MemoryQuery
{
    /** @var Memory */
    private Memory $memory;
    /** @var string */
    public string $key;
    /** @var string */
    public string $instanceOf;
    /** @var bool */
    public bool $cache = false;
    /** @var int */
    public int $cacheTTL = 0;
    /** @var null|\Closure */
    public ?\Closure $callback = null;

    /**
     * Query constructor.
     * @param Memory $memory
     * @param string $key
     * @param string $instanceOf
     */
    public function __construct(Memory $memory, string $key, string $instanceOf)
    {
        $this->memory = $memory;
        $this->key = $key;
        $this->instanceOf = $instanceOf;
    }

    /**
     * @param int $ttl
     * @return $this
     */
    public function cache(int $ttl = 0): self
    {
        $this->cache = true;
        $this->cacheTTL = $ttl;
        return $this;
    }

    /**
     * @param \Closure $callback
     * @return $this
     */
    public function callback(\Closure $callback): self
    {
        if (is_callable($callback)) {
            $this->callback = $callback;
        }

        return $this;
    }

    /**
     * @param \Closure|null $callback
     * @return object|null
     */
    public function fetch(?\Closure $callback = null): ?object
    {
        if ($callback) {
            $this->callback($callback);
        }

        return $this->memory->fetch($this);
    }
}
