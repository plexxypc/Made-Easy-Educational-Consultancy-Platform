<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Input types allowed in Service::$customer_fields definitions.
 *
 * "file" answers are stored as order_documents (private disk), not in
 * orders.field_answers; the answer there is just the original filename.
 */
enum CustomerFieldType: string implements HasLabel
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Phone = 'phone';
    case Email = 'email';
    case Select = 'select';
    case File = 'file';

    /**
     * Human-readable label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Short text',
            self::Textarea => 'Long text',
            self::Number => 'Number',
            self::Phone => 'Phone number',
            self::Email => 'Email address',
            self::Select => 'Dropdown (choose one)',
            self::File => 'File upload (PDF or image)',
        };
    }
}
