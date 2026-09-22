<?php

it('renders the ChurchX portfolio with an admin login entry point', function () {
    $response = $this->get('/');

    $response
        ->assertSee('One calm command center for')
        ->assertSee('People &amp; membership', escape: false)
        ->assertSee('Finance with integrity')
        ->assertSee('Admin login')
        ->assertSee('href="'.route('backpack.auth.login').'"', escape: false);
});
