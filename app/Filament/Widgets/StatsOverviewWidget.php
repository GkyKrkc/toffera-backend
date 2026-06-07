<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers    = User::count();
        $activeUsers   = User::active()->count();
        $pendingAgents = User::agents()->where('status', 'pending')->count();
        $bannedUsers   = User::banned()->count();
        $totalAgents   = User::agents()->where('status', 'active')->count();
        $totalBuyers   = User::buyers()->where('status', 'active')->count();

        return [
            Stat::make('Toplam Kullanıcı', $totalUsers)
                ->description("Aktif: {$activeUsers}")
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make('Bekleyen Başvuru', $pendingAgents)
                ->description('Onay bekleyen uzman')
                ->icon('heroicon-o-clock')
                ->color($pendingAgents > 0 ? 'warning' : 'success'),

            Stat::make('Aktif Uzman', $totalAgents)
                ->description('Onaylı emlakçı / galerici')
                ->icon('heroicon-o-identification')
                ->color('success'),

            Stat::make('Aktif Müşteri', $totalBuyers)
                ->description('Kayıtlı alıcı')
                ->icon('heroicon-o-user-group')
                ->color('info'),

            Stat::make('Banlı Kullanıcı', $bannedUsers)
                ->description('Hesabı askıya alınan')
                ->icon('heroicon-o-no-symbol')
                ->color($bannedUsers > 0 ? 'danger' : 'success'),
        ];
    }
}