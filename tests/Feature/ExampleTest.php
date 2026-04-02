<?php

use App\Livewire\WelcomePage;

test('root shows welcome landing page', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSeeLivewire(WelcomePage::class);
});
