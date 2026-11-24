<?php

namespace ArchTestsFixture\Application\Listeners;

class CloseOrdersListener
{
    public function handle(object $event): void
    {
        $event->product->orders()->where('open', true)->update(['open' => false]);

        \DB::table('orders')->count();
    }
}
