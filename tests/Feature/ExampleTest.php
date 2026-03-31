<?php

test('root redirects to portal', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/portal');
});
