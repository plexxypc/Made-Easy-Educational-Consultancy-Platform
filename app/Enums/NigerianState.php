<?php

namespace App\Enums;

use App\Support\EnumInput;
use Filament\Support\Contracts\HasLabel;

/**
 * The 36 states and the Federal Capital Territory, where an institution is.
 */
enum NigerianState: string implements HasLabel
{
    case Abia = 'abia';
    case Adamawa = 'adamawa';
    case AkwaIbom = 'akwa_ibom';
    case Anambra = 'anambra';
    case Bauchi = 'bauchi';
    case Bayelsa = 'bayelsa';
    case Benue = 'benue';
    case Borno = 'borno';
    case CrossRiver = 'cross_river';
    case Delta = 'delta';
    case Ebonyi = 'ebonyi';
    case Edo = 'edo';
    case Ekiti = 'ekiti';
    case Enugu = 'enugu';
    case Fct = 'fct';
    case Gombe = 'gombe';
    case Imo = 'imo';
    case Jigawa = 'jigawa';
    case Kaduna = 'kaduna';
    case Kano = 'kano';
    case Katsina = 'katsina';
    case Kebbi = 'kebbi';
    case Kogi = 'kogi';
    case Kwara = 'kwara';
    case Lagos = 'lagos';
    case Nasarawa = 'nasarawa';
    case Niger = 'niger';
    case Ogun = 'ogun';
    case Ondo = 'ondo';
    case Osun = 'osun';
    case Oyo = 'oyo';
    case Plateau = 'plateau';
    case Rivers = 'rivers';
    case Sokoto = 'sokoto';
    case Taraba = 'taraba';
    case Yobe = 'yobe';
    case Zamfara = 'zamfara';

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::AkwaIbom => 'Akwa Ibom',
            self::CrossRiver => 'Cross River',
            self::Fct => 'FCT (Abuja)',
            default => ucfirst($this->value),
        };
    }

    /**
     * Read a CSV cell: "Lagos", "lagos state", "Akwa-Ibom", "FCT", "Abuja".
     *
     * @param  string  $input
     * @return self|null Null if not recognised.
     */
    public static function from_input(string $input): ?self
    {
        $key = (string) preg_replace('/_state$/', '', EnumInput::key($input));

        return match ($key) {
            'abuja', 'fct_abuja', 'federal_capital_territory' => self::Fct,
            'nassarawa' => self::Nasarawa,
            default => EnumInput::match(self::cases(), $key),
        };
    }
}
