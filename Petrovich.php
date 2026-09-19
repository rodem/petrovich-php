<?php

declare(strict_types=1);

/** Russian name inflection; rule selection follows petrovich-rs. */
class Petrovich
{
    public const CASE_NOMINATIVE = -1;
    public const CASE_NOMENATIVE = self::CASE_NOMINATIVE; // Legacy spelling.
    public const CASE_GENITIVE = 0;
    public const CASE_DATIVE = 1;
    public const CASE_ACCUSATIVE = 2;
    public const CASE_INSTRUMENTAL = 3;
    public const CASE_PREPOSITIONAL = 4;

    public const GENDER_ANDROGYNOUS = 0;
    public const GENDER_MALE = 1;
    public const GENDER_FEMALE = 2;

    private const GENDERS = ['androgynous' => 0, 'male' => 1, 'female' => 2];
    private array $rules;
    private array $genderRules;
    private int $gender;

    /** Custom directories retain the old <directory>/rules/rules.json layout. */
    public function __construct(?int $gender = self::GENDER_ANDROGYNOUS, string $rules_dir = __DIR__)
    {
        if (!extension_loaded('mbstring')) {
            throw new RuntimeException('Petrovich requires ext-mbstring.');
        }
        $gender ??= self::GENDER_ANDROGYNOUS;
        if (!in_array($gender, self::GENDERS, true)) {
            throw new InvalidArgumentException('Unknown gender.');
        }
        $this->gender = $gender;
        $path = $rules_dir === __DIR__ ? __DIR__ . '/resources/rules.json' : $rules_dir . '/rules/rules.json';
        $this->rules = self::loadJson($path);
        foreach (['firstname', 'lastname', 'middlename'] as $type) {
            if (!isset($this->rules[$type]) || !is_array($this->rules[$type])
                || !isset($this->rules[$type]['suffixes']) || !is_array($this->rules[$type]['suffixes'])) {
                throw new UnexpectedValueException("Invalid rules for $type.");
            }
            foreach (['exceptions', 'suffixes'] as $kind) {
                $list = $this->rules[$type][$kind] ?? [];
                if (!is_array($list)) {
                    throw new UnexpectedValueException("Invalid rule list for $type.");
                }
                foreach ($list as $rule) {
                    if (!is_array($rule) || !isset($rule['gender']) || !is_string($rule['gender'])
                        || !isset(self::GENDERS[$rule['gender']]) || !isset($rule['test'], $rule['mods'])
                        || !is_array($rule['test']) || !is_array($rule['mods'])
                        || array_keys($rule['mods']) !== [0, 1, 2, 3, 4]) {
                        throw new UnexpectedValueException("Invalid rule for $type.");
                    }
                    foreach (array_merge($rule['test'], $rule['mods']) as $value) {
                        if (!is_string($value)) {
                            throw new UnexpectedValueException('Rule values must be strings.');
                        }
                    }
                }
            }
        }
        $this->genderRules = self::loadJson(__DIR__ . '/resources/gender.json')['gender'];
        foreach ($this->genderRules as &$heuristic) {
            $exceptions = $suffixes = [];
            foreach (self::GENDERS as $key => $value) {
                foreach ($heuristic['exceptions'][$key] ?? [] as $name) {
                    $exceptions[$name] = $value;
                }
                foreach ($heuristic['suffixes'][$key] ?? [] as $suffix) {
                    $suffixes[] = [$suffix, $value];
                }
            }
            // PHP 8 sorting is stable: equal lengths preserve rule order.
            usort($suffixes, static fn(array $a, array $b): int => mb_strlen($b[0], 'UTF-8') <=> mb_strlen($a[0], 'UTF-8'));
            $heuristic = ['exceptions' => $exceptions, 'suffixes' => $suffixes];
        }
        unset($heuristic);
    }

    private static function loadJson(string $path): array
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot read rules: $path");
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new UnexpectedValueException("Rules must be an object: $path");
        }
        return $data;
    }

    public function firstname(string $firstname, int $case = self::CASE_NOMENATIVE): string
    {
        return $this->inflect($firstname, $case, 'firstname');
    }

    public function lastname(string $lastname, int $case = self::CASE_NOMENATIVE): string
    {
        return $this->inflect($lastname, $case, 'lastname');
    }

    public function middlename(string $middlename, int $case = self::CASE_NOMENATIVE): string
    {
        return $this->inflect($middlename, $case, 'middlename');
    }

    private function inflect(string $name, int $case, string $type): string
    {
        if ($case < self::CASE_NOMINATIVE || $case > self::CASE_PREPOSITIONAL) {
            throw new InvalidArgumentException('Unknown grammatical case.');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Name cannot be empty.');
        }
        if ($case === self::CASE_NOMINATIVE) {
            return $name;
        }
        $parts = explode('-', $name);
        $last = count($parts) - 1;
        foreach ($parts as $i => &$part) {
            $lower = mb_strtolower($part, 'UTF-8');
            $rule = $this->findRule($lower, $type, $this->gender, $i === $last)
                ?? $this->findRule($lower, $type, self::GENDER_ANDROGYNOUS, false);
            if ($rule === null || $rule['mods'][$case] === '.') {
                continue;
            }
            $mod = $rule['mods'][$case];
            $skip = substr_count($mod, '-');
            $part = mb_substr($part, 0, max(0, mb_strlen($part, 'UTF-8') - $skip), 'UTF-8')
                . substr($mod, $skip);
        }
        unset($part);
        return implode('-', $parts);
    }

    private function findRule(string $name, string $type, int $gender, bool $known): ?array
    {
        foreach (['exceptions', 'suffixes'] as $kind) {
            foreach ($this->rules[$type][$kind] ?? [] as $rule) {
                $ruleGender = self::GENDERS[$rule['gender']];
                if ($known ? $ruleGender !== $gender : (($ruleGender === self::GENDER_FEMALE) !== ($gender === self::GENDER_FEMALE))) {
                    continue;
                }
                foreach ($rule['test'] as $test) {
                    if ($kind === 'exceptions' ? $name === $test : str_ends_with($name, $test)) {
                        return $rule;
                    }
                }
            }
        }
        return null;
    }

    /** Legacy patronymic-only API, including оглы/кызы. */
    public function detectGender(string $middlename): int
    {
        if ($middlename === '') {
            throw new InvalidArgumentException('Middlename cannot be empty.');
        }
        $lower = mb_strtolower($middlename, 'UTF-8');
        if (str_ends_with($lower, 'оглы')) {
            return self::GENDER_MALE;
        }
        if (str_ends_with($lower, 'кызы')) {
            return self::GENDER_FEMALE;
        }
        return $this->detectGenderByName(middlename: $middlename);
    }

    /** Full-name detection, equivalent to petrovich-rs detect_gender. */
    public function detectGenderByName(?string $lastname = null, ?string $firstname = null, ?string $middlename = null): int
    {
        $ln = $this->genderVote($lastname, 'lastname');
        $fn = $this->genderVote($firstname, 'firstname');
        $mn = $this->genderVote($middlename, 'middlename');
        if ($mn !== null && $mn !== self::GENDER_ANDROGYNOUS) {
            return $mn;
        }
        $votes = array_values(array_unique(array_filter([$ln, $fn, $mn], static fn(?int $v): bool => $v !== null)));
        if (count($votes) > 1) {
            if ($fn !== null && $fn !== self::GENDER_ANDROGYNOUS && $ln === self::GENDER_ANDROGYNOUS) {
                return $fn;
            }
            if ($ln !== null && $ln !== self::GENDER_ANDROGYNOUS && $fn === self::GENDER_ANDROGYNOUS) {
                return $ln;
            }
        }
        return count($votes) === 1 ? $votes[0] : self::GENDER_ANDROGYNOUS;
    }

    private function genderVote(?string $name, string $type): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        $heuristic = $this->genderRules[$type];
        $parts = explode('-', mb_strtolower($name, 'UTF-8'));
        $part = $parts[count($parts) - 1]; // Last hyphen part wins, even if unknown.
        if (isset($heuristic['exceptions'][$part])) {
            return $heuristic['exceptions'][$part];
        }
        foreach ($heuristic['suffixes'] as [$suffix, $gender]) {
            if (str_ends_with($part, $suffix)) {
                return $gender;
            }
        }
        return self::GENDER_ANDROGYNOUS;
    }
}
