<?php

it('returns 404 for an unknown provider', function (): void {
    $this->post('/webhooks/inbound/unknown-provider')->assertNotFound();
});
