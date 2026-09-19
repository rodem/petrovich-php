# Petrovich для PHP 8

Склонение русских имён, фамилий и отчеств и определение пола по ФИО.
Алгоритмы и правила синхронизированы с соседним проектом `petrovich-rs`.
Лицензия MIT.

## Требования и установка

PHP **8.0–8.x**, расширение **mbstring**. Правила JSON включены в
`resources/`: инициализация старого submodule `rules` больше не нужна.
Rust, Bun и YAML-расширение для работы библиотеки не требуются.

Можно подключить `Petrovich.php` напрямую или выполнить `composer install`
в каталоге библиотеки и использовать `vendor/autoload.php`.
Composer classmap включает класс `Petrovich` и trait `Trait_Petrovich`.

## Склонение

```php
require_once 'Petrovich.php';

$p = new Petrovich(Petrovich::GENDER_MALE);
echo $p->firstname('Александр', Petrovich::CASE_GENITIVE); // Александра
echo $p->middlename('Сергеевич', Petrovich::CASE_GENITIVE); // Сергеевича
echo $p->lastname('Пушкин', Petrovich::CASE_GENITIVE);     // Пушкина
echo $p->lastname('Бонч-Бруевич', Petrovich::CASE_DATIVE); // Бонч-Бруевичу
```

Без второго аргумента имя возвращается в именительном падеже.

| Константа | Значение | Падеж |
|---|---:|---|
| `CASE_NOMINATIVE` | -1 | Именительный |
| `CASE_NOMENATIVE` | -1 | Старое написание, сохранено как alias |
| `CASE_GENITIVE` | 0 | Родительный |
| `CASE_DATIVE` | 1 | Дательный |
| `CASE_ACCUSATIVE` | 2 | Винительный |
| `CASE_INSTRUMENTAL` | 3 | Творительный |
| `CASE_PREPOSITIONAL` | 4 | Предложный |

Пол: `GENDER_ANDROGYNOUS = 0`, `GENDER_MALE = 1`, `GENDER_FEMALE = 2`.
По умолчанию пол неизвестен; `null` в конструкторе также означает неизвестный пол.
Склонение **не определяет пол автоматически**.

Обработка UTF-8 не зависит от `mb_internal_encoding`. Правила ищутся без
учёта регистра. Как в Rust, регистр основы сохраняется, а добавляемое
окончание берётся из правил: `ИВАН` → `ИВАНа`, не `ИВАНА`.
Части через дефис обрабатываются отдельно; строка не разбивается по пробелам.

## Определение пола

```php
$p = new Petrovich();
// Старый API по отчеству; поддержка оглы/кызы сохранена.
$gender = $p->detectGender('Петровна'); // GENDER_FEMALE

// Новый API: фамилия, имя, отчество (каждый аргумент необязателен).
$gender = $p->detectGenderByName('Иванов', 'Саша'); // GENDER_MALE
$gender = $p->detectGenderByName(firstname: 'Александра'); // GENDER_FEMALE
$p = new Petrovich($gender);
```

`detectGenderByName()` повторяет Rust `detect_gender`: пустые строки и `null`
не голосуют, распознанное отчество имеет приоритет, затем разрешаются
голоса имени и фамилии. Неоднозначный результат — `GENDER_ANDROGYNOUS`.
Эти методы не меняют пол, установленный в экземпляре.

## Trait

```php
require_once 'Petrovich.php';
require_once 'Trait/Petrovich.php';

class User { use Trait_Petrovich; }

$user = new User();
$user->firstname = 'Александр';
$user->middlename = 'Сергеевич';
$user->lastname = 'Пушкин';
$user->gender = Petrovich::GENDER_MALE;

echo $user->firstname(Petrovich::CASE_GENITIVE);  // Александра
echo $user->lastname(Petrovich::CASE_GENITIVE);   // Пушкина
echo $user->middlename(Petrovich::CASE_GENITIVE); // Сергеевича
```

Изменение `$user->gender` учитывается при следующем склонении.

## Совместимость и ошибки

Сохранены глобальные имена класса и trait, старые методы, числовые константы
и порядок аргументов конструктора. Параметры и возвращаемые значения методов
теперь типизированы. Для входных данных используйте строки UTF-8 и целые
константы падежа/пола; некорректные типы могут вызвать `TypeError`.

Пустое имя в методах класса и пустое отчество в `detectGender()` вызывают
`InvalidArgumentException`, как и неизвестные падеж/пол. Ошибки чтения правил
вызывают `RuntimeException`, некорректный JSON — `JsonException`, неверная
структура правил — `UnexpectedValueException`.

Второй аргумент конструктора по-прежнему позволяет передать каталог с
`rules/rules.json`. Он заменяет только правила склонения; эвристики пола
берутся из встроенного `resources/gender.json`.

Результаты склонения могут отличаться от старой PHP-версии: перенесены
двухпроходный выбор правил по полу, обработка составных имён и актуальные
исключения из Rust. Теги `first_word` игнорируются, как в Rust-эталоне.

## Тесты

```bash
php tests/run.php
# Или:
composer test

# Полный прогон по данным соседней Rust-версии:
php tests/run.php ../petrovich-rs/tests/data
```

Полный прогон проверяет 269 458 строк основных наборов и дополнительные
примеры ФИО. Известные ошибки эвристик сравниваются с `*.allow.tsv` из Rust:
новое расхождение или исчезновение старого проваливает тест.
Проверено на PHP 8.0.30 и 8.5.10 с `E_ALL`.

## Обновление правил (для разработчиков)

```bash
bun tools/sync-rules.ts ../petrovich-rs
php tests/run.php ../petrovich-rs/tests/data
```

Скрипт преобразует `src/rules.yml` и `src/gender.yml` в JSON, сохраняя порядок
правил. Полученные файлы `resources/*.json` следует включать в репозиторий.
Старый submodule `rules` оставлен для совместимости структуры репозитория,
но при обычном использовании не читается.
