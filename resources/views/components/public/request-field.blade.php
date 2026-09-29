@props([
    'field',
    'upload_rules',
])
{{-- Renders one Service::$customer_fields entry. Posted as fields[key]; errors are on fields.key. --}}
@php
    $type = \App\Enums\CustomerFieldType::tryFrom((string) ($field['type'] ?? '')) ?? \App\Enums\CustomerFieldType::Text;
    $key = $field['key'];
    $input_id = 'field-'.$key;
    $error_key = 'fields.'.$key;
    $is_required = ! empty($field['required']);
    $has_error = $errors->has($error_key);
    $value = old($error_key);
    $input_classes = 'block w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-brand-500 focus:ring-brand-500 '.($has_error ? 'border-red-500' : '');
    $described_by = trim(($type === \App\Enums\CustomerFieldType::File ? $input_id.'-help ' : '').($has_error ? $input_id.'-error' : ''));
@endphp

<div>
    <label for="{{ $input_id }}" class="block text-sm font-semibold text-slate-800">
        {{ $field['label'] }}
        @if ($is_required)
            <span class="text-red-600" aria-hidden="true">*</span>
        @else
            <span class="font-normal text-slate-500">(optional)</span>
        @endif
    </label>

    <div class="mt-1">
        @switch($type)
            @case(\App\Enums\CustomerFieldType::Textarea)
                <textarea id="{{ $input_id }}" name="fields[{{ $key }}]" rows="4" maxlength="5000" @required($is_required)
                    @if ($described_by) aria-describedby="{{ $described_by }}" @endif
                    @if ($has_error) aria-invalid="true" @endif
                    class="{{ $input_classes }}">{{ $value }}</textarea>
                @break

            @case(\App\Enums\CustomerFieldType::Select)
                <select id="{{ $input_id }}" name="fields[{{ $key }}]" @required($is_required)
                    @if ($described_by) aria-describedby="{{ $described_by }}" @endif
                    @if ($has_error) aria-invalid="true" @endif
                    class="{{ $input_classes }} min-h-[48px]">
                    <option value="">Choose…</option>
                    @foreach ($field['options'] ?? [] as $option)
                        <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @break

            @case(\App\Enums\CustomerFieldType::File)
                <input id="{{ $input_id }}" name="fields[{{ $key }}]" type="file" @required($is_required)
                    accept="{{ collect($upload_rules['allowed_extensions'])->map(fn (string $extension): string => '.'.$extension)->implode(',') }}"
                    aria-describedby="{{ $described_by }}"
                    @if ($has_error) aria-invalid="true" @endif
                    class="block w-full rounded-xl border border-slate-300 bg-white text-base text-slate-700 file:mr-4 file:min-h-[48px] file:border-0 file:bg-brand-50 file:px-4 file:font-semibold file:text-brand-800 hover:file:bg-brand-100">
                <p id="{{ $input_id }}-help" class="mt-1 text-sm text-slate-500">
                    PDF, JPG or PNG, up to {{ \Illuminate\Support\Number::fileSize($upload_rules['max_kilobytes'] * 1024) }}.
                    @if ($errors->any()) If the form showed errors, please choose the file again. @endif
                </p>
                @break

            @default
                @php
                    $input_attributes = new \Illuminate\View\ComponentAttributeBag(array_filter(match ($type) {
                        \App\Enums\CustomerFieldType::Number => [
                            'type' => 'number',
                            'inputmode' => 'decimal',
                            'step' => 'any',
                            'min' => $field['min'] ?? null,
                            'max' => $field['max'] ?? null,
                        ],
                        \App\Enums\CustomerFieldType::Phone => ['type' => 'tel', 'autocomplete' => 'tel', 'inputmode' => 'tel'],
                        \App\Enums\CustomerFieldType::Email => ['type' => 'email', 'autocomplete' => 'email'],
                        default => ['type' => 'text', 'maxlength' => 255],
                    }, fn (mixed $attribute): bool => $attribute !== null));
                @endphp
                <input id="{{ $input_id }}" name="fields[{{ $key }}]" value="{{ $value }}" @required($is_required)
                    @if ($described_by) aria-describedby="{{ $described_by }}" @endif
                    @if ($has_error) aria-invalid="true" @endif
                    {{ $input_attributes }}
                    class="{{ $input_classes }} min-h-[48px]">
        @endswitch
    </div>

    @error($error_key)
        <p id="{{ $input_id }}-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
    @enderror
</div>
