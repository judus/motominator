<?php

namespace App\Ai\Providers;

use App\Ai\Storage\SafeConversationStore;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Contracts\ConversationStore;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConversationStore::class, SafeConversationStore::class);
    }
}
