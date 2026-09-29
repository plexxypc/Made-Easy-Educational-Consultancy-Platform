<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Smoke-test page at /hello proving Livewire renders and round-trips.
 * Safe to delete once real Livewire pages exist.
 */
#[Layout('layouts.guest')]
#[Title('Hello')]
class Hello extends Component
{
    /**
     * Name typed by the visitor (bound live to the input).
     *
     * @var string
     */
    public string $name = '';

    /**
     * Number of times the button has been pressed.
     *
     * @var int
     */
    public int $click_count = 0;

    /**
     * Increment the click counter (server round-trip).
     *
     * @return void
     */
    public function increment(): void
    {
        $this->click_count++;
    }

    /**
     * Render the component.
     *
     * @return View
     */
    public function render(): View
    {
        return view('livewire.hello');
    }
}
