<?php

declare(strict_types=1);

trait Trait_Petrovich
{
    // Untyped public properties retained for compatibility with existing models.
    public $firstname;
    public $middlename;
    public $lastname;
    public $gender;

    private ?Petrovich $petrovich = null;
    private ?int $petrovichGender = null;

    private function petrovichInstance(): Petrovich
    {
        $gender = $this->gender ?? Petrovich::GENDER_ANDROGYNOUS;
        if ($this->petrovich === null || $this->petrovichGender !== $gender) {
            $this->petrovich = new Petrovich($gender);
            $this->petrovichGender = $gender;
        }
        return $this->petrovich;
    }

    public function firstname(int $case = Petrovich::CASE_NOMENATIVE): ?string
    {
        if ($case === Petrovich::CASE_NOMENATIVE) {
            return $this->firstname;
        }
        return $this->petrovichInstance()->firstname($this->firstname ?? '', $case);
    }

    public function middlename(int $case = Petrovich::CASE_NOMENATIVE): ?string
    {
        if ($case === Petrovich::CASE_NOMENATIVE) {
            return $this->middlename;
        }
        return $this->petrovichInstance()->middlename($this->middlename ?? '', $case);
    }

    public function lastname(int $case = Petrovich::CASE_NOMENATIVE): ?string
    {
        if ($case === Petrovich::CASE_NOMENATIVE) {
            return $this->lastname;
        }
        return $this->petrovichInstance()->lastname($this->lastname ?? '', $case);
    }
}
