<?php

namespace App\Providers;

use App\Models\Message;
use App\Models\StoryComment;
use App\Models\PrivateMessage;
use App\Models\Oneliner;
use App\Models\File;
use App\Models\GraffitiWall;
use App\Policies\MessagePolicy;
use App\Policies\StoryCommentPolicy;
use App\Policies\PrivateMessagePolicy;
use App\Policies\OnelinerPolicy;
use App\Policies\FilePolicy;
use App\Policies\GraffitiWallPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Message::class => MessagePolicy::class,
        StoryComment::class => StoryCommentPolicy::class,
        PrivateMessage::class => PrivateMessagePolicy::class,
        Oneliner::class => OnelinerPolicy::class,
        File::class => FilePolicy::class,
        GraffitiWall::class => GraffitiWallPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
