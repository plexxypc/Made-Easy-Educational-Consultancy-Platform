<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Naira input <-> integer kobo conversion used by the admin forms.
 */
class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{0: string|int|null, 1: int}>
     */
    public static function naira_inputs(): array
    {
        return [
            'whole naira' => ['6500', 650_000],
            'two decimals' => ['6500.50', 650_050],
            'one decimal' => ['6500.5', 650_050],
            'tiny amount (float trap)' => ['0.29', 29],
            'integer input' => [20000, 2_000_000],
            'padded with spaces' => [' 150 ', 15_000],
            'empty' => ['', 0],
            'null' => [null, 0],
        ];
    }

    /**
     * @param  string|int|null  $naira
     * @param  int  $expected_kobo
     * @return void
     */
    #[DataProvider('naira_inputs')]
    public function test_naira_to_kobo(string|int|null $naira, int $expected_kobo): void
    {
        $this->assertSame($expected_kobo, Money::naira_to_kobo($naira));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalid_inputs(): array
    {
        return [
            'negative' => ['-100'],
            'three decimals' => ['1.005'],
            'thousands separator' => ['6,500'],
            'text' => ['abc'],
        ];
    }

    /**
     * @param  string  $naira
     * @return void
     */
    #[DataProvider('invalid_inputs')]
    public function test_invalid_naira_is_rejected(string $naira): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::naira_to_kobo($naira);
    }

    /**
     * @return void
     */
    public function test_kobo_to_naira_and_format(): void
    {
        $this->assertSame('6500', Money::kobo_to_naira(650_000));
        $this->assertSame('6500.50', Money::kobo_to_naira(650_050));
        $this->assertSame('0.05', Money::kobo_to_naira(5));
        $this->assertNull(Money::kobo_to_naira(null));

        $this->assertSame('₦6,500.00', Money::format(650_000));
        $this->assertSame('₦1,234,567.89', Money::format(123_456_789));
        $this->assertSame('₦0.00', Money::format(null));

        $this->assertSame('₦20,000', Money::format(2_000_000, hide_zero_kobo: true));
        $this->assertSame('₦6,500.50', Money::format(650_050, hide_zero_kobo: true));

        $this->assertSame('₦20,000', Money::format(2_000_000, hide_zero_kobo: true));
        $this->assertSame('₦6,500.50', Money::format(650_050, hide_zero_kobo: true));
    }
}
