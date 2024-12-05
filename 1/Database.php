<?php

namespace FpDbTest;

use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    private $skip;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;

        $this->skip = new class{};
    }


    public function skip()
    {
        return $this->skip;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $argslen = count($args);
        $thisSkipValue = $this->skip();

        $ret = '';
        $wrk = &$ret;


        $ind = 0;
        $q2b = [];
        $qchars = mb_str_split($query);
        $isInBraces = false;
        $inBracesContent = '';
        $isHasSkipValueInBraces = false;
        for($qind = 0, $qlen = count($qchars); $qind < $qlen; $qind++) {
            $specifier = '';
            $qchar = $qchars[$qind];
            switch ($qchar) {
                case "\\":
                    $wrk .= $qchar;
                    if (($qind+1) < $qlen) {
                        $wrk .= $qchars[$qind+1];
                    }
                    $qind++;
                    break;
                case '"': case "'": case '`':
                    if (!empty($q2b[$qchar])) unset($q2b[$qchar]);
                    else $q2b[$qchar] = true;
                    $wrk .= $qchar;
                    break;
                case '{':
                    if (!empty($q2b)) {
                        $wrk .= $qchar;
                        break;
                    }
                    if (!empty($isInBraces)) throw new \InvalidArgumentException('Detected nested braces');
                    $isInBraces = true;
                    $inBracesContent = '';
                    $isHasSkipValueInBraces = false;
                    $wrk = &$inBracesContent;
                    break;
                case '}':
                    if (!empty($q2b)) {
                        $wrk .= $qchar;
                        break;
                    }
                    if (empty($isInBraces)) throw new \InvalidArgumentException('Unexpected brace');
                    else {
                        if (!$isHasSkipValueInBraces) {
                            $ret .= $inBracesContent;
                        }
                        $wrk = &$ret;
                    }
                    $isInBraces = false;
                    break;
                case "?":
                    if (!empty($q2b)) {
                        $wrk .= $qchar;
                        break;
                    }
                    $specifier .= $qchar;
                    break;
                default:
                    $wrk .= $qchar;
            }
            if (empty($specifier)) {
                continue;
            }

            if (($qind+1) < $qlen) {
                $tmp = $qchars[$qind+1];
                if (in_array($tmp, ['d','f','a','#'], true)) {
                    $specifier .= $tmp;
                    $qind++;
                }
            }

            if ($ind >= $argslen) {
                throw new \InvalidArgumentException("Not enough arguments");
            }
            $arg = $args[$ind];
            $ind++;
            if ($arg === $thisSkipValue) {
                $isHasSkipValueInBraces = true;
                continue;
            }

            switch ($specifier) {
                case '?': $prepared = $this->prepareValue($arg); break;
                case '?d': $prepared = $this->prepareDigit($arg); break;
                case '?f': $prepared = $this->prepareFloat($arg); break;
                case '?a': $prepared = $this->prepareArray($arg); break;
                case '?#': $prepared = $this->prepareNames($arg); break;
                default: throw new \InvalidArgumentException('Unknown specifier "'.$specifier.'"');
            }
            $wrk .= $prepared;

        }

        return $ret;
    }

    public function prepareDigit($digit): string {
        if (!isset($digit))  return 'NULL';
        return strval(intval($digit));
    }

    public function prepareFloat($float): string {
        if (!isset($float)) return 'NULL';
        return strval(floatval($float));
    }

    public function prepareValue($value): string {
        if (!isset($value)) return 'NULL';

        $type = gettype($value);
        switch ($type) {
            case 'NULL': return 'NULL';
            case 'string': return "'".$this->mysqli->real_escape_string($value)."'";
            case 'integer': case 'boolean': return $this->prepareDigit($value);
            case 'double': return $this->prepareFloat($value);
            default: throw new \InvalidArgumentException('Unexpected value type "'.$type.'"');
        }
    }

    public function prepareArray($arr): string {
        if (!isset($arr)) return '';
        if (!is_array($arr)) throw new \InvalidArgumentException('Expected array argument, got "'.gettype($arr).'"');
        if (empty($arr)) return '';
        $ret = [];
        $isAssoc = null;
        foreach ($arr as $key => $value) {
            if (is_int($key)) {
                if (isset($isAssoc) AND ($isAssoc !== false)) {
                    throw new \InvalidArgumentException("Expected full non-associative array");
                }
                $isAssoc = false;
                $ret[] = $this->prepareValue($value);
            } else {
                if (isset($isAssoc) AND ($isAssoc !== true)) {
                    throw new \InvalidArgumentException("Expected full associative array");
                }
                $isAssoc = true;
                $ret[] = $this->prepareNames($key).' = '.$this->prepareValue($value);
            }
        }
        return implode(', ', $ret);
    }


    public function prepareNames($names): string {
        if (empty($names)) return '';
        if (!is_array($names)) $names = [$names];
        $ret = [];
        foreach ($names as $ind => $name) {
            $ret[] = '`'.$this->mysqli->real_escape_string($name).'`';
        }
        return implode(', ', $ret);
    }

}
