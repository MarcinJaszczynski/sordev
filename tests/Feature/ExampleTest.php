<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    // Root may redirect to region (302) or return 200 depending on environment.
    $this->assertContains($response->getStatusCode(), [200, 302]);
});
