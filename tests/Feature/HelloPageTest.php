<?php

namespace Tests\Feature;

use App\Livewire\Hello;
use Livewire\Livewire;
use Tests\TestCase;

class HelloPageTest extends TestCase
{
    /**
     * The /hello page renders the Livewire component.
     *
     * @return void
     */
    public function test_hello_page_renders(): void
    {
        $this->get('/hello')
            ->assertOk()
            ->assertSeeLivewire(Hello::class)
            ->assertSee('Hello!');
    }

    /**
     * The component reacts to input and actions.
     *
     * @return void
     */
    public function test_hello_component_is_interactive(): void
    {
        Livewire::test(Hello::class)
            ->set('name', 'Ada')
            ->assertSee('Hello, Ada!')
            ->call('increment')
            ->call('increment')
            ->assertSet('click_count', 2);
    }
}
