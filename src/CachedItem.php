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

use Comely\Cache\Exception\CachedItemException;

/**
 * Class CachedItem
 * @package Comely\Cache
 */
class CachedItem
{
    /** @var string */
    public const SERIALIZED_PREFIX = "~comelyCachedItem";
    /** @var int */
    public const PREFIX_LEN = 17;
    /** @var int */
    public const PLAIN_STRING_MAX_LEN = 128;

    /** @var string */
    public readonly string $key;
    /** @var string */
    public readonly string $type;
    /** @var string|int|float|bool|null */
    public readonly string|int|float|null|bool $value;
    /** @var int */
    public readonly int $storedOn;
    /** @var int|null */
    public ?int $ttl = null;

    /**
     * @param string $key
     * @param object|int|bool|array|string|null $value
     * @param int|null $ttl
     * @return int|string
     */
    public static function Prepare(string $key, object|int|bool|array|string|null $value, ?int $ttl = null): int|string
    {
        if (is_string($value) && strlen($value) <= self::PLAIN_STRING_MAX_LEN) {
            return $value;
        }

        if (is_int($value)) {
            return $value;
        }

        $ser = serialize(new self($key, $value, $ttl));
        $padding = self::PLAIN_STRING_MAX_LEN - strlen($ser);
        if ($padding > 0) {
            $ser .= str_repeat("\0", $padding);
        }

        return self::SERIALIZED_PREFIX . base64_encode($ser);
    }

    /**
     * @param string $stored
     * @return int|string|static
     * @throws CachedItemException
     */
    public static function Decode(string $stored): int|string|self
    {
        if (preg_match('/^-?\d+$/', $stored)) {
            return intval($stored);
        }

        $byteLen = strlen($stored);
        if ($byteLen <= self::PLAIN_STRING_MAX_LEN) {
            return $stored;
        }

        if (substr($stored, 0, self::PREFIX_LEN) !== self::SERIALIZED_PREFIX) {
            return $stored;
        }

        $cachedItem = unserialize(rtrim(base64_decode(substr($stored, self::PREFIX_LEN))));
        if (!$cachedItem instanceof self) {
            throw new CachedItemException(
                'Could not retrieve serialized CachedItem object',
                CachedItemException::UNSERIALIZE_FAIL
            );
        }

        return $cachedItem;
    }

    /**
     * CachedItem constructor.
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     */
    public function __construct(string $key, mixed $value, ?int $ttl = null)
    {
        $this->key = $key;
        $this->type = gettype($value);
        $this->value = match ($this->type) {
            "boolean", "integer", "double", "string", "NULL" => $value,
            "object", "array" => serialize($value),
            default => throw new \UnexpectedValueException(sprintf('Cannot store value of type "%s"', $this->type)),
        };

        $this->ttl = $ttl;
        $this->storedOn = time();
    }

    /**
     * @return int|float|string|bool|array|object|null
     * @throws CachedItemException
     */
    public function getStoredItem(): int|float|string|null|bool|array|object
    {
        if ($this->ttl) {
            $epoch = time();
            if ($this->ttl > $epoch || ($epoch - $this->storedOn) >= $this->ttl) {
                throw new CachedItemException('Cached item has expired', CachedItemException::IS_EXPIRED);
            }
        }

        if (!in_array($this->type, ["array", "object"])) {
            return $this->value;
        }

        $obj = unserialize($this->value);
        if (!$obj) {
            throw new CachedItemException(
                'Failed to unserialize stored ' . $this->type,
                CachedItemException::UNSERIALIZE_FAIL
            );
        }

        return $obj;
    }
}
