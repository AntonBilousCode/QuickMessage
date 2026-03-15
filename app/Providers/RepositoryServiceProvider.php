<?php

namespace App\Providers;

use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Contracts\Services\MessageServiceInterface;
use App\Repositories\MessageRepository;
use App\Services\MessageService;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All interface → implementation bindings.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        MessageRepositoryInterface::class => MessageRepository::class,
        MessageServiceInterface::class => MessageService::class,
    ];

    public function register(): void
    {
        // Bindings are auto-resolved via the $bindings property above.
        // No manual binding needed unless constructor args require it.
    }
}
