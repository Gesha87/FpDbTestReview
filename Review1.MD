**Ревью варианта 1**

```php
$argslen = count($args);
```
Именовать переменные лучше в одном стиле (camelCase).

---

```php
$wrk = &$ret;
```
На грани элегантности и костыля) Всё-таки не принято у нас использовать ссылки, не на `С` пишем.

---

```php
$q2b = [];
```
Таких названий лучше избегать, вообще непонятно для чего используется.
В принципе тут много переменных, которые бы по-нормальному назвать
(`$argslen`, `$ind`, `$wrk`, `$ret` и т.д.).

Зачем делать массивом `$q2b`, тоже непонятно: достаточно хранить в переменной
текущую открытую кавычку, чтобы дальше её искать.

---

```php
$qchars = mb_str_split($query);
```
Для разделения на блоки лучше использовать функцию
`preg_split` - она быстрее. Но код надо будет немного переделать.

---

```php
case '"': case "'": case '`':
    if (!empty($q2b[$qchar])) unset($q2b[$qchar]);
    else $q2b[$qchar] = true;
    $wrk .= $qchar;
    break;
```
Неверная обработка строк. 
В итоге, если внутри кавычек будет другой тип кавычек,
и они не закроются, то пропустится весь шаблон до конца,
так как массив `$q2b` будет не пуст.
Например, `SELECT * FROM users WHERE name = "O'Reily" AND block = ?`.

---

```php
$wrk .= $qchar;
```
С точки зрения производительности быстрее добавлять символы
в массив `$chars`, а потом для получения результата склеивать их
с помощью `implode('', $chars)`.

---

```php
$arg = $args[$ind];
$ind++;
```
Можно сразу `$arg = $args[$ind++]`.

---

```php
switch ($specifier) {
    case '?': $prepared = $this->prepareValue($arg); break;
    case '?d': $prepared = $this->prepareDigit($arg); break;
    case '?f': $prepared = $this->prepareFloat($arg); break;
    case '?a': $prepared = $this->prepareArray($arg); break;
    case '?#': $prepared = $this->prepareNames($arg); break;
    default: throw new \InvalidArgumentException('Unknown specifier "'.$specifier.'"');
}
```
Можно заменить на `match`.

---

```php
public function prepareDigit($digit): string {
    if (!isset($digit))  {return 'NULL';}
    return strval(intval($digit));
}
```
На `is_numeric` лучше проверить и кинуть исключение,
если не число. То же самое касается `prepareFloat`.

---

```php
case 'integer': case 'boolean': return $this->prepareDigit($value);
```
Так лучше не писать, `case 'boolean'` теряется.

---

```php
public function prepareArray($arr): string {
    if (!isset($arr)) return '';
```
Тут исключение должно быть, нельзя в массив `null` передавать.

---

```php
public function prepareArray($arr): string {
    if (!isset($arr)) return '';
    if (!is_array($arr)) throw new \InvalidArgumentException('Expected array argument, got "'.gettype($arr).'"');
    if (empty($arr)) return '';
```
Тут тоже лучше исключение, иначе может получиться неверный SQL
`SELECT * FROM users WHERE id IN ()`.

---

```php
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
```
В данном случае массивы вида `[3 => 1, 5 => 'temp']` будут 
считаться валидными и преобразуются в список `[1, 'tmp']`.
Лучше кидать исключение.

Или для определения списка использовать функцию `array_is_list`,
а для ассоциативных массивов уже кидать исключение,
если ключ оказался числом. Хоть число - это и валидное значение для идентификатора,
но вряд ли кто-то реально будет использовать числа в названиях колонок.
Скорее всего это случится по ошибке.

---

```php
public function prepareNames($names): string {
    if (empty($names)) return '';
```
Опять же лучше исключение, чтобы не получился неверный SQL.

---

```php
$ret[] = '`'.$this->mysqli->real_escape_string($name).'`';
```
`real_escape_string` не экранирует обратную кавычку.

---

Плюс не хватает проверок в конце основной функции,
что блок закрыт, и что все строки завершены. 
Если нет, то надо бы исключение кинуть о неверном шаблоне.

---

PSR-12 вообще не применяется. Тут и пробелов между операндами
нет, и `case` могут в одну строчку идти и ещё много всего,
что усложняет чтение кода. И ощущение, что какую-то часть написал
сишник (где все эти $q2b, $qind, &$wrk и т.д.), а потом дописал
PHP-шник (с осмысленными названиями переменных, вроде $isInBraces) :)