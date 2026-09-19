<?php

declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../Petrovich.php';
require_once __DIR__ . '/../Trait/Petrovich.php';

$count = 0;
function same($expected, $actual, string $label = ''): void
{
    global $count;
    ++$count;
    if ($expected !== $actual) {
        throw new RuntimeException("$label: expected " . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function raises(string $class, callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        same(true, $e instanceof $class, get_class($e));
        return;
    }
    throw new RuntimeException("Expected $class");
}

$male = new Petrovich(Petrovich::GENDER_MALE);
$female = new Petrovich(Petrovich::GENDER_FEMALE);
$unknown = new Petrovich();
mb_internal_encoding('ISO-8859-1'); // Library must always use explicit UTF-8.
same(-1, Petrovich::CASE_NOMINATIVE);
same(Petrovich::CASE_NOMINATIVE, Petrovich::CASE_NOMENATIVE);
foreach (['Лёши', 'Лёше', 'Лёшу', 'Лёшей', 'Лёше'] as $case => $expected) {
    same($expected, $male->firstname('Лёша', $case));
}
foreach (['firstname' => 'Александр', 'lastname' => 'Пушкин', 'middlename' => 'Сергеевич'] as $method => $name) {
    same($name, $male->$method($name));
    same('Blabla', $male->$method('Blabla', 0));
    raises(InvalidArgumentException::class, fn() => $male->$method('', 0));
    raises(InvalidArgumentException::class, fn() => $male->$method($name, 5));
    raises(InvalidArgumentException::class, fn() => $male->$method($name, -2));
}
same('Илье-Александру', $male->firstname('Илья-Александр', 1));
same('Бонч-Бруевичу', $male->lastname('Бонч-Бруевич', 1));
same('Бончу', $male->lastname('Бонч', 1));
same('Петров Водкину', $male->lastname('Петров Водкин', 1));
same('Самотечнему', $male->lastname('Самотечний', 1));
same('Ивановой-Сидоровой', $female->lastname('Иванова-Сидорова', 1));
same('Борух-Бендитовне', $female->middlename('Борух-Бендитовна', 1));
same('Щусь', $female->lastname('Щусь', 1));
same('Щусю', $male->lastname('Щусь', 1));
same('ИВАНа', $male->firstname('ИВАН', 0)); // Rust preserves stem, appends lowercase modifier.
same('Ивану', $unknown->firstname('Иван', 1));
same('Ивану', (new Petrovich(null))->firstname('Иван', 1));
same(2, $male->detectGender('ПЕТРОВНА'));
same(1, $male->detectGender('Мамед оглы'));
same(2, $male->detectGender('Мамед кызы'));
raises(InvalidArgumentException::class, fn() => $male->detectGender(''));
raises(InvalidArgumentException::class, fn() => new Petrovich(3));
raises(RuntimeException::class, fn() => new Petrovich(1, __DIR__ . '/nonexistent'));
foreach ([
    [null, null, null, 0], ['Иванов', 'Саша', null, 1],
    ['Андрейчук', 'Саша', null, 0], [null, 'Александра', null, 2],
    ['Иванов', 'Александра', null, 0], ['Иванов', 'Иван', 'Петровна', 2],
    ['Склифасовская', 'Александра', '', 2], [null, 'Иван-xyz', null, 0],
    [null, 'xyz-Иван', null, 1], [null, null, 'Степаныч', 0],
] as [$ln, $fn, $mn, $expected]) {
    same($expected, $male->detectGenderByName($ln, $fn, $mn));
}
$model = new class { use Trait_Petrovich; };
same(null, $model->firstname());
$model->lastname = 'Цой';
$model->gender = 1;
same('Цою', $model->lastname(1));
$model->gender = 2;
same('Цой', $model->lastname(1)); // Cached instance must follow gender changes.
$model->firstname = 'Анна';
same('Анны', $model->firstname(0));
$model->middlename = 'Ивановна';
same('Ивановны', $model->middlename(0));

// Custom legacy rule layout, including malformed JSON handling.
$temp = sys_get_temp_dir() . '/petrovich-' . bin2hex(random_bytes(8));
mkdir($temp . '/rules', 0777, true);
try {
    copy(__DIR__ . '/../resources/rules.json', $temp . '/rules/rules.json');
    same('Ивана', (new Petrovich(1, $temp))->firstname('Иван', 0));
    file_put_contents($temp . '/rules/rules.json', '{');
    raises(JsonException::class, fn() => new Petrovich(1, $temp));
    file_put_contents($temp . '/rules/rules.json', '{}');
    raises(UnexpectedValueException::class, fn() => new Petrovich(1, $temp));
} finally {
    unlink($temp . '/rules/rules.json');
    rmdir($temp . '/rules');
    rmdir($temp);
}
if (isset($argv[1])) {
    $dataDir = $argv[1];
    require __DIR__ . '/golden.php';
}
echo "OK: $count checks (PHP " . PHP_VERSION . ")\n";
