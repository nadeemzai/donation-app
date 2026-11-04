<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Animals offered for Qurbani. A cow or camel is shared by 7; a goat or sheep is one share. */
enum QurbaniAnimal: string implements HasLabel
{
    case Cow = 'cow';
    case Camel = 'camel';
    case Goat = 'goat';
    case Sheep = 'sheep';

    public function label(): string
    {
        return match ($this) {
            self::Cow => 'Cow',
            self::Camel => 'Camel',
            self::Goat => 'Goat',
            self::Sheep => 'Sheep',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function sharesPerAnimal(): int
    {
        return match ($this) {
            self::Cow, self::Camel => 7,
            self::Goat, self::Sheep => 1,
        };
    }

    /** "Cow (1 of 7 shares)" / "Goat (whole animal)". */
    public function shareLabel(): string
    {
        return $this->sharesPerAnimal() > 1
            ? $this->label().' — 1 of '.$this->sharesPerAnimal().' shares'
            : $this->label().' — whole animal';
    }
}
