<?php
/**
 * This file is a part of "comely-io/cache" package.
 * https://github.com/comely-io/io/cache"
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/comely-io/io/cache/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Comely\Cache;

use Comely\Cache\Exception\CachedItemException;
use Comely\Cache\Exception\CachedItemExpiredException;
use Comely\Utils\Time\Time;

/**
 * Class CachedItem
 * @package Comely\Cache
 */
class CachedItem
{
    /** @var string */
    public $key;
    /** @var string */
    public $dataType;
    /** @var null|string */
    public $instanceOf;
    /** @var bool */
    public $serialized;
    /** @var string|int|float|null|bool */
    public $data;
    /** @var int|null */
    public $size;
    /** @var int|null */
    public $ttl;
    /** @var int */
    public $timeStamp;

    /**
     * CachedItem constructor.
     * @param string $key
     * @param $data
     * @param int|null $ttl
     */
    public function __construct(string $key, $data, ?int $ttl = null)
    {
        $this->key = $key;
        $this->dataType = gettype($data);
        $this->serialized = false;

        switch ($this->dataType) {
            case "boolean":
            case "integer":
            case "double":
            case "string":
            case "NULL":
                $this->data = $data;
                break;
            case "object":
            case "array":
                if ($this->dataType === "object") {
                    $this->instanceOf = get_class($data);
                }

                $this->serialized = true;
                $this->data = base64_encode(serialize($data));
                break;
            default:
                throw new \UnexpectedValueException(sprintf('Cannot store value of type "%s"', $this->dataType));
        }

        $this->size = is_string($this->data) ? strlen($this->data) : null;
        $this->ttl = $ttl;
        $this->timeStamp = time();
    }

    /**
     * @return bool|float|int|mixed|string|null
     * @throws CachedItemException
     * @throws CachedItemExpiredException
     */
    public function get()
    {
        if ($this->ttl) {
            if ($this->ttl > time() || Time::difference($this->timeStamp) >= $this->ttl) {
                throw new CachedItemExpiredException('Cached value has expired');
            }
        }

        if (!$this->serialized) {
            return $this->data;
        }

        $obj = unserialize(base64_decode($this->data));
        if (!$obj) {
            throw new CachedItemException('Failed to unserialize stored ' . $this->dataType);
        }

        return $obj;
    }
}