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
В принципе тут много переменных, которые бы по-нормальному назвать.
Зачем делать массивом `$q2b`, тоже непонятно: достаточно хранить в переменной
текущую открытую кавычку, чтобы дальше её искать.

---

```php
$qchars = mb_str_split($query);
```
Для разделения на блоки лучше использовать `preg_split` - она быстрее. Но код надо будет немного переделать.

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
если ключ оказался числом.

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