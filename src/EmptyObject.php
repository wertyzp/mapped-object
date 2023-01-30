<?php

declare(strict_types=1);

namespace Werty\Mapping;

abstract class EmptyObject
{
    protected const T_BOOLEAN = 'boolean';
    protected const T_INTEGER = 'integer';
    protected const T_DOUBLE = 'double';
    protected const T_STRING = 'string';
    protected const T_ARRAY = 'array';
    protected const T_OBJECT = 'object';
    protected const T_RESOURCE = 'resource';
    protected const T_RESOURCE_CLOSED = 'resource (closed)';
    protected const T_UNKNOWN_TYPE = 'unknown type';

    protected const T_SCALAR = [
        self::T_STRING => true,
        self::T_BOOLEAN => true,
        self::T_DOUBLE => true,
        self::T_INTEGER => true
    ];

    protected const TYPE_MAP = [
    ];

    protected const RESTRICTIVE = false;

    public function __construct($dataObject = [])
    {
        $this->fill($dataObject);
    }

    protected function fill($dataObject = [])
    {
        $data = (array) $dataObject;

        $typeMaps = [];
        $class = get_class($this);

        do {
            $typeMaps[] = $class::TYPE_MAP;
        } while ($class = get_parent_class($class));

        $typeMap = array_merge(...array_reverse($typeMaps));

        foreach ($data as $key => $value) {
            if (!isset($typeMap[$key])) {
                if (static::RESTRICTIVE || !empty($this->{$key})) {
                    // skip
                    continue;
                }
                // direct assignment
                $this->{$key} = $value;
                continue;
            }

            $map = $typeMap[$key];

            if (!is_array($map)) {
                $this->{$key} = $this->map($map, $value);
                continue;
            }

            // we've got an array, expected to create an array
            $type = reset($map);
            $typeKey = key($map);
            $this->{$key} = [];

            $scalarMap = isset(self::T_SCALAR[$typeKey]);
            foreach ($value as $subKey => $subValue) {
                if (!$scalarMap) {
                    $this->{$key}[] = $this->map($type, $subValue);
                    continue;
                }
                $this->{$key}[$subKey] = $this->map($type, $subValue);
            }
        }
    }

    private function map($type, $value)
    {
        if (isset(self::T_SCALAR[$type])) {
            settype($value, $type);
        } elseif (is_string($type) && class_exists($type)) {
            $value = new $type($value);
        }
        return $value;
    }

    public function toArray($only = []): array
    {
        $vars = get_object_vars($this);

        return $this->_toArray($vars, $only);
    }

    private function _toArray($array, $only = []): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (!empty($only) && !in_array($key, $only)) {
                continue;
            }

            if ($value instanceof self) {
                $result[$key] = $value->toArray();
                continue;
            }

            if ($value instanceof \stdClass) {
                $result[$key] = $this->_toArray((array) $value);
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->_toArray($value);
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
