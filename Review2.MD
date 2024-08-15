**Ревью варианта 2**

_Database_

```php
public function skip()
{
    return $this->skipper->getSkippedArgument();
}
```
Кажется чрезмерным введение дополнительного класса Skipper.
Можно в конструкторе Database создавать нужный объект для пропуска
и передавать его в билдеры вместо скиппера.

---

_QueryBuilder_

```php
// Гарантируем, что все значения аргументов будут идти по порядку
$args = array_values($args);
```
Лучше уж тогда проверить на `array_is_list` и кинуть исключение, если не так.

---

_ArgumentBuilder_

```php
$replacement = $this->buildArgument($this->format, $args[$this->argumentNumber]);
```
Нет проверки на отсутствие аргумента в массиве `$args`.

---

```php
private function buildAuto(mixed $argument, bool $isArraysAllowed = true): string
{
    if (is_null($argument)) {
        return 'NULL';
    }
    if (is_array($argument)) {
        if (!$isArraysAllowed) {
            throw new TypeError('Array items can not be an array.');
        }
        return $this->buildArray($argument, false);
    }
    if (is_int($argument)) {
        return $this->buildInt($argument);
    }
    if (is_float($argument)) {
        return $this->buildFloat($argument);
    }
    if (is_string($argument)) {
        return sprintf('\'%s\'', str_replace('\'', '\\\'', $argument));
    }
    throw new TypeError('Argument must be integer, float, boolean, string, array or null.');
}
```
Эта функция используется для параметра без спецификатора. 
Соответственно, будет обрабатываться массив, хотя не должен.

Нет обработки булева типа.

Строка экранируется не через `real_escape_string`.

---

```php
private function buildArray(mixed $array, bool $isArraysAllowed = true): string
{
    ...
}
```
`$isArraysAllowed` - ненужная переменная, всегда false по сути.

Внутри не хватает проверок на пустоту и отсутствие чисел в ассоциативных ключах. 

---

```php
$isList = array_keys($array) === range(0, count($array) - 1);
```
Для этого есть функция `array_is_list`.

---

```php
if ($isFirst) {
    $result = $append;
    $isFirst = false;
} else {
    $result = sprintf('%s, %s', $result, $append);
}
```
Как-то сложно. Лучше складывать всё в массив `$append`,
а потом просто склеить `$result = implode(', ', $append)`

---

```php
return sprintf('`%s`', $id);
```
Идентификатор не экранируется.

---

```php
private function buildIdArray(array $id): string
{
    ...
}
```
Не хватает проверки на пустоту массива.

---

```php
private function buildFloat(mixed $number): string
{
    if (is_null($number)) {
        return 'NULL';
    }
    return sprintf('%f', $number);
}
```
Не хватает проверки на `is_numeric`, как и в `buildInt`.
Также `sprintf` по-умолчанию обрежет число до 6 знаков после запятой.

---

_ConditionalBlockBuilder_

```php
public function __construct(
    int $position,
    int $len,
    ArgumentBuilder $argumentReplacer,
    ArgumentBuilder ...$additionalArgumentReplacers)
{
    ...
}
```
Конструктор подразумевает, что в блоке обязательно будет хоть один параметр.
Это лучше проверить уже внутри и выкинуть нормальное исключение.

---

```php
private function buildBlock(Skipper $skipper, string $source, array $args): string
{
    foreach ($this->argumentNumbers as $argumentNumber) {
        if ($skipper->isArgumentSkipped($args[$argumentNumber])) {
            return '';
        }
    }
    ...
}
```
`$this->argumentNumbers` - необязательное свойство.
Номера параметров можно так же получить через `$this->argumentReplacers`.

---

_SimpleQueryParser_

```php
$last = sprintf('%s%s', $char, $query[$position]);
switch ($last) {
    case '/*':
        $skip = true;
        $endComment = '*/';
        break;
    case '--':
        $skip = true;
        $endComment = "\n";
        break;
    case $endComment:
        $skip = false;
        break;
}
if ($skip) {
    continue;
}
```
Полностью отсутствует проверка строковых литералов.

Проверка комментариев, которая и не требовалась, всё равно нерабочая.
Встретив `*/` или `--`, всё, что после, будет считаться комментарием.
Потому что `$char` не обновляется.