<?php

namespace App\Policies;

use App\Models\IngestionFile;
use App\Models\User;
use Filament\Facades\Filament;

class IngestionFilePolicy
{
    public function view(User $user, IngestionFile $ingestionFile): bool
    {
        return $this->mayAccessThroughAdminPanel($user);
    }

    public function download(User $user, IngestionFile $ingestionFile): bool
    {
        return $this->mayAccessThroughAdminPanel($user);
    }

    protected function mayAccessThroughAdminPanel(User $user): bool
    {
        $panel = Filament::getPanel('admin');

        return $panel !== null && $user->canAccessPanel($panel);
    }
}
