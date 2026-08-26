<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case Card = 'card';
    case Boleto = 'boleto';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::Card => 'Cartão',
            self::Boleto => 'Boleto',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pix => 'zap',
            self::Card => 'credit-card',
            self::Boleto => 'barcode',
        };
    }

    /** Regra de validação: in:pix,card,boleto */
    public static function rule(): string
    {
        return 'in:' . implode(',', array_column(self::cases(), 'value'));
    }

    /** ['pix' => 'PIX', ...] para selects e displays */
    public static function labels(): array
    {
        return array_column(
            array_map(fn (self $c) => [$c->value, $c->label()], self::cases()),
            1,
            0
        );
    }

    /** ['pix' => 'zap', ...] */
    public static function icons(): array
    {
        return array_column(
            array_map(fn (self $c) => [$c->value, $c->icon()], self::cases()),
            1,
            0
        );
    }
}
