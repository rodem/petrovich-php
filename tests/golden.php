<?php

// Invoked by run.php with the petrovich-rs/tests/data directory.
declare(strict_types=1);
function rows(string $name): array
{
    global $dataDir;
    $lines = file($dataDir . '/' . $name . '.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    array_shift($lines);
    return $lines;
}
function fields(string $line): array
{
    return array_map(static fn(string $s): string => str_replace(['\\n', '\\t', '\\\\'], ["\n", "\t", '\\'], $s), explode("\t", $line));
}
function parity(string $dataset, array $mismatches, int $total): void
{
    global $dataDir;
    $allowed = is_file($dataDir . '/' . $dataset . '.allow.tsv') ? rows($dataset . '.allow') : [];
    $mismatches = array_values(array_unique($mismatches));
    sort($mismatches);
    sort($allowed);
    same($allowed, $mismatches, $dataset . ' mismatch set');
    same(true, $total > 100, $dataset . ' dataset size');
    echo "$dataset: $total rows, " . count($mismatches) . " known mismatches; parity OK\n";
}
$cases = ['рд' => 0, 'дт' => 1, 'вн' => 2, 'тв' => 3, 'пр' => 4];
$caseNames = ['genitive', 'dative', 'accusative', 'instrumental', 'prepositional'];
$genders = ['мр' => 1, 'жр' => 2, 'мр-жр' => 0];
$genderNames = ['androgynous', 'male', 'female'];
$engines = [0 => $unknown, 1 => $male, 2 => $female];
function grammar(string $grammemes): array
{
    global $cases, $genders;
    $case = $gender = null;
    foreach (explode(',', $grammemes) as $tag) {
        $case ??= $cases[$tag] ?? null;
        if ($tag === 'мр' || $tag === 'жр') {
            $gender ??= $genders[$tag];
        }
    }
    return [$case, $gender];
}
foreach (['firstnames' => 'firstname', 'surnames' => 'lastname', 'midnames' => 'middlename'] as $dataset => $method) {
    $total = 0;
    $mismatches = [];
    foreach (rows($dataset) as $line) {
        [$lemma, $word, $grammemes] = fields($line);
        $tags = explode(',', $grammemes);
        [$case, $gender] = grammar($grammemes);
        if (!in_array('ед', $tags, true) || in_array('0', $tags, true) || $case === null || $gender === null) {
            continue;
        }
        ++$total;
        $actual = mb_strtoupper($engines[$gender]->$method($lemma, $case), 'UTF-8');
        if ($actual !== $word) {
            $mismatches[] = "$dataset:{$caseNames[$case]}:{$genderNames[$gender]}\t$lemma\t$word\t$actual";
        }
    }
    parity($dataset, $mismatches, $total);
}
foreach (['firstnames.gender' => 1, 'surnames.gender' => 0, 'midnames.gender' => 2, 'firstnames.popular.gender' => 1] as $dataset => $index) {
    $total = 0;
    $mismatches = [];
    foreach (rows($dataset) as $line) {
        [$lemma, $tag] = fields($line);
        $parts = [null, null, null];
        $parts[$index] = $lemma;
        $actual = $unknown->detectGenderByName(...$parts);
        ++$total;
        if ($actual !== $genders[$tag]) {
            $mismatches[] = "$lemma\t{$genderNames[$genders[$tag]]}\t{$genderNames[$actual]}";
        }
    }
    parity($dataset, $mismatches, $total);
}
foreach (rows('people.gender') as $line) {
    [$ln, $fn, $mn, $tag] = fields($line);
    same($genders[$tag], $unknown->detectGenderByName($ln, $fn, $mn), $line);
}
foreach (rows('people') as $line) {
    $f = fields($line);
    [$case, $gender] = grammar($f[6]);
    if ($case === null || $gender === null) {
        continue;
    }
    foreach (['lastname', 'firstname', 'middlename'] as $i => $method) {
        if ($f[$i] !== '') {
            same(mb_strtoupper($f[$i + 3], 'UTF-8'), mb_strtoupper($engines[$gender]->$method($f[$i], $case), 'UTF-8'), $line);
        }
    }
}
