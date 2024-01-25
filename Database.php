<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private readonly mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        if (empty($query)) {
            throw new Exception('Empty query template.');
        }

        $index = 0;

        // заменяем места вставки значений в шаблоне
        $query = preg_replace_callback('/\?([df#a])?/', function ($matches) use ($args, &$index) {
            // проверяем, что есть параметр для замены
            if (!isset($args[$index]) && !is_null($args[$index])) {
                throw new Exception('Missing parameter for placeholder.');
            }

            $value = $args[$index];

            // проверяем, что значение не равно специальному значению
            if ($value !== $this->skip()) {

                $specifier = $matches[1] ?? null;

                // преобразуем значение в соответствии со спецификатором
                $value = match ($specifier) {
                    'd' => is_null($value) ? 'NULL' : (int)$value,
                    'f' => is_null($value) ? 'NULL' : (float)$value,
                    'a' => $this->formatArray($value),
                    '#' => $this->formatIdentifier($value),
                    default => $this->formatValue($value),
                };
            }

            $index++;

            // возвращаем значение для замены
            return $value;
        }, $query);

        // проверяем, что все параметры были использованы
        if ($index < count($args)) {
            throw new Exception('Too many parameters for query template.');
        }

        // обрабатываем условные блоки в шаблоне
        $query = preg_replace_callback('/\{(.+?)\}/', function ($matches) {
            // проверяем, есть ли в блоке специальное значение
            if (str_contains($matches[1], $this->skip())) {
                // удаляем блок из шаблона
                return '';
            } else {
                // оставляем содержимое блока без скобок
                return $matches[1];
            }
        }, $query);

        // возвращаем сформированный запрос
        return $query;
    }

    public function skip(): string
    {
        // возвращаем специальное значение для пропуска условного блока
        return 'Uncle Albert - Admiral Halsey';
    }

    private function formatValue($value): float|int|string
    {
        if (is_string($value)) {
            // экранируем и заключаем строку в кавычки
            return "'" . $this->mysqli->real_escape_string($value) . "'";
        } elseif (is_int($value) || is_float($value)) {
            // возвращаем число без изменений
            return $value;
        } elseif (is_bool($value)) {
            // приводим булево значение к 0 или 1
            return (int)$value;
        } elseif (is_null($value)) {
            // возвращаем NULL
            return 'NULL';
        } else {
            throw new Exception('Invalid value type.');
        }
    }

    private function formatArray($value): string
    {
        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                // формируем пары идентификатор и значение через запятую
                $pairs = [];
                foreach ($value as $key => $val) {
                    $pairs[] = $this->formatIdentifier($key) . ' = ' . $this->formatValue($val);
                }
                return implode(', ', $pairs);
            } else {
                // формируем список значений через запятую
                $list = [];
                foreach ($value as $val) {
                    $list[] = $this->formatValue($val);
                }
                return implode(', ', $list);
            }
        } else {
            throw new Exception('Invalid array type.');
        }
    }

    private function formatIdentifier($value): string
    {
        if (is_string($value)) {
            // экранируем и заключаем идентификатор в обратные кавычки
            return '`' . str_replace('`', '``', $value) . '`';
        } elseif (is_array($value)) {
            // формируем список идентификаторов через запятую
            $list = [];
            foreach ($value as $val) {
                $list[] = $this->formatIdentifier($val);
            }
            return implode(', ', $list);
        } else {
            throw new Exception('Invalid identifier type.');
        }
    }

    private function isAssoc($array): bool
    {
        // проверяет, является ли массив ассоциативным
        if (is_array($array)) {
            $keys = array_keys($array);
            return array_keys($keys) !== $keys;
        }
        return false;
    }
}
